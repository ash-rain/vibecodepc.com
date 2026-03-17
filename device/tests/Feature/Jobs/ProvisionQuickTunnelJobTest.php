<?php

use App\Jobs\ProvisionQuickTunnelJob;
use App\Models\TunnelConfig;
use App\Services\DeviceRegistry\DeviceIdentityService;
use App\Services\Tunnel\QuickTunnelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function setupIdentityForJob(string $uuid): void
{
    $deviceJson = json_encode([
        'id' => $uuid,
        'hardware_serial' => 'test-serial',
        'manufactured_at' => '2026-01-01T00:00:00Z',
        'firmware_version' => '1.0.0',
    ]);

    $path = storage_path('test-device-job.json');
    file_put_contents($path, $deviceJson);
    config(['vibecodepc.device_json_path' => $path]);

    app()->singleton(
        DeviceIdentityService::class,
        fn () => new DeviceIdentityService($path),
    );
}

afterEach(function () {
    $path = storage_path('test-device-job.json');
    if (file_exists($path)) {
        unlink($path);
    }
});

it('starts tunnel and registers URL with cloud', function () {
    $uuid = 'test-device-uuid';
    $tunnelUrl = 'https://abc123.trycloudflare.com';
    $cloudUrl = config('vibecodepc.cloud_url');

    setupIdentityForJob($uuid);

    TunnelConfig::factory()->verified()->create();

    $quickTunnelService = Mockery::mock(QuickTunnelService::class);
    $quickTunnelService->shouldReceive('startForDashboard')
        ->once()
        ->andReturn($tunnelUrl);

    app()->instance(QuickTunnelService::class, $quickTunnelService);

    Http::fake([
        "{$cloudUrl}/api/devices/{$uuid}/tunnel/register" => Http::response(null, 200),
    ]);

    $job = new ProvisionQuickTunnelJob;
    app()->call([$job, 'handle']);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/tunnel/register')
        && $request['tunnel_url'] === $tunnelUrl);
});

it('seeds wizard progress', function () {
    $uuid = 'test-device-uuid';

    setupIdentityForJob($uuid);

    $quickTunnelService = Mockery::mock(QuickTunnelService::class);
    $quickTunnelService->shouldReceive('startForDashboard')
        ->once()
        ->andReturn('https://test.trycloudflare.com');

    app()->instance(QuickTunnelService::class, $quickTunnelService);

    Http::fake();

    $job = new ProvisionQuickTunnelJob;
    app()->call([$job, 'handle']);

    expect(\App\Models\WizardProgress::count())->toBeGreaterThan(0);
});

it('falls back to app URL in local dev when tunnel fails', function () {
    $uuid = 'test-device-uuid';
    $cloudUrl = config('vibecodepc.cloud_url');

    setupIdentityForJob($uuid);

    TunnelConfig::factory()->verified()->create();

    app()->detectEnvironment(fn () => 'local');

    $quickTunnelService = Mockery::mock(QuickTunnelService::class);
    $quickTunnelService->shouldReceive('startForDashboard')
        ->once()
        ->andThrow(new RuntimeException('Docker not available'));

    app()->instance(QuickTunnelService::class, $quickTunnelService);

    Http::fake([
        "{$cloudUrl}/api/devices/{$uuid}/tunnel/register" => Http::response(null, 200),
    ]);

    $job = new ProvisionQuickTunnelJob;
    app()->call([$job, 'handle']);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/tunnel/register'));
});

it('returns early in non-local env when tunnel fails', function () {
    $uuid = 'test-device-uuid';

    setupIdentityForJob($uuid);

    app()->detectEnvironment(fn () => 'production');

    $quickTunnelService = Mockery::mock(QuickTunnelService::class);
    $quickTunnelService->shouldReceive('startForDashboard')
        ->once()
        ->andThrow(new RuntimeException('Docker not available'));

    app()->instance(QuickTunnelService::class, $quickTunnelService);

    Http::fake();

    $job = new ProvisionQuickTunnelJob;
    app()->call([$job, 'handle']);

    // Should not try to register a URL
    Http::assertNothingSent();

    // But wizard progress should still be seeded
    expect(\App\Models\WizardProgress::count())->toBeGreaterThan(0);
});

it('starts tunnel and does not block for URL discovery', function () {
    $uuid = 'test-device-uuid';
    $cloudUrl = config('vibecodepc.cloud_url');

    setupIdentityForJob($uuid);

    TunnelConfig::factory()->verified()->create();

    $quickTunnelService = Mockery::mock(QuickTunnelService::class);
    // startForDashboard now returns null immediately and dispatches async job
    $quickTunnelService->shouldReceive('startForDashboard')
        ->once()
        ->andReturn(null);
    // refreshUrl should not be called directly anymore
    $quickTunnelService->shouldNotReceive('refreshUrl');

    app()->instance(QuickTunnelService::class, $quickTunnelService);

    Http::fake([
        "{$cloudUrl}/api/devices/{$uuid}/tunnel/register" => Http::response(null, 200),
    ]);

    $job = new ProvisionQuickTunnelJob;
    app()->call([$job, 'handle']);

    // No URL was captured immediately, so no cloud registration happens
    // PollTunnelUrlJob will handle registration asynchronously when URL is found
    Http::assertNothingSent();
});

it('returns early when tunnel is skipped', function () {
    $uuid = 'test-device-uuid';

    setupIdentityForJob($uuid);

    TunnelConfig::factory()->skipped()->create();

    $quickTunnelService = Mockery::mock(QuickTunnelService::class);
    $quickTunnelService->shouldNotReceive('startForDashboard');

    app()->instance(QuickTunnelService::class, $quickTunnelService);

    Http::fake();

    $job = new ProvisionQuickTunnelJob;
    app()->call([$job, 'handle']);

    // Should not try to start tunnel or make any cloud API calls
    Http::assertNothingSent();

    // But wizard progress should still be seeded
    expect(\App\Models\WizardProgress::count())->toBeGreaterThan(0);
});
