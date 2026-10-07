<?php

declare(strict_types=1);

namespace App\Foundation\System;

/**
 * JobBatch
 *
 * The main entry point. Wraps CMD (environment/OS auto-detection + process
 * spawning), JobStore (cross-process job state), and Scheduler (cron /
 * Task Scheduler registration) behind one small API:
 *
 *   $batch = new JobBatch();
 *   $job   = $batch->run('php /path/to/worker.php --task=report');   // fire-and-forget, non-blocking
 *   $batch->status($job->id)->status;                                 // poll later
 *
 *   $job2 = $batch->add('php /path/to/cleanup.php');
 *   $batch->scheduler()->schedule($job2, '0 * * * *');                // hourly, via cron/Task Scheduler
 *
 * Dispatch is "truly" non-blocking on every supported OS: the caller's PHP
 * process never waits on the job's command. Instead CMD::runInBackground()
 * detaches a small runner process (bin/run-job.php) which executes the
 * command and writes the result back into the JobStore, so the caller can
 * poll status() at its own pace.
 */
final class JobBatch
{
    private CMD $cmd;
    private JobStore $store;
    private Scheduler $scheduler;
    private string $runnerScript;

    public function __construct(?CMD $cmd = null, ?JobStore $store = null, ?string $runnerScript = null)
    {
        $this->cmd = $cmd ?? new CMD();
        $this->store = $store ?? new JobStore();
        $this->runnerScript = $runnerScript ?? (__DIR__ . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'run-job.php');

        if (!is_file($this->runnerScript)) {
            throw new \RuntimeException("Runner script not found: {$this->runnerScript}");
        }

        $this->scheduler = new Scheduler($this->cmd, $this->store, $this->runnerScript);
    }

    public function cmd(): CMD
    {
        return $this->cmd;
    }

    public function store(): JobStore
    {
        return $this->store;
    }

    public function scheduler(): Scheduler
    {
        return $this->scheduler;
    }

    /** Environment report from CMD - OS, arch, capabilities, cron/task-scheduler availability, etc. */
    public function environment(): array
    {
        return $this->cmd->detect();
    }

    /**
     * Register a new job without running it yet.
     *
     * Options: id, cwd, max_attempts, meta (array of arbitrary caller data).
     */
    public function add(string $command, array $options = []): Job
    {
        $id = (string) ($options['id'] ?? $this->generateId());

        $job = new Job(
            id: $id,
            command: $command,
            cwd: $options['cwd'] ?? null,
            maxAttempts: max(1, (int) ($options['max_attempts'] ?? 1)),
            meta: is_array($options['meta'] ?? null) ? $options['meta'] : [],
        );

        $this->store->save($job);

        return $job;
    }

    /**
     * Dispatch a job to run immediately, detached and non-blocking, regardless
     * of OS. Returns instantly - the command itself runs in a background
     * process. Call status($id) afterwards to check progress/result.
     */
    public function dispatch(Job|string $job): Job
    {
        $job = $job instanceof Job ? $job : $this->mustFind($job);

        if (!$this->cmd->hasAnyProcessSpawner()) {
            throw new \RuntimeException(
                'No process-spawning function is available (proc_open/exec/shell_exec/popen are all disabled).'
            );
        }

        $job->status = Job::STATUS_QUEUED;
        $job->logFile = $this->store->logsDir() . DIRECTORY_SEPARATOR . $job->id . '.log';
        $this->store->save($job);

        $runnerCommand = $this->buildRunnerCommand($job->id);
        $pid = $this->cmd->runInBackground($runnerCommand, $job->logFile);

        $job->pid = $pid ?: null;
        $this->store->save($job);

        return $job;
    }

    /** Convenience: add() + dispatch() in one call. */
    public function run(string $command, array $options = []): Job
    {
        return $this->dispatch($this->add($command, $options));
    }

    /** Convenience: add() + Scheduler::schedule() in one call, for a recurring job. */
    public function every(string $command, string $cronExpression, array $options = [], array $windowsOptions = []): Job
    {
        $job = $this->add($command, $options);

        return $this->scheduler->schedule($job, $cronExpression, $windowsOptions);
    }

    public function status(string $id): ?Job
    {
        return $this->store->load($id);
    }

    /** @return array<string,Job> */
    public function all(): array
    {
        return $this->store->all();
    }

    /** Best-effort: signal the OS process to stop and mark the job cancelled. */
    public function cancel(string $id): bool
    {
        $job = $this->store->load($id);
        if ($job === null || $job->pid === null || $job->isFinished()) {
            return false;
        }

        $this->killPid($job->pid);

        $job->status = Job::STATUS_CANCELLED;
        $job->finishedAt = microtime(true);
        $this->store->save($job);

        return true;
    }

    /** Block and wait (polling) until the job finishes or the timeout elapses. Only for callers that want to wait. */
    public function wait(string $id, float $timeoutSeconds = 30.0, float $pollIntervalSeconds = 0.25): ?Job
    {
        $deadline = microtime(true) + $timeoutSeconds;

        do {
            $job = $this->store->load($id);
            if ($job !== null && $job->isFinished()) {
                return $job;
            }
            usleep((int) ($pollIntervalSeconds * 1_000_000));
        } while (microtime(true) < $deadline);

        return $this->store->load($id);
    }

    /* -------------------------------------------------------------- */

    private function buildRunnerCommand(string $id): string
    {
        $php = $this->cmd->getPhpBinaryPath() ?: 'php';

        return sprintf(
            '%s %s --job=%s --store=%s',
            escapeshellarg($php),
            escapeshellarg($this->runnerScript),
            escapeshellarg($id),
            escapeshellarg($this->store->dir())
        );
    }

    private function killPid(int $pid): void
    {
        if ($this->cmd->isWindows()) {
            $this->cmd->run("taskkill /PID {$pid} /F /T");
            return;
        }

        $this->cmd->run("kill -TERM {$pid} 2>/dev/null");
    }

    private function mustFind(string $id): Job
    {
        $job = $this->store->load($id);
        if ($job === null) {
            throw new \RuntimeException("Job not found: {$id}");
        }

        return $job;
    }

    private function generateId(): string
    {
        return date('Ymd-His') . '-' . bin2hex(random_bytes(4));
    }
}
