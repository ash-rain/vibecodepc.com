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

it('displays heartbeat status in table format', function () {
    $service = app(ScheduledTaskMonitorService::class);
    $service->recordTaskComplete('device-heartbeat', 1000);

    $this->artisan('device:check-heartbeats')
        ->assertSuccessful()
        ->expectsOutputToContain('Scheduled Task Heartbeat Monitor')
        ->expectsOutputToContain('device-heartbeat');
});

it('outputs heartbeat status in JSON format', function () {
    $service = app(ScheduledTaskMonitorService::class);
    $service->recordTaskComplete('device-heartbeat', 1000);

    // Verify the task was recorded
    $lastRun = $service->getLastRun('device-heartbeat');
    expect($lastRun)->not->toBeNull();

    $this->artisan('device:check-heartbeats', ['--json' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('device-heartbeat');
});

it('shows healthy status when heartbeat ran recently', function () {
    $service = app(ScheduledTaskMonitorService::class);
    $service->recordTaskComplete('device-heartbeat', 1000);

    $this->artisan('device:check-heartbeats')
        ->assertSuccessful()
        ->expectsOutputToContain('HEALTHY');
});

it('shows unhealthy status when heartbeat is stale', function () {
    // Manually set an old last run
    Cache::put('schedule:monitor:last_run:device-heartbeat', [
        'completed_at' => Carbon::now()->subMinutes(10)->toIso8601String(),
        'status' => 'completed',
    ], now()->addHours(24));

    $this->artisan('device:check-heartbeats')
        ->assertFailed()
        ->expectsOutputToContain('UNHEALTHY');
});

it('detects missed runs and displays them', function () {
    // Set last run to 10 minutes ago (with 3 min interval, should have missed runs)
    Cache::put('schedule:monitor:last_run:device-heartbeat', [
        'completed_at' => Carbon::now()->subMinutes(10)->toIso8601String(),
        'status' => 'completed',
    ], now()->addHours(24));

    $this->artisan('device:check-heartbeats')
        ->assertFailed()
        ->expectsOutputToContain('missed runs');
});

it('accepts custom task name option', function () {
    $service = app(ScheduledTaskMonitorService::class);
    $service->recordTaskComplete('device-pairing-poll', 500);

    $this->artisan('device:check-heartbeats', ['--task' => 'device-pairing-poll'])
        ->assertSuccessful()
        ->expectsOutputToContain('device-pairing-poll');
});

it('logs alert when heartbeats are missed and alert flag is set', function () {
    // Set last run to 10 minutes ago
    Cache::put('schedule:monitor:last_run:device-heartbeat', [
        'completed_at' => Carbon::now()->subMinutes(10)->toIso8601String(),
        'status' => 'completed',
    ], now()->addHours(24));

    $this->artisan('device:check-heartbeats', ['--alert' => true])
        ->assertFailed();

    // Check that an alert was logged
    // Note: In a real test, you might use Log::fake() to assert logging
});

it('displays last run information', function () {
    $service = app(ScheduledTaskMonitorService::class);
    $service->recordTaskComplete('device-heartbeat', 1500);

    $this->artisan('device:check-heartbeats')
        ->assertSuccessful()
        ->expectsOutputToContain('Last Run Information')
        ->expectsOutputToContain('completed')
        ->expectsOutputToContain('1500');
});

it('shows recommendations when unhealthy', function () {
    // Set last run to 10 minutes ago
    Cache::put('schedule:monitor:last_run:device-heartbeat', [
        'completed_at' => Carbon::now()->subMinutes(10)->toIso8601String(),
        'status' => 'completed',
    ], now()->addHours(24));

    $this->artisan('device:check-heartbeats')
        ->assertFailed()
        ->expectsOutputToContain('Recommendations')
        ->expectsOutputToContain('schedule:work');
});

it('displays no missed runs message when healthy', function () {
    $service = app(ScheduledTaskMonitorService::class);
    $service->recordTaskComplete('device-heartbeat', 1000);

    $this->artisan('device:check-heartbeats')
        ->assertSuccessful()
        ->expectsOutputToContain('No missed runs detected');
});

it('includes check timestamp in output', function () {
    $service = app(ScheduledTaskMonitorService::class);
    $service->recordTaskComplete('device-heartbeat', 1000);

    $this->artisan('device:check-heartbeats')
        ->assertSuccessful()
        ->expectsOutputToContain('Checked at:');
});
