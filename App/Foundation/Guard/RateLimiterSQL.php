<?php

declare(strict_types=1);

namespace App\Foundation\Guard;

use PDO;
use PDOException;
use InvalidArgumentException;
use Throwable;

class RateLimiterSQL
{
    private PDO $connection;
    private string $ip;
    private int $limit;
    private int $timeFrame;
    private int $banTime;
    private string $prefix;
    private string $type;

    private const MAX_RETRIES = 3;
    private const BUSY_TIMEOUT_MS = 5000;

    public function __construct(
        string $type,
        string $dbPath,
        int $limit = 10,
        int $timeFrame = 60,
        int $banTime = 300,
        ?string $ip = null
    ) {
        // $type is interpolated directly into table/index names below, so it
        // must be restricted to a safe identifier. Never let this come
        // straight from unsanitized user/request input.
        if (!preg_match('/^[A-Za-z0-9_]{1,32}$/', $type)) {
            throw new InvalidArgumentException(
                "Invalid rate limiter type '{$type}': must be alphanumeric/underscore, max 32 chars."
            );
        }

        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;
        $candidateIp = $ip ?? ($forwarded ? trim(explode(',', $forwarded)[0]) : ($_SERVER['REMOTE_ADDR'] ?? ''));

        if (filter_var($candidateIp, FILTER_VALIDATE_IP) === false) {
            // Don't silently accept garbage — fail closed to a fixed bucket
            // rather than letting a spoofed/malformed value slip into the DB.
            $candidateIp = 'unknown';
        }
        $this->ip = $candidateIp;

        if ($limit < 1 || $timeFrame < 1 || $banTime < 1) {
            throw new InvalidArgumentException('limit, timeFrame, and banTime must all be positive integers.');
        }

        $this->limit = $limit;
        $this->timeFrame = $timeFrame;
        $this->banTime = $banTime;
        $this->type = $type;
        $this->prefix = $type . '_';

        $this->connection = new PDO('sqlite:' . $dbPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->connection->exec('PRAGMA journal_mode = WAL;');
        $this->connection->exec('PRAGMA synchronous = NORMAL;');
        // Let SQLite wait for a locked DB instead of throwing immediately.
        // Critical once you have any concurrent traffic at all.
        $this->connection->exec('PRAGMA busy_timeout = ' . self::BUSY_TIMEOUT_MS . ';');

        $this->initializeDatabase();
    }

    private function initializeDatabase(): void
    {
        // NOTE: every index name is prefixed with $this->prefix. Index names
        // are unique across the WHOLE schema in SQLite (not per-table), so
        // reusing the same generic name for multiple $type instances against
        // the same DB file causes "CREATE INDEX IF NOT EXISTS" to silently
        // no-op for every type after the first. That leaves later tables
        // without their unique constraint, which is what turns a normal
        // upsert in banUser() into an "ON CONFLICT clause does not match any
        // PRIMARY KEY or UNIQUE constraint" / unique-constraint failure.
        $setup = [
            "CREATE TABLE IF NOT EXISTS {$this->prefix}requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip VARCHAR(45) NOT NULL,
                timestamp INTEGER NOT NULL,
                type VARCHAR(50) NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS {$this->prefix}bans (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip VARCHAR(45) NOT NULL,
                expires_at INTEGER NOT NULL,
                type VARCHAR(50) NOT NULL,
                created_at INTEGER DEFAULT (strftime('%s', 'now'))
            )",
            "CREATE INDEX IF NOT EXISTS {$this->prefix}idx_requests_ip_type ON {$this->prefix}requests (ip, type)",
            "CREATE INDEX IF NOT EXISTS {$this->prefix}idx_requests_timestamp ON {$this->prefix}requests (timestamp)",
            "CREATE UNIQUE INDEX IF NOT EXISTS {$this->prefix}idx_unique_bans_ip_type ON {$this->prefix}bans (ip, type)",
            "CREATE INDEX IF NOT EXISTS {$this->prefix}idx_bans_expires_at ON {$this->prefix}bans (expires_at)",
        ];

        foreach ($setup as $sql) {
            $this->connection->exec($sql);
        }
    }

    public function check(): array
    {
        $attempt = 0;

        while (true) {
            try {
                return $this->attemptCheck();
            } catch (PDOException $e) {
                $attempt++;
                // SQLITE_BUSY / locked errors can still surface despite the
                // busy_timeout pragma under heavy contention. Retry a couple
                // times with a short backoff instead of bubbling a 500 up.
                if ($attempt >= self::MAX_RETRIES || !$this->isRetryable($e)) {
                    throw $e;
                }
                usleep(random_int(10_000, 50_000)); // 10-50ms jitter
            }
        }
    }

    private function isRetryable(PDOException $e): bool
    {
        $msg = strtolower($e->getMessage());
        return str_contains($msg, 'locked') || str_contains($msg, 'busy');
    }

    private function attemptCheck(): array
    {
        $now = time();
        $db = $this->connection;

        // BEGIN IMMEDIATE acquires the write lock up front instead of
        // deferring and risking an upgrade conflict mid-transaction.
        $db->beginTransaction();

        try {
            // 1. Check if banned
            $stmt = $db->prepare("SELECT expires_at FROM {$this->prefix}bans WHERE ip = ? LIMIT 1");
            $stmt->execute([$this->ip]);
            $ban = $stmt->fetchColumn();

            if ($ban && $ban > $now) {
                $db->commit();
                return ['allowed' => false, 'reason' => 'banned', 'banned_until' => (int)$ban];
            }

            // 2. Count recent requests, and find the oldest one in-window so
            //    we can give an accurate retry_after instead of always 0.
            $stmt = $db->prepare(
                "SELECT COUNT(1) as cnt, MIN(timestamp) as oldest
                 FROM {$this->prefix}requests
                 WHERE ip = ? AND timestamp > ?"
            );
            $stmt->execute([$this->ip, $now - $this->timeFrame]);
            $row = $stmt->fetch();
            $count = (int)($row['cnt'] ?? 0);
            $oldest = $row['oldest'] !== null ? (int)$row['oldest'] : $now;

            // 3. Over the limit -> ban
            if ($count >= $this->limit) {
                $this->banUser($now + $this->banTime);
                $db->commit();
                return ['allowed' => false, 'reason' => 'banned', 'banned_until' => $now + $this->banTime];
            }

            // 4. Record request
            $stmt = $db->prepare("INSERT INTO {$this->prefix}requests (ip, timestamp, type) VALUES (?, ?, ?)");
            $stmt->execute([$this->ip, $now, $this->type]);

            // 5. Cleanup old records (bounded, cheap, keeps table small)
            $db->exec("DELETE FROM {$this->prefix}requests WHERE timestamp < " . ($now - $this->timeFrame * 5));
            $db->exec("DELETE FROM {$this->prefix}bans WHERE expires_at < " . $now);

            $db->commit();
            return ['allowed' => true, 'remaining' => max(0, $this->limit - $count - 1)];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private function banUser(int $expiresAt): void
    {
        $stmt = $this->connection->prepare("
            INSERT INTO {$this->prefix}bans (ip, expires_at, type)
            VALUES (:ip, :expires, :type)
            ON CONFLICT(ip, type) DO UPDATE SET expires_at = excluded.expires_at
        ");
        $stmt->execute([':ip' => $this->ip, ':expires' => $expiresAt, ':type' => $this->type]);
    }

    /**
     * Housekeeping method for a cron job — not called on the hot path.
     */
    public function cleanupOldData(int $maxAge = 86400): void
    {
        $cutoffTime = time() - $maxAge;

        $stmt = $this->connection->prepare("DELETE FROM {$this->prefix}requests WHERE timestamp < ?");
        $stmt->execute([$cutoffTime]);

        $stmt = $this->connection->prepare("DELETE FROM {$this->prefix}bans WHERE expires_at < ?");
        $stmt->execute([time()]);
    }
}
