<?php

declare(strict_types=1);

namespace App\Foundation\Guard;

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
