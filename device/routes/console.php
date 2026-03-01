<?php

use App\Models\CloudCredential;
use App\Models\Project;
use App\Models\QuickTunnel;
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

// Poll cloud for pairing claim every 5 seconds (no-op once paired)
Schedule::command('device:poll-pairing')
    ->everyFiveSeconds()
    ->withoutOverlapping()
    ->name('device-pairing-poll');

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

    app(CloudApiClient::class)->sendHeartbeat($deviceId, $metrics);

    app(ConfigSyncService::class)->syncIfNeeded($deviceId);
})->everyThreeMinutes()->name('device-heartbeat');
