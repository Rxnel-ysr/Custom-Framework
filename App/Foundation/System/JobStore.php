<?php

declare(strict_types=1);

namespace App\Foundation\System;

/**
 * JobStore
 *
 * Persists Job records as one JSON file per job under a storage directory
 * (defaults to a subfolder of the system temp dir). This is what lets a
 * detached background process, a cron tick, or a Windows scheduled task -
 * each a completely separate PHP process with no shared memory - find a job
 * by id and report its result back.
 *
 * Writes are atomic (write-to-temp-file then rename) and reads/writes use
 * flock() so concurrent workers don't corrupt a job record.
 */
final class JobStore
{
    private string $dir;

    public function __construct(?string $dir = null)
    {
        $this->dir = rtrim($dir ?? (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jobbatch'), '/\\');

        if (!is_dir($this->dir) && !@mkdir($this->dir, 0775, true) && !is_dir($this->dir)) {
            throw new \RuntimeException("Unable to create job store directory: {$this->dir}");
        }

        $logsDir = $this->dir . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($logsDir)) {
            @mkdir($logsDir, 0775, true);
        }
    }

    public function dir(): string
    {
        return $this->dir;
    }

    public function logsDir(): string
    {
        return $this->dir . DIRECTORY_SEPARATOR . 'logs';
    }

    private function path(string $id): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $id);
        return $this->dir . DIRECTORY_SEPARATOR . $safe . '.json';
    }

    public function save(Job $job): void
    {
        $path = $this->path($job->id);
        $tmp = $path . '.tmp.' . bin2hex(random_bytes(4));

        $bytes = json_encode($job->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($bytes === false) {
            throw new \RuntimeException("Unable to encode job {$job->id} as JSON: " . json_last_error_msg());
        }

        if (file_put_contents($tmp, $bytes, LOCK_EX) === false) {
            throw new \RuntimeException("Unable to write temp job file: {$tmp}");
        }

        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException("Unable to save job file: {$path}");
        }
    }

    public function load(string $id): ?Job
    {
        $path = $this->path($id);
        if (!is_file($path)) {
            return null;
        }

        $fp = fopen($path, 'r');
        if ($fp === false) {
            return null;
        }

        flock($fp, LOCK_SH);
        $raw = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        $data = json_decode((string) $raw, true);

        return is_array($data) ? Job::fromArray($data) : null;
    }

    public function delete(string $id): bool
    {
        $path = $this->path($id);
        return is_file($path) ? @unlink($path) : false;
    }

    /** @return array<string,Job> keyed by job id */
    public function all(): array
    {
        $jobs = [];

        foreach (glob($this->dir . '/*.json') ?: [] as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            if (is_array($data)) {
                $job = Job::fromArray($data);
                $jobs[$job->id] = $job;
            }
        }

        ksort($jobs);

        return $jobs;
    }

    /**
     * Run $fn while holding an exclusive advisory lock scoped to this job id,
     * so two workers (e.g. an overlapping cron tick) can't process the same
     * job at once. The lock file is separate from the JSON data file.
     */
    public function withLock(string $id, callable $fn): mixed
    {
        $lockPath = $this->path($id) . '.lock';
        $fp = fopen($lockPath, 'c');
        if ($fp === false) {
            throw new \RuntimeException("Unable to open lock file: {$lockPath}");
        }

        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            throw new \RuntimeException("Unable to acquire lock for job: {$id}");
        }

        try {
            return $fn();
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /** Try to acquire the lock without blocking; returns false immediately if another worker holds it. */
    public function withLockNonBlocking(string $id, callable $fn): mixed
    {
        $lockPath = $this->path($id) . '.lock';
        $fp = fopen($lockPath, 'c');
        if ($fp === false) {
            throw new \RuntimeException("Unable to open lock file: {$lockPath}");
        }

        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            return false;
        }

        try {
            return $fn();
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /** Remove job + log files older than $olderThanSeconds and already finished. Returns count removed. */
    public function prune(int $olderThanSeconds = 86400): int
    {
        $removed = 0;
        $cutoff = microtime(true) - $olderThanSeconds;

        foreach ($this->all() as $job) {
            if ($job->isFinished() && $job->finishedAt !== null && $job->finishedAt < $cutoff) {
                $this->delete($job->id);
                if ($job->logFile && is_file($job->logFile)) {
                    @unlink($job->logFile);
                }
                $removed++;
            }
        }

        return $removed;
    }
}
