<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ScheduledTaskMonitorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckHeartbeats extends Command
{
    protected $signature = 'device:check-heartbeats
        {--task=device-heartbeat : The task name to check}
        {--json : Output in JSON format}
        {--alert : Send alert if heartbeats are missed}';

    protected $description = 'Check for missed scheduled task runs and report status';

    public function handle(ScheduledTaskMonitorService $monitor): int
    {
        $taskName = $this->option('task');

        // Detect missed runs
        $missedRuns = $monitor->detectMissedRuns($taskName);

        // Get health status
        $isHealthy = $monitor->isHeartbeatHealthy($taskName);
        $lastRun = $monitor->getLastRun($taskName);
        $allMissedRuns = $monitor->getMissedRuns();
        $taskMissedRuns = array_filter(
            $allMissedRuns,
            fn ($run) => $run['task'] === $taskName
        );

        // Alert if needed
        if ($this->option('alert') && count($missedRuns) > 0) {
            Log::warning('Heartbeat monitoring alert: missed runs detected', [
                'task' => $taskName,
                'missed_count' => count($missedRuns),
                'last_run' => $lastRun,
            ]);

            $this->sendAlert($taskName, $missedRuns, $lastRun);
        }

        // Output results
        if ($this->option('json')) {
            $this->outputJson($taskName, $isHealthy, $lastRun, $missedRuns, $taskMissedRuns);
        } else {
            $this->outputTable($taskName, $isHealthy, $lastRun, $missedRuns, $taskMissedRuns);
        }

        return $isHealthy ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Send alert for missed runs.
     *
     * @param  array<int, array{expected_at: string, detected_at: string}>  $missedRuns
     * @param  array{completed_at: string, duration_ms: ?int, status: string}|null  $lastRun
     */
    private function sendAlert(string $taskName, array $missedRuns, ?array $lastRun): void
    {
        $lastRunTime = $lastRun ? $lastRun['completed_at'] : 'never';
        $missedCount = count($missedRuns);

        Log::alert('Scheduled task has missed runs', [
            'task' => $taskName,
            'missed_runs_count' => $missedCount,
            'last_successful_run' => $lastRunTime,
            'action_required' => 'Check scheduler and task health',
        ]);
    }

    /**
     * Output results in JSON format.
     *
     * @param  array<int, array{expected_at: string, detected_at: string}>  $missedRuns
     * @param  array<int, array{task: string, expected_at: string, detected_at: string}>  $allTaskMissedRuns
     * @param  array{completed_at: string, duration_ms: ?int, status: string}|null  $lastRun
     */
    private function outputJson(
        string $taskName,
        bool $isHealthy,
        ?array $lastRun,
        array $missedRuns,
        array $allTaskMissedRuns
    ): void {
        $this->line(json_encode([
            'task' => $taskName,
            'healthy' => $isHealthy,
            'last_run' => $lastRun,
            'newly_detected_missed_runs' => $missedRuns,
            'total_missed_runs' => count($allTaskMissedRuns),
            'checked_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT));
    }

    /**
     * Output results in table format.
     *
     * @param  array<int, array{expected_at: string, detected_at: string}>  $missedRuns
     * @param  array<int, array{task: string, expected_at: string, detected_at: string}>  $allTaskMissedRuns
     * @param  array{completed_at: string, duration_ms: ?int, status: string}|null  $lastRun
     */
    private function outputTable(
        string $taskName,
        bool $isHealthy,
        ?array $lastRun,
        array $missedRuns,
        array $allTaskMissedRuns
    ): void {
        $this->newLine();
        $this->info('╔════════════════════════════════════════════════════════════╗');
        $this->info('║           Scheduled Task Heartbeat Monitor                 ║');
        $this->info('╚════════════════════════════════════════════════════════════╝');
        $this->newLine();

        // Health Status
        $statusIcon = $isHealthy ? '🟢' : '🔴';
        $statusText = $isHealthy ? 'HEALTHY' : 'UNHEALTHY';

        $this->info("{$statusIcon} Task: {$taskName} [{$statusText}]");
        $this->newLine();

        // Last Run Info
        $this->info('─ Last Run Information ───────────────────────────────────────');
        if ($lastRun) {
            $lastRunTime = \Carbon\Carbon::parse($lastRun['completed_at']);
            $this->line("  Status        : {$lastRun['status']}");
            $this->line("  Completed At  : {$lastRunTime->toDateTimeString()} ({$lastRunTime->diffForHumans()})");
            if (isset($lastRun['duration_ms']) && $lastRun['duration_ms'] !== null) {
                $this->line("  Duration      : {$lastRun['duration_ms']}ms");
            }
        } else {
            $this->warn('  No run recorded');
        }
        $this->newLine();

        // Missed Runs Summary
        $this->info('─ Missed Runs Summary ──────────────────────────────────────');
        $totalMissed = count($allTaskMissedRuns);
        $newlyDetected = count($missedRuns);

        if ($totalMissed === 0) {
            $this->info('  🟢 No missed runs detected');
        } else {
            $this->warn("  🔴 Total missed runs: {$totalMissed}");
            if ($newlyDetected > 0) {
                $this->warn("  🔴 Newly detected: {$newlyDetected}");
            }
        }
        $this->newLine();

        // Detailed missed runs
        if ($totalMissed > 0) {
            $this->info('─ Recent Missed Runs ───────────────────────────────────────');
            $recentRuns = array_slice(array_reverse($allTaskMissedRuns), 0, 5);

            foreach ($recentRuns as $run) {
                $expectedAt = \Carbon\Carbon::parse($run['expected_at']);
                $this->warn("  • Expected: {$expectedAt->toDateTimeString()} ({$expectedAt->diffForHumans()})");
            }
            $this->newLine();
        }

        // Recommendations
        if (! $isHealthy) {
            $this->error('─ Recommendations ──────────────────────────────────────────');
            $this->line('  1. Verify the scheduler is running: php artisan schedule:work');
            $this->line('  2. Check task logs for errors');
            $this->line('  3. Ensure system time is synchronized');
            $this->line('  4. Review task execution time (may be too long)');
            $this->newLine();
        }

        $this->info(sprintf('Checked at: %s', now()->toIso8601String()));
        $this->newLine();
    }
}
