<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ScheduledTaskMonitorService
{
    private const string CACHE_PREFIX = 'schedule:monitor:';

    private const string MISSED_RUNS_CACHE_KEY = 'schedule:missed_runs';

    private const int DEFAULT_HEARTBEAT_INTERVAL_MINUTES = 3;

    private const int MISSED_RUN_THRESHOLD_MINUTES = 5;

    /**
     * Record that a task has started running.
     */
    public function recordTaskStart(string $taskName): void
    {
        $key = self::CACHE_PREFIX.'start:'.$taskName;
        Cache::put($key, now()->toIso8601String(), now()->addHours(24));

        Log::debug('Scheduled task started', ['task' => $taskName]);
    }

    /**
     * Record that a task has completed successfully.
     */
    public function recordTaskComplete(string $taskName, ?int $durationMs = null): void
    {
        $key = self::CACHE_PREFIX.'last_run:'.$taskName;
        $runData = [
            'completed_at' => now()->toIso8601String(),
            'duration_ms' => $durationMs,
            'status' => 'completed',
        ];

        Cache::put($key, $runData, now()->addHours(24));

        Log::debug('Scheduled task completed', [
            'task' => $taskName,
            'duration_ms' => $durationMs,
        ]);
    }

    /**
     * Record that a task has failed.
     */
    public function recordTaskFailure(string $taskName, \Throwable $exception): void
    {
        $key = self::CACHE_PREFIX.'last_run:'.$taskName;
        $runData = [
            'completed_at' => now()->toIso8601String(),
            'status' => 'failed',
            'error' => $exception->getMessage(),
        ];

        Cache::put($key, $runData, now()->addHours(24));

        Log::error('Scheduled task failed', [
            'task' => $taskName,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Record a missed heartbeat run.
     */
    public function recordMissedRun(string $taskName, Carbon $expectedRunTime): void
    {
        $missedRuns = Cache::get(self::MISSED_RUNS_CACHE_KEY, []);
        $missedRuns[] = [
            'task' => $taskName,
            'expected_at' => $expectedRunTime->toIso8601String(),
            'detected_at' => now()->toIso8601String(),
        ];

        // Keep only last 100 missed runs
        $missedRuns = array_slice($missedRuns, -100);

        Cache::put(self::MISSED_RUNS_CACHE_KEY, $missedRuns, now()->addDays(7));

        Log::warning('Scheduled task missed expected run time', [
            'task' => $taskName,
            'expected_at' => $expectedRunTime->toIso8601String(),
        ]);
    }

    /**
     * Get the last run data for a task.
     *
     * @return array{completed_at: string, duration_ms: ?int, status: string, error?: string}|null
     */
    public function getLastRun(string $taskName): ?array
    {
        $key = self::CACHE_PREFIX.'last_run:'.$taskName;

        return Cache::get($key);
    }

    /**
     * Check if heartbeat is healthy (has run recently).
     */
    public function isHeartbeatHealthy(string $taskName = 'device-heartbeat'): bool
    {
        $lastRun = $this->getLastRun($taskName);

        if ($lastRun === null) {
            return false;
        }

        $lastRunTime = Carbon::parse($lastRun['completed_at']);
        $threshold = now()->subMinutes(self::MISSED_RUN_THRESHOLD_MINUTES);

        return $lastRunTime->greaterThan($threshold);
    }

    /**
     * Get the time since last heartbeat.
     */
    public function getTimeSinceLastHeartbeat(string $taskName = 'device-heartbeat'): ?Carbon
    {
        $lastRun = $this->getLastRun($taskName);

        if ($lastRun === null) {
            return null;
        }

        return Carbon::parse($lastRun['completed_at']);
    }

    /**
     * Detect missed runs for a task.
     *
     * @return array<int, array{expected_at: string, detected_at: string}>
     */
    public function detectMissedRuns(string $taskName = 'device-heartbeat', int $intervalMinutes = self::DEFAULT_HEARTBEAT_INTERVAL_MINUTES): array
    {
        $lastRun = $this->getLastRun($taskName);
        $missedRuns = [];

        if ($lastRun === null) {
            return $missedRuns;
        }

        $lastRunTime = Carbon::parse($lastRun['completed_at']);
        $now = now();
        $expectedRuns = [];

        // Calculate expected run times since last actual run
        $nextExpected = $lastRunTime->copy()->addMinutes($intervalMinutes);

        while ($nextExpected->lessThan($now)) {
            $expectedRuns[] = $nextExpected->copy();
            $nextExpected->addMinutes($intervalMinutes);
        }

        // Record missed runs
        foreach ($expectedRuns as $expectedTime) {
            $this->recordMissedRun($taskName, $expectedTime);
            $missedRuns[] = [
                'expected_at' => $expectedTime->toIso8601String(),
                'detected_at' => now()->toIso8601String(),
            ];
        }

        return $missedRuns;
    }

    /**
     * Get all missed runs.
     *
     * @return array<int, array{task: string, expected_at: string, detected_at: string}>
     */
    public function getMissedRuns(): array
    {
        return Cache::get(self::MISSED_RUNS_CACHE_KEY, []);
    }

    /**
     * Get health status for all monitored tasks.
     *
     * @return array<string, array{last_run: ?array, healthy: bool, missed_count: int}>
     */
    public function getHealthStatus(): array
    {
        $tasks = ['device-heartbeat', 'device-pairing-poll', 'device-tunnel-status-poll', 'cleanup-abandoned-projects'];
        $status = [];

        foreach ($tasks as $task) {
            $lastRun = $this->getLastRun($task);
            $missedRuns = array_filter(
                $this->getMissedRuns(),
                fn ($run) => $run['task'] === $task
            );

            $status[$task] = [
                'last_run' => $lastRun,
                'healthy' => $this->isHeartbeatHealthy($task),
                'missed_count' => count($missedRuns),
            ];
        }

        return $status;
    }

    /**
     * Clear all monitoring data.
     */
    public function clearMonitoringData(): void
    {
        Cache::forget(self::MISSED_RUNS_CACHE_KEY);

        // Clear all task-specific keys
        $tasks = ['device-heartbeat', 'device-pairing-poll', 'device-tunnel-status-poll', 'cleanup-abandoned-projects'];
        foreach ($tasks as $task) {
            Cache::forget(self::CACHE_PREFIX.'last_run:'.$task);
            Cache::forget(self::CACHE_PREFIX.'start:'.$task);
        }
    }
}
