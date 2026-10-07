<?php

declare(strict_types=1);

namespace App\Foundation\System;

/**
 * Scheduler
 *
 * Registers a Job to run repeatedly using whatever the host OS actually
 * offers: the user's crontab on Linux/macOS/BSD, or `schtasks` on Windows.
 * Each recurring entry simply invokes bin/run-job.php for that job id, so
 * the same runner script and job-state file are used whether a job was
 * dispatched once in the background or is ticking on a schedule.
 */
final class Scheduler
{
    private const MARKER_PREFIX = '# jobbatch:';

    public function __construct(
        private readonly CMD $cmd,
        private readonly JobStore $store,
        private readonly string $runnerScript,
    ) {}

    /**
     * Schedule $job to run repeatedly.
     *
     * @param string $cronExpression standard 5-field cron expression, used verbatim on Unix
     *                                (e.g. "*\/5 * * * *" for every 5 minutes). On Windows this
     *                                is ignored in favor of $windowsOptions, but should still be
     *                                supplied for documentation / portability.
     * @param array  $windowsOptions ['schedule' => 'MINUTE'|'HOURLY'|'DAILY'|'WEEKLY', 'modifier' => int]
     *                                mapped to `schtasks /sc <schedule> /mo <modifier>`.
     */
    public function schedule(Job $job, string $cronExpression, array $windowsOptions = []): Job
    {
        if (!$this->cmd->hasScheduler()) {
            throw new \RuntimeException('No system scheduler (cron or Task Scheduler) is available on this host.');
        }

        $job->cronId = $job->id;
        $job->cronExpression = $cronExpression;
        if ($job->status === Job::STATUS_PENDING) {
            // Recurring jobs sit "pending" between ticks; each tick's runner
            // process flips it to running/success/failed for that execution.
        }
        $this->store->save($job);

        if ($this->cmd->hasCron()) {
            $this->addCronEntry($job, $cronExpression);
        } elseif ($this->cmd->hasTaskScheduler()) {
            $this->addWindowsTask($job, $windowsOptions ?: ['schedule' => 'MINUTE', 'modifier' => 5]);
        }

        return $job;
    }

    public function unschedule(Job $job): bool
    {
        $removed = false;

        if ($this->cmd->hasCron()) {
            $removed = $this->removeCronEntry($job);
        } elseif ($this->cmd->hasTaskScheduler()) {
            $removed = $this->removeWindowsTask($job);
        }

        if ($removed) {
            $job->cronId = null;
            $job->cronExpression = null;
            $this->store->save($job);
        }

        return $removed;
    }

    /** List job ids currently registered as recurring entries, as far as the OS scheduler is concerned. */
    public function listScheduled(): array
    {
        $ids = [];

        if ($this->cmd->hasCron()) {
            $current = (string) $this->cmd->run('crontab -l')['stdout'];
            foreach (preg_split('/\R/', $current) ?: [] as $line) {
                if (preg_match('/' . preg_quote(self::MARKER_PREFIX, '/') . '(\S+)/', $line, $m)) {
                    $ids[] = $m[1];
                }
            }
        } elseif ($this->cmd->hasTaskScheduler()) {
            $result = $this->cmd->run('schtasks /Query /FO CSV /NH');
            foreach (preg_split('/\R/', (string) $result['stdout']) ?: [] as $line) {
                if (str_contains($line, 'JobBatch_')) {
                    if (preg_match('/JobBatch_([A-Za-z0-9_\-]+)/', $line, $m)) {
                        $ids[] = $m[1];
                    }
                }
            }
        }

        return $ids;
    }

    /* ----------------------------------------------------------------
     * Shared: the command each scheduler tick actually runs
     * ------------------------------------------------------------- */

    private function runnerCommandLine(Job $job): string
    {
        $php = $this->cmd->getPhpBinaryPath() ?: 'php';
        $logFile = $this->store->logsDir() . DIRECTORY_SEPARATOR . $job->id . '.cron.log';

        if ($this->cmd->isWindows()) {
            // schtasks wants a single executable command string, no shell redirection needed -
            // run-job.php writes its own per-job JSON, stdout/stderr capture happens inside it.
            return sprintf(
                '%s %s --job=%s --store=%s',
                $php,
                $this->runnerScript,
                $job->id,
                $this->store->dir()
            );
        }

        return sprintf(
            '%s %s --job=%s --store=%s >> %s 2>&1',
            escapeshellarg($php),
            escapeshellarg($this->runnerScript),
            escapeshellarg($job->id),
            escapeshellarg($this->store->dir()),
            escapeshellarg($logFile)
        );
    }

    /* ----------------------------------------------------------------
     * Unix cron
     * ------------------------------------------------------------- */

    private function addCronEntry(Job $job, string $cronExpression): void
    {
        $marker = self::MARKER_PREFIX . $job->id;
        $entryLine = trim($cronExpression) . ' ' . $this->runnerCommandLine($job) . ' ' . $marker;

        $lines = $this->currentCrontabLines();
        $lines = array_values(array_filter($lines, static fn($l) => !str_contains($l, $marker)));
        $lines[] = $entryLine;

        $this->writeCrontab(implode("\n", $lines) . "\n");
    }

    private function removeCronEntry(Job $job): bool
    {
        $marker = self::MARKER_PREFIX . $job->id;
        $lines = $this->currentCrontabLines();
        $filtered = array_values(array_filter($lines, static fn($l) => !str_contains($l, $marker)));

        if (count($filtered) === count($lines)) {
            return false; // nothing to remove
        }

        $this->writeCrontab(implode("\n", $filtered) . "\n");

        return true;
    }

    private function currentCrontabLines(): array
    {
        $current = (string) $this->cmd->run('crontab -l 2>/dev/null')['stdout'];
        $trimmed = trim($current);

        return $trimmed === '' ? [] : (preg_split('/\R/', $trimmed) ?: []);
    }

    private function writeCrontab(string $contents): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'jbcron');
        if ($tmp === false) {
            throw new \RuntimeException('Unable to create temp file for crontab update.');
        }

        file_put_contents($tmp, $contents);
        $result = $this->cmd->run('crontab ' . escapeshellarg($tmp));
        @unlink($tmp);

        if ($result['code'] !== 0) {
            throw new \RuntimeException('Failed to update crontab: ' . $result['stderr']);
        }
    }

    /* ----------------------------------------------------------------
     * Windows Task Scheduler
     * ------------------------------------------------------------- */

    private function taskName(Job $job): string
    {
        return 'JobBatch_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $job->id);
    }

    private function addWindowsTask(Job $job, array $options): void
    {
        $schedule = strtoupper((string) ($options['schedule'] ?? 'MINUTE'));
        $modifier = (string) ($options['modifier'] ?? 5);
        $name = $this->taskName($job);

        $command = sprintf(
            'schtasks /Create /TN %s /TR %s /SC %s /MO %s /F',
            escapeshellarg($name),
            escapeshellarg($this->runnerCommandLine($job)),
            escapeshellarg($schedule),
            escapeshellarg($modifier)
        );

        $result = $this->cmd->run($command);
        if ($result['code'] !== 0) {
            throw new \RuntimeException('Failed to create scheduled task: ' . $result['stdout'] . $result['stderr']);
        }
    }

    private function removeWindowsTask(Job $job): bool
    {
        $name = $this->taskName($job);
        $result = $this->cmd->run(sprintf('schtasks /Delete /TN %s /F', escapeshellarg($name)));

        return $result['code'] === 0;
    }
}
