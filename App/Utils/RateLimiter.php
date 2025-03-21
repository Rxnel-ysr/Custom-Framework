<?php
namespace App\Utils\Guard;

class RateLimiter
{
    private $ip;
    private $limit;
    private $timeFrame;
    private $banTime;
    private $type;
    private $storagePath;

    public function __construct($type,$limit = 10, $timeFrame = 60, $banTime = 300)
    {
        $this->ip = $_SERVER['REMOTE_ADDR'];
        $this->limit = $limit;
        $this->timeFrame = $timeFrame;
        $this->banTime = $banTime;
        $this->type = $type;
        $this->storagePath = STORAGE_PATH . "cache/rate-limiter/{$this->type}_rate_limit_{$this->ip}.log";
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
        $banFile = STORAGE_PATH . "cache/rate-limiter/{$this->type}_ban_{$this->ip}.log";
        return file_exists($banFile) && file_get_contents($banFile) > time();
    }

    private function banUser()
    {
        file_put_contents(STORAGE_PATH . "cache/rate-limiter/{$this->type}_ban_{$this->ip}.log", time() + $this->banTime);
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
