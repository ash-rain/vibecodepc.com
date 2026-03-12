<?php

use App\Models\CloudCredential;
use App\Models\Project;
use App\Models\QuickTunnel;
use App\Services\AnalyticsService;
use App\Services\CloudApiClient;
use App\Services\ConfigSyncService;
use App\Services\DeviceHealthService;
use App\Services\ScheduledTaskMonitorService;
use App\Services\Tunnel\TunnelService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Poll cloud for pairing claim every 5 seconds (no-op once paired)
Schedule::command('device:poll-pairing')
    ->everyFiveSeconds()
    ->withoutOverlapping()
    ->name('device-pairing-poll')
    ->before(function () {
        app(ScheduledTaskMonitorService::class)->recordTaskStart('device-pairing-poll');
    })
    ->after(function () {
        app(ScheduledTaskMonitorService::class)->recordTaskComplete('device-pairing-poll');
    });

// Poll for tunnel token file appearance when tunnel was skipped
Schedule::command('device:poll-tunnel-status')
    ->everyMinute()
    ->withoutOverlapping()
    ->name('device-tunnel-status-poll')
    ->before(function () {
        app(ScheduledTaskMonitorService::class)->recordTaskStart('device-tunnel-status-poll');
    })
    ->after(function () {
        app(ScheduledTaskMonitorService::class)->recordTaskComplete('device-tunnel-status-poll');
    });

Schedule::call(function () {
    $startTime = microtime(true);
    $credential = CloudCredential::current();

    if (! $credential || ! $credential->isPaired()) {
        return;
    }

    $deviceJsonPath = config('vibecodepc.device_json_path');
    $deviceJson = file_exists($deviceJsonPath)
        ? json_decode(file_get_contents($deviceJsonPath), true)
        : [];

    $deviceId = $deviceJson['id'] ?? null;

    if (! $deviceId) {
        Log::warning('Heartbeat skipped: no device ID in device.json');

        return;
    }

    $metrics = app(DeviceHealthService::class)->getMetrics();

    $metrics['running_projects'] = Project::running()->count();
    $metrics['tunnel_active'] = app(TunnelService::class)->isRunning();
    $metrics['firmware_version'] = $deviceJson['firmware_version'] ?? 'unknown';

    $activeQuickTunnels = QuickTunnel::whereIn('status', ['starting', 'running'])->get();

    if ($activeQuickTunnels->isNotEmpty()) {
        $metrics['quick_tunnels'] = $activeQuickTunnels->map(fn (QuickTunnel $qt) => [
            'tunnel_url' => $qt->tunnel_url,
            'local_port' => $qt->local_port,
            'project_name' => $qt->project?->name,
            'status' => $qt->status,
            'started_at' => $qt->started_at?->toIso8601String(),
        ])->all();
    }

    // Include analytics events data
    $analytics = app(AnalyticsService::class);
    $metrics['analytics'] = $analytics->getAggregatedData();

    app(CloudApiClient::class)->sendHeartbeat($deviceId, $metrics);

    app(ConfigSyncService::class)->syncIfNeeded($deviceId);

    // Record completion with duration
    $durationMs = (int) ((microtime(true) - $startTime) * 1000);
    app(ScheduledTaskMonitorService::class)->recordTaskComplete('device-heartbeat', $durationMs);
})
    ->everyThreeMinutes()
    ->name('device-heartbeat')
    ->before(function () {
        app(ScheduledTaskMonitorService::class)->recordTaskStart('device-heartbeat');
    })
    ->onFailure(function () {
        app(ScheduledTaskMonitorService::class)->recordTaskFailure('device-heartbeat', new \RuntimeException('Heartbeat task failed'));
    });

// Cleanup abandoned and errored projects daily
Schedule::job(App\Jobs\CleanupAbandonedProjectsJob::class)
    ->daily()
    ->at('02:00')
    ->withoutOverlapping()
    ->name('cleanup-abandoned-projects')
    ->before(function () {
        app(ScheduledTaskMonitorService::class)->recordTaskStart('cleanup-abandoned-projects');
    })
    ->after(function () {
        app(ScheduledTaskMonitorService::class)->recordTaskComplete('cleanup-abandoned-projects');
    });

// Monitor scheduled tasks for missed heartbeat runs
Schedule::command('device:check-heartbeats --alert')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->name('scheduled-task-monitor')
    ->before(function () {
        app(ScheduledTaskMonitorService::class)->recordTaskStart('scheduled-task-monitor');
    })
    ->after(function () {
        app(ScheduledTaskMonitorService::class)->recordTaskComplete('scheduled-task-monitor');
    });
