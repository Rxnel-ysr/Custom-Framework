<?php

declare(strict_types=1);

namespace App\Foundation\Guard;

use PDO;
use Throwable;

class RateLimiterSQL
{
    private PDO $connection;
    private $ip;
    private $limit;
    private $timeFrame;
    private $banTime;
    private $prefix;
    private $type;

    public function __construct(string $type, string $dbPath, int $limit = 10, int $timeFrame = 60, int $banTime = 300, ?string $ip = null)
    {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;
        $this->ip = $ip ?? ($forwarded ? explode(',', $forwarded)[0] : $_SERVER['REMOTE_ADDR']);
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

        $this->initializeDatabase();
    }

    private function initializeDatabase()
    {
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
            "CREATE INDEX IF NOT EXISTS idx_requests_ip_type ON  {$this->prefix}requests (ip, type)",
            "CREATE INDEX IF NOT EXISTS idx_requests_timestamp ON  {$this->prefix}requests (timestamp)",
            "CREATE UNIQUE INDEX IF NOT EXISTS idx_unique_bans_ip_type ON  {$this->prefix}bans (ip, type)",
            "CREATE INDEX IF NOT EXISTS idx_bans_expires_at ON  {$this->prefix}bans (expires_at)"
        ];

        foreach ($setup as $sql) {
            $this->connection->exec($sql);
        }
    }

    public function check(): array
    {
        $now = time();
        $db = $this->connection;
        $db->beginTransaction();

        try {
            // 1. Check if banned
            $stmt = $db->prepare("SELECT expires_at FROM {$this->prefix}bans WHERE ip = ? LIMIT 1");
            $stmt->execute([$this->ip]);
            $ban = $stmt->fetchColumn();

            if ($ban && $ban > $now) {
                $db->commit();
                return ['allowed' => false, 'reason' => 'banned', 'banned_until' => $ban];
            }

            // 2. Count recent requests
            $stmt = $db->prepare("SELECT COUNT(1) FROM {$this->prefix}requests WHERE ip = ? AND timestamp > ?");
            $stmt->execute([$this->ip, $now - $this->timeFrame]);
            $count = (int)$stmt->fetchColumn();

            // 3. Apply thresholds
            if ($count >= $this->limit) {
                $this->banUser($now + $this->banTime);
                $db->commit();
                return ['allowed' => false, 'reason' => 'banned', 'banned_until' => $now + $this->banTime];
            }

            if ($count >= $this->limit) {
                // Mark as 'paused' but not banned
                $db->commit();
                return [
                    'allowed' => false,
                    'reason' => 'paused',
                    'retry_after' => $this->timeFrame - ($now - ($now - $this->timeFrame))
                ];
            }

            // 4. Record request
            $stmt = $db->prepare("INSERT INTO {$this->prefix}requests (ip, timestamp, type) VALUES (?, ?, ?)");
            $stmt->execute([$this->ip, $now, $this->type]);

            // 5. Cleanup old records
            $db->exec("DELETE FROM {$this->prefix}requests WHERE timestamp < " . ($now - $this->timeFrame * 5));
            $db->exec("DELETE FROM  {$this->prefix}bans WHERE expires_at < " . time());

            $db->commit();
            return ['allowed' => true];
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }


    private function banUser(int $expiresAt): void
    {
        $stmt = $this->connection->prepare("
        INSERT INTO {$this->prefix}bans (ip, expires_at, type)
        VALUES (:ip, :expires, :type)
        ON CONFLICT(ip, type) DO UPDATE SET expires_at = :expires
    ");
        $stmt->execute([':ip' => $this->ip, ':expires' => $expiresAt, ':type' => $this->type]);
    }
    // Optional: Cleanup method to remove old data
    public function cleanupOldData($maxAge = 86400) // 24 hours default
    {
        $cutoffTime = time() - $maxAge;

        // Clean old rate limits
        $stmt = $this->connection->prepare(
            "DELETE FROM  {$this->prefix}requests WHERE timestamp < ?"
        );
        $stmt->execute([$cutoffTime]);

        // Clean expired bans
        $stmt = $this->connection->prepare(
            "DELETE FROM  {$this->prefix}bans WHERE expires_at < ?"
        );
        $stmt->execute([time()]);
    }
}
