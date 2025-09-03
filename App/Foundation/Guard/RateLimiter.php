<?php

declare(strict_types=1);

namespace App\Foundation\Guard;

use PDO;
use Throwable;

class RateLimiter
{
    private $ip;
    private $limit;
    private $timeFrame;
    private $banTime;
    private $type;
    private $storagePath;
    private $storage_path_dir;

    public function __construct(string $type, string $storage_path, int $limit = 10, int $timeFrame = 60, int $banTime = 300)
    {
        $this->ip = $_SERVER['REMOTE_ADDR'];
        $this->storage_path_dir = $storage_path;
        $this->limit = $limit;
        $this->timeFrame = $timeFrame;
        $this->banTime = $banTime;
        $this->type = $type;
        $this->storagePath = $storage_path . "/cache/rate-limiter/{$this->type}_rate_limit_{$this->ip}.log";
    }

    public function check()
    {
        if ($this->isBanned()) {
            $this->blockAccess("Too Many Requests - You are temporarily banned.");
        }

        $requests = $this->loadRequests();

        $requests = array_filter($requests, fn($t) => $t > time() - $this->timeFrame);

        if (count($requests) >= $this->limit) {
            $this->banUser();
            $this->blockAccess("Too Many Requests - Slow down.");
        }

        $requests[] = time();
        $this->saveRequests($requests);
    }

    private function isBanned()
    {
        $banFile = $this->storage_path_dir . "cache/rate-limiter/{$this->type}_ban_{$this->ip}.log";
        return file_exists($banFile) && file_get_contents($banFile) > time();
    }

    private function banUser()
    {
        file_put_contents($this->storage_path_dir . "/cache/rate-limiter/{$this->type}_ban_{$this->ip}.log", time() + $this->banTime);
    }

    private function loadRequests()
    {
        return file_exists($this->storagePath) ? explode("\n", trim(file_get_contents($this->storagePath))) : [];
    }

    private function saveRequests($requests)
    {
        file_put_contents($this->storagePath, implode("\n", $requests), LOCK_EX);
    }

    private function blockAccess($message)
    {
        http_response_code(429);
        die($message);
    }
}

class RateLimiterSQL
{
    private PDO $connection;
    private $ip;
    private $limit;
    private $timeFrame;
    private $banTime;
    private $prefix;
    private $type;

    public function __construct(string $type, string $dbPath, int $limit = 10, int $timeFrame = 60, int $banTime = 300)
    {
        $this->ip = $_SERVER['REMOTE_ADDR'];
        $this->limit = $limit;
        $this->timeFrame = $timeFrame;
        $this->banTime = $banTime;
        $this->type = $type;
        $this->prefix = $type . '_';

        $this->connection = new PDO('sqlite:' . $dbPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT => true
        ]);

        $this->initializeDatabase();
    }

    private function initializeDatabase()
    {
        $setup = [
            'CREATE TABLE IF NOT EXISTS ' . $this->prefix . 'rate_limits (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip VARCHAR(45) NOT NULL,
                timestamp INTEGER NOT NULL,
                type VARCHAR(50) NOT NULL
            )',
            'CREATE TABLE IF NOT EXISTS ' . $this->prefix . 'bans (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip VARCHAR(45) NOT NULL,
                ban_until INTEGER NOT NULL,
                type VARCHAR(50) NOT NULL,
                created_at INTEGER DEFAULT (strftime(\'%s\', \'now\'))
            )',
            'CREATE INDEX IF NOT EXISTS idx_rate_limits_ip_type ON ' . $this->prefix . 'rate_limits (ip, type)',
            'CREATE INDEX IF NOT EXISTS idx_rate_limits_timestamp ON ' . $this->prefix . 'rate_limits (timestamp)',
            'CREATE INDEX IF NOT EXISTS idx_bans_ip_type ON ' . $this->prefix . 'bans (ip, type)',
            'CREATE INDEX IF NOT EXISTS idx_bans_ban_until ON ' . $this->prefix . 'bans (ban_until)'
        ];

        foreach ($setup as $sql) {
            $this->connection->exec($sql);
        }
    }

    public function check()
    {
        if ($this->isBanned()) {
            $this->blockAccess("Too Many Requests - You are temporarily banned.");
        }

        $currentTime = time();
        $this->cleanOldRequests($currentTime);

        $requestCount = $this->getRecentRequestCount($currentTime);

        if ($requestCount >= $this->limit) {
            $this->banUser($currentTime);
            $this->blockAccess("Too Many Requests - Slow down.");
        }

        $this->addRequest($currentTime);
    }

    private function isBanned()
    {
        $currentTime = time();
        $stmt = $this->connection->prepare(
            'SELECT ban_until FROM ' . $this->prefix . 'bans 
             WHERE ip = ? AND type = ? AND ban_until > ?'
        );
        $stmt->execute([$this->ip, $this->type, $currentTime]);

        return $stmt->fetch() !== false;
    }

    private function banUser($currentTime)
    {
        $banUntil = $currentTime + $this->banTime;

        // Check if user already has a ban record
        $stmt = $this->connection->prepare(
            'SELECT id FROM ' . $this->prefix . 'bans 
             WHERE ip = ? AND type = ?'
        );
        $stmt->execute([$this->ip, $this->type]);

        if ($stmt->fetch()) {
            // Update existing ban
            $stmt = $this->connection->prepare(
                'UPDATE ' . $this->prefix . 'bans 
                 SET ban_until = ? 
                 WHERE ip = ? AND type = ?'
            );
            $stmt->execute([$banUntil, $this->ip, $this->type]);
        } else {
            // Insert new ban
            $stmt = $this->connection->prepare(
                'INSERT INTO ' . $this->prefix . 'bans (ip, ban_until, type) 
                 VALUES (?, ?, ?)'
            );
            $stmt->execute([$this->ip, $banUntil, $this->type]);
        }
    }

    private function cleanOldRequests($currentTime)
    {
        $cutoffTime = $currentTime - $this->timeFrame;

        $stmt = $this->connection->prepare(
            'DELETE FROM ' . $this->prefix . 'rate_limits 
             WHERE timestamp < ? AND type = ?'
        );
        $stmt->execute([$cutoffTime, $this->type]);
    }

    private function getRecentRequestCount($currentTime)
    {
        $cutoffTime = $currentTime - $this->timeFrame;

        $stmt = $this->connection->prepare(
            'SELECT COUNT(*) as count FROM ' . $this->prefix . 'rate_limits 
             WHERE ip = ? AND type = ? AND timestamp >= ?'
        );
        $stmt->execute([$this->ip, $this->type, $cutoffTime]);

        $result = $stmt->fetch();
        return (int) $result['count'];
    }

    private function addRequest($timestamp)
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO ' . $this->prefix . 'rate_limits (ip, timestamp, type) 
             VALUES (?, ?, ?)'
        );
        $stmt->execute([$this->ip, $timestamp, $this->type]);
    }

    private function blockAccess($message)
    {
        http_response_code(429);
        die($message);
    }

    // Optional: Cleanup method to remove old data
    public function cleanupOldData($maxAge = 86400) // 24 hours default
    {
        $cutoffTime = time() - $maxAge;

        // Clean old rate limits
        $stmt = $this->connection->prepare(
            'DELETE FROM ' . $this->prefix . 'rate_limits 
             WHERE timestamp < ?'
        );
        $stmt->execute([$cutoffTime]);

        // Clean expired bans
        $stmt = $this->connection->prepare(
            'DELETE FROM ' . $this->prefix . 'bans 
             WHERE ban_until < ?'
        );
        $stmt->execute([time()]);
    }
}