<?php

declare(strict_types=1);

use App\Services\ScheduledTaskMonitorService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

afterEach(function () {
    Cache::flush();
});

it('records task start', function () {
    $service = new ScheduledTaskMonitorService;

    $service->recordTaskStart('device-heartbeat');

    $key = 'schedule:monitor:start:device-heartbeat';
    expect(Cache::has($key))->toBeTrue();
    expect(Cache::get($key))->toBeString();
});

it('records task completion with duration', function () {
    $service = new ScheduledTaskMonitorService;

    $service->recordTaskComplete('device-heartbeat', 1500);

    $lastRun = $service->getLastRun('device-heartbeat');
    expect($lastRun)->not->toBeNull();
    expect($lastRun['status'])->toBe('completed');
    expect($lastRun['duration_ms'])->toBe(1500);
});

it('records task failure', function () {
    $service = new ScheduledTaskMonitorService;
    $exception = new \RuntimeException('Task failed');

    $service->recordTaskFailure('device-heartbeat', $exception);

    $lastRun = $service->getLastRun('device-heartbeat');
    expect($lastRun)->not->toBeNull();
    expect($lastRun['status'])->toBe('failed');
    expect($lastRun['error'])->toBe('Task failed');
});

it('records missed runs', function () {
    $service = new ScheduledTaskMonitorService;
    $expectedTime = Carbon::now()->subMinutes(10);

    $service->recordMissedRun('device-heartbeat', $expectedTime);

    $missedRuns = $service->getMissedRuns();
    expect($missedRuns)->toHaveCount(1);
    expect($missedRuns[0]['task'])->toBe('device-heartbeat');
    expect($missedRuns[0]['expected_at'])->toBe($expectedTime->toIso8601String());
});

it('limits missed runs to last 100 entries', function () {
    $service = new ScheduledTaskMonitorService;

    // Record 110 missed runs
    for ($i = 0; $i < 110; $i++) {
        $service->recordMissedRun('device-heartbeat', Carbon::now()->subMinutes($i));
    }

    $missedRuns = $service->getMissedRuns();
    expect($missedRuns)->toHaveCount(100);
});

it('detects healthy heartbeat when run recently', function () {
    $service = new ScheduledTaskMonitorService;

    $service->recordTaskComplete('device-heartbeat', 1000);

    expect($service->isHeartbeatHealthy('device-heartbeat'))->toBeTrue();
});

it('detects unhealthy heartbeat when not run recently', function () {
    $service = new ScheduledTaskMonitorService;

    // Manually set an old last run
    Cache::put('schedule:monitor:last_run:device-heartbeat', [
        'completed_at' => Carbon::now()->subMinutes(10)->toIso8601String(),
        'status' => 'completed',
    ], now()->addHours(24));

    expect($service->isHeartbeatHealthy('device-heartbeat'))->toBeFalse();
});

it('returns null for last run when no runs recorded', function () {
    $service = new ScheduledTaskMonitorService;

    expect($service->getLastRun('device-heartbeat'))->toBeNull();
});

it('returns null for time since last heartbeat when no runs recorded', function () {
    $service = new ScheduledTaskMonitorService;

    expect($service->getTimeSinceLastHeartbeat('device-heartbeat'))->toBeNull();
});

it('detects missed runs based on interval', function () {
    $service = new ScheduledTaskMonitorService;

    // Set last run to 10 minutes ago
    Cache::put('schedule:monitor:last_run:device-heartbeat', [
        'completed_at' => Carbon::now()->subMinutes(10)->toIso8601String(),
        'status' => 'completed',
    ], now()->addHours(24));

    // With 3 minute interval, should detect 2-3 missed runs
    $missedRuns = $service->detectMissedRuns('device-heartbeat', 3);

    expect($missedRuns)->not->toBeEmpty();
});

it('returns health status for all tasks', function () {
    $service = new ScheduledTaskMonitorService;

    // Record some runs
    $service->recordTaskComplete('device-heartbeat', 1000);
    $service->recordTaskComplete('device-pairing-poll', 500);

    $status = $service->getHealthStatus();

    expect($status)->toHaveKeys([
        'device-heartbeat',
        'device-pairing-poll',
        'device-tunnel-status-poll',
        'cleanup-abandoned-projects',
    ]);
    expect($status['device-heartbeat']['healthy'])->toBeTrue();
});

it('clears monitoring data', function () {
    $service = new ScheduledTaskMonitorService;

    $service->recordTaskComplete('device-heartbeat', 1000);
    $service->recordMissedRun('device-heartbeat', Carbon::now());

    $service->clearMonitoringData();

    expect($service->getLastRun('device-heartbeat'))->toBeNull();
    expect($service->getMissedRuns())->toBeEmpty();
});

it('filters missed runs by task', function () {
    $service = new ScheduledTaskMonitorService;

    $service->recordMissedRun('device-heartbeat', Carbon::now()->subMinutes(5));
    $service->recordMissedRun('device-pairing-poll', Carbon::now()->subMinutes(3));

    $missedRuns = $service->getMissedRuns();
    expect($missedRuns)->toHaveCount(2);

    $heartbeatMissed = array_filter($missedRuns, fn ($run) => $run['task'] === 'device-heartbeat');
    expect($heartbeatMissed)->toHaveCount(1);
});
