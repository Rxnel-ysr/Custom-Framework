<?php

declare(strict_types=1);

namespace App\Foundation\System;

/**
 * Job
 *
 * A single unit of work: the shell command to run plus everything needed to
 * track its lifecycle (status, pid, exit code, output, timestamps, retry
 * bookkeeping). Jobs are plain data - JobStore persists them as JSON so that
 * a detached background process or a cron tick (a completely separate PHP
 * process) can look one up by id and update it.
 */
final class Job implements \JsonSerializable
{
    public const STATUS_PENDING   = 'pending';   // created, not yet dispatched
    public const STATUS_QUEUED    = 'queued';    // handed off to the OS (background/cron), not started yet
    public const STATUS_RUNNING   = 'running';   // actively executing
    public const STATUS_SUCCESS   = 'success';   // finished, exit code 0
    public const STATUS_FAILED    = 'failed';    // finished, non-zero exit code, no attempts left
    public const STATUS_CANCELLED = 'cancelled'; // killed before/while running

    public function __construct(
        public readonly string $id,
        public string $command,
        public string $status = self::STATUS_PENDING,
        public ?int $pid = null,
        public ?int $exitCode = null,
        public ?string $stdout = null,
        public ?string $stderr = null,
        public ?string $logFile = null,
        public ?string $cwd = null,
        public array $meta = [],
        public ?float $createdAt = null,
        public ?float $startedAt = null,
        public ?float $finishedAt = null,
        public int $attempts = 0,
        public int $maxAttempts = 1,
        /** Set when this job is also registered as a recurring cron / Task Scheduler entry. */
        public ?string $cronId = null,
        public ?string $cronExpression = null,
    ) {
        $this->createdAt ??= microtime(true);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            command: (string) ($data['command'] ?? ''),
            status: (string) ($data['status'] ?? self::STATUS_PENDING),
            pid: isset($data['pid']) ? (int) $data['pid'] : null,
            exitCode: isset($data['exitCode']) ? (int) $data['exitCode'] : null,
            stdout: $data['stdout'] ?? null,
            stderr: $data['stderr'] ?? null,
            logFile: $data['logFile'] ?? null,
            cwd: $data['cwd'] ?? null,
            meta: is_array($data['meta'] ?? null) ? $data['meta'] : [],
            createdAt: isset($data['createdAt']) ? (float) $data['createdAt'] : null,
            startedAt: isset($data['startedAt']) ? (float) $data['startedAt'] : null,
            finishedAt: isset($data['finishedAt']) ? (float) $data['finishedAt'] : null,
            attempts: (int) ($data['attempts'] ?? 0),
            maxAttempts: (int) ($data['maxAttempts'] ?? 1),
            cronId: $data['cronId'] ?? null,
            cronExpression: $data['cronExpression'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'command'        => $this->command,
            'status'         => $this->status,
            'pid'            => $this->pid,
            'exitCode'       => $this->exitCode,
            'stdout'         => $this->stdout,
            'stderr'         => $this->stderr,
            'logFile'        => $this->logFile,
            'cwd'            => $this->cwd,
            'meta'           => $this->meta,
            'createdAt'      => $this->createdAt,
            'startedAt'      => $this->startedAt,
            'finishedAt'     => $this->finishedAt,
            'attempts'       => $this->attempts,
            'maxAttempts'    => $this->maxAttempts,
            'cronId'         => $this->cronId,
            'cronExpression' => $this->cronExpression,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_SUCCESS, self::STATUS_FAILED, self::STATUS_CANCELLED], true);
    }

    public function isRecurring(): bool
    {
        return $this->cronId !== null;
    }

    public function durationSeconds(): ?float
    {
        if ($this->startedAt === null || $this->finishedAt === null) {
            return null;
        }

        return $this->finishedAt - $this->startedAt;
    }
}
