#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace App\Foundation\System\bin;

use App\Foundation\System\CMD;
use App\Foundation\System\Job;
use App\Foundation\System\JobStore;

/**
 * run-job.php
 *
 * Executes exactly one job and writes its result back to the JobStore.
 * This is the file JobBatch::dispatch() launches via CMD::runInBackground()
 * (fire-and-forget) and the file Scheduler registers with cron / schtasks
 * for recurring jobs. It is a standalone PHP process - it does not share
 * memory with whatever created the job, so all state travels through the
 * JobStore's JSON files.
 *
 * Usage:
 *   php run-job.php --job=<id> --store=<storage-dir>
 */

require_once __DIR__ . '/../CMD.php';
require_once __DIR__ . '/../Job.php';
require_once __DIR__ . '/../JobStore.php';


function jobbatch_truncate(string $text, int $limit = 50_000): string
{
    if (strlen($text) <= $limit) {
        return $text;
    }

    return substr($text, 0, $limit) . "\n...[truncated]";
}

$options = getopt('', ['job:', 'store:']);
$jobId = $options['job'] ?? null;
$storeDir = $options['store'] ?? null;

if (!is_string($jobId) || $jobId === '' || !is_string($storeDir) || $storeDir === '') {
    fwrite(STDERR, "Usage: run-job.php --job=<id> --store=<storage-dir>\n");
    exit(2);
}

try {
    $store = new JobStore($storeDir);
} catch (\Throwable $e) {
    fwrite(STDERR, 'Unable to open job store: ' . $e->getMessage() . "\n");
    exit(2);
}

// withLockNonBlocking: if a previous tick of the same recurring job is still
// running when the next cron tick fires, skip this tick instead of stacking
// up overlapping executions.
$handled = $store->withLockNonBlocking($jobId, function () use ($store, $jobId): bool {
    $job = $store->load($jobId);

    if ($job === null) {
        fwrite(STDERR, "Job {$jobId} not found in store {$store->dir()}\n");
        return false;
    }

    if ($job->status === Job::STATUS_RUNNING) {
        // Shouldn't normally happen since we hold the lock, but guards against
        // a stale "running" record left over from a killed process.
        return true;
    }

    if ($job->status === Job::STATUS_CANCELLED) {
        return true;
    }

    $cmd = new CMD();

    $job->status = Job::STATUS_RUNNING;
    $job->pid = getmypid();
    $job->startedAt = microtime(true);
    $job->exitCode = null;
    $job->attempts++;
    $store->save($job);

    $result = $cmd->run($job->command, $job->cwd);

    $job->exitCode = $result['code'];
    $job->stdout = jobbatch_truncate($result['stdout']);
    $job->stderr = jobbatch_truncate($result['stderr']);
    $job->finishedAt = microtime(true);

    if ($result['code'] === 0) {
        $job->status = Job::STATUS_SUCCESS;
    } elseif ($job->attempts < $job->maxAttempts) {
        // Left as "pending" so a manual or scheduled re-dispatch will retry it.
        $job->status = Job::STATUS_PENDING;
    } else {
        $job->status = Job::STATUS_FAILED;
    }

    $store->save($job);

    return true;
});

exit($handled ? 0 : 1);
