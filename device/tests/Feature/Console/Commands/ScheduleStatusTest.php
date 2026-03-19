<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    // Clear any cached schedule run times
    Cache::flush();
});

it('displays scheduled tasks in table format', function () {
    $this->artisan('device:schedule-status')
        ->assertSuccessful()
        ->expectsOutputToContain('Scheduled Task Status')
        ->expectsOutputToContain('Summary')
        ->expectsOutputToContain('Task Details');
});

it('outputs tasks in JSON format', function () {
    $this->artisan('device:schedule-status', ['--json' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('tasks');
});

it('shows all scheduled tasks', function () {
    $this->artisan('device:schedule-status')
        ->assertSuccessful()
        ->expectsOutputToContain('device-pairing-poll')
        ->expectsOutputToContain('device-tunnel-status-poll')
        ->expectsOutputToContain('device-heartbeat')
        ->expectsOutputToContain('cleanup-abandoned-projects');
});

it('shows task frequencies', function () {
    $this->artisan('device:schedule-status')
        ->assertSuccessful()
        ->expectsOutputToContain('Every minute')
        ->expectsOutputToContain('Every 3 minutes')
        ->expectsOutputToContain('Daily at 02:00');
});

it('shows cron expressions', function () {
    $this->artisan('device:schedule-status')
        ->assertSuccessful()
        ->expectsOutputToContain('* * * * *')
        ->expectsOutputToContain('*/3 * * * *')
        ->expectsOutputToContain('0 2 * * *');
});

it('shows pending status for tasks that have never run', function () {
    $this->artisan('device:schedule-status')
        ->assertSuccessful()
        ->expectsOutputToContain('Pending');
});

it('shows next run times', function () {
    $this->artisan('device:schedule-status')
        ->assertSuccessful()
        ->expectsOutputToContain('Next Run');
});

it('shows without overlapping configuration', function () {
    $this->artisan('device:schedule-status')
        ->assertSuccessful()
        ->expectsOutputToContain('No Overlap');
});

it('accepts format option', function () {
    $this->artisan('device:schedule-status', ['--format' => 'json'])
        ->assertSuccessful();
});

it('displays summary counts', function () {
    $this->artisan('device:schedule-status')
        ->assertSuccessful()
        ->expectsOutputToContain('Total Tasks')
        ->expectsOutputToContain('OK')
        ->expectsOutputToContain('Pending');
});

it('shows report generation timestamp', function () {
    $this->artisan('device:schedule-status')
        ->assertSuccessful()
        ->expectsOutputToContain('Report generated');
});
