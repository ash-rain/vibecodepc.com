<?php

use App\Models\CloudCredential;
use App\Models\Project;
use App\Services\CloudApiClient;
use App\Services\ConfigSyncService;
use App\Services\DeviceHealthService;
use App\Services\Tunnel\TunnelService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
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

    app(CloudApiClient::class)->sendHeartbeat($deviceId, $metrics);

    app(ConfigSyncService::class)->syncIfNeeded($deviceId);
})->everyThreeMinutes()->name('device-heartbeat');
