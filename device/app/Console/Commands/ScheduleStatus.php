<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;

class ScheduleStatus extends Command
{
    protected $signature = 'device:schedule-status
        {--json : Output in JSON format}
        {--format=table : Output format (table, json)}';

    protected $description = 'View status of scheduled tasks';

    public function handle(Schedule $schedule): int
    {
        $tasks = $this->collectTaskStatus($schedule);

        if ($this->option('json') || $this->option('format') === 'json') {
            $this->outputJson($tasks);
        } else {
            $this->outputTable($tasks);
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectTaskStatus(Schedule $schedule): array
    {
        $events = $schedule->events();
        $tasks = [];

        foreach ($events as $event) {
            $taskName = $this->getTaskName($event);
            $lastRunKey = "schedule:last_run:{$taskName}";
            $lastRun = Cache::get($lastRunKey);
            $nextRun = $event->nextRunDate();
            $expression = $event->expression;

            $tasks[] = [
                'name' => $taskName,
                'description' => $event->description ?? 'N/A',
                'expression' => $expression,
                'frequency' => $this->getHumanReadableFrequency($expression),
                'last_run' => $lastRun,
                'last_run_human' => $lastRun ? $this->formatLastRun($lastRun) : 'Never',
                'next_run' => $nextRun->toDateTimeString(),
                'next_run_human' => $nextRun->diffForHumans(),
                'status' => $this->getTaskStatus($lastRun, $nextRun, $expression),
                'overdue' => $this->isOverdue($lastRun, $nextRun),
                'runs_in_background' => $event->runInBackground,
                'without_overlapping' => $event->withoutOverlapping,
            ];
        }

        return $tasks;
    }

    private function getTaskName(Event $event): string
    {
        if (isset($event->description)) {
            return $event->description;
        }

        if ($event->command) {
            return $this->getCommandName($event->command);
        }

        return 'Closure';
    }

    private function getCommandName(string $command): string
    {
        $parts = explode(' ', $command);

        foreach ($parts as $part) {
            if (str_starts_with($part, 'artisan')) {
                continue;
            }

            if (str_starts_with($part, '--')) {
                continue;
            }

            if (str_starts_with($part, '>')) {
                break;
            }

            return $part;
        }

        return $command;
    }

    private function getHumanReadableFrequency(string $expression): string
    {
        $parts = explode(' ', $expression);

        return match ($expression) {
            '* * * * *' => 'Every minute',
            '*/5 * * * *' => 'Every 5 minutes',
            '*/3 * * * *' => 'Every 3 minutes',
            '0 * * * *' => 'Hourly',
            '0 0 * * *' => 'Daily at midnight',
            '0 2 * * *' => 'Daily at 02:00',
            '0 * * * 0' => 'Weekly',
            '0 0 1 * *' => 'Monthly',
            default => $this->parseCronExpression($parts),
        };
    }

    /**
     * @param  array<int, string>  $parts
     */
    private function parseCronExpression(array $parts): string
    {
        if (count($parts) !== 5) {
            return $parts[0].' '.$parts[1].' '.$parts[2].' '.$parts[3].' '.$parts[4];
        }

        [$minute, $hour, $day, $month, $weekday] = $parts;

        $desc = [];

        if ($minute === '*' && $hour === '*') {
            $desc[] = 'Every minute';
        } elseif (str_starts_with($minute, '*/')) {
            $interval = substr($minute, 2);
            $desc[] = "Every {$interval} minutes";
        } elseif ($minute !== '*' && $hour === '*') {
            $desc[] = "Every hour at minute {$minute}";
        } elseif ($minute !== '*' && $hour !== '*') {
            $desc[] = "At {$hour}:{$minute}";
        }

        if ($day !== '*') {
            $desc[] = "on day {$day}";
        }

        if ($month !== '*') {
            $desc[] = "in month {$month}";
        }

        if ($weekday !== '*') {
            $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            $desc[] = 'on '.($days[$weekday] ?? $weekday);
        }

        return implode(' ', $desc);
    }

    private function getTaskStatus(?string $lastRun, \DateTimeInterface $nextRun, string $expression): string
    {
        if ($lastRun === null) {
            return 'pending';
        }

        $lastRunTime = \Carbon\Carbon::parse($lastRun);
        $now = now();

        if ($lastRunTime->greaterThan($nextRun)) {
            return 'running';
        }

        if ($this->isOverdue($lastRun, $nextRun)) {
            return 'overdue';
        }

        return 'ok';
    }

    private function isOverdue(?string $lastRun, \DateTimeInterface $nextRun): bool
    {
        if ($lastRun === null) {
            return false;
        }

        $lastRunTime = \Carbon\Carbon::parse($lastRun);
        $now = now();

        return $nextRun < $now && $lastRunTime < $nextRun;
    }

    private function formatLastRun(string $lastRun): string
    {
        return \Carbon\Carbon::parse($lastRun)->diffForHumans();
    }

    /**
     * @param  array<int, array<string, mixed>>  $tasks
     */
    private function outputTable(array $tasks): void
    {
        $this->newLine();
        $this->info('╔════════════════════════════════════════════════════════════╗');
        $this->info('║ Scheduled Task Status                                      ║');
        $this->info('╚════════════════════════════════════════════════════════════╝');
        $this->newLine();

        if (empty($tasks)) {
            $this->warn('No scheduled tasks found.');

            return;
        }

        $overdueCount = count(array_filter($tasks, fn ($t) => $t['overdue']));
        $runningCount = count(array_filter($tasks, fn ($t) => $t['status'] === 'running'));
        $okCount = count(array_filter($tasks, fn ($t) => $t['status'] === 'ok'));
        $pendingCount = count(array_filter($tasks, fn ($t) => $t['status'] === 'pending'));

        $this->info('─ Summary ──────────────────────────────────────────────────');
        $this->twoColumnTable([
            ['Total Tasks', (string) count($tasks)],
            ['OK', "🟢 {$okCount}"],
            ['Running', "🔵 {$runningCount}"],
            ['Pending', "⚪ {$pendingCount}"],
            ['Overdue', $overdueCount > 0 ? "🔴 {$overdueCount}" : '🟢 0'],
        ]);

        $this->newLine();
        $this->info('─ Task Details ─────────────────────────────────────────────');
        $this->newLine();

        foreach ($tasks as $task) {
            $this->displayTask($task);
        }

        $this->newLine();
        $this->info(sprintf('Report generated: %s', now()->toIso8601String()));
        $this->newLine();
    }

    /**
     * @param  array<string, mixed>  $task
     */
    private function displayTask(array $task): void
    {
        $statusIcon = match ($task['status']) {
            'ok' => '🟢',
            'running' => '🔵',
            'overdue' => '🔴',
            'pending' => '⚪',
            default => '⚪',
        };

        $this->info("{$statusIcon} {$task['name']}");
        $this->twoColumnTable([
            ['  Frequency', $task['frequency']],
            ['  Expression', $task['expression']],
            ['  Last Run', $task['last_run_human']],
            ['  Next Run', $task['next_run_human']],
            ['  Background', $task['runs_in_background'] ? 'Yes' : 'No'],
            ['  No Overlap', $task['without_overlapping'] ? 'Yes' : 'No'],
        ]);
        $this->newLine();
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $rows
     */
    private function twoColumnTable(array $rows): void
    {
        $maxLabelWidth = 0;
        foreach ($rows as $row) {
            $maxLabelWidth = max($maxLabelWidth, strlen($row[0]));
        }

        foreach ($rows as $row) {
            $label = str_pad($row[0], $maxLabelWidth, ' ', STR_PAD_RIGHT);
            $this->line("  {$label} : {$row[1]}");
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $tasks
     */
    private function outputJson(array $tasks): void
    {
        $this->line(json_encode([
            'tasks' => $tasks,
            'generated_at' => now()->toIso8601String(),
            'summary' => [
                'total' => count($tasks),
                'ok' => count(array_filter($tasks, fn ($t) => $t['status'] === 'ok')),
                'running' => count(array_filter($tasks, fn ($t) => $t['status'] === 'running')),
                'pending' => count(array_filter($tasks, fn ($t) => $t['status'] === 'pending')),
                'overdue' => count(array_filter($tasks, fn ($t) => $t['overdue'])),
            ],
        ], JSON_PRETTY_PRINT));
    }
}
