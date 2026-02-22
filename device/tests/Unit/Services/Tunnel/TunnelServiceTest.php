<?php

declare(strict_types=1);

use App\Models\TunnelConfig;
use App\Services\Tunnel\TunnelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

uses(RefreshDatabase::class);

it('checks if cloudflared is installed', function () {
    Process::fake([
        '*cloudflared --version*' => Process::result(output: '2024.1.0'),
    ]);

    $service = new TunnelService;

    expect($service->isInstalled())->toBeTrue();
});

it('reports not installed when version command fails', function () {
    Process::fake([
        '*cloudflared --version*' => Process::result(exitCode: 1),
    ]);

    $service = new TunnelService;

    expect($service->isInstalled())->toBeFalse();
});

it('checks if cloudflared is running', function () {
    Process::fake([
        'pgrep*' => Process::result(output: '12345'),
    ]);

    $service = new TunnelService;

    expect($service->isRunning())->toBeTrue();
});

it('detects real credentials', function () {
    TunnelConfig::factory()->verified()->create();

    $service = new TunnelService;

    expect($service->hasCredentials())->toBeTrue();
});

it('rejects placeholder credentials', function () {
    TunnelConfig::factory()->create([
        'tunnel_id' => '00000000-0000-0000-0000-000000000000',
    ]);

    $service = new TunnelService;

    expect($service->hasCredentials())->toBeFalse();
});

it('rejects missing credentials when no tunnel config exists', function () {
    $service = new TunnelService;

    expect($service->hasCredentials())->toBeFalse();
});

it('rejects empty tunnel id', function () {
    TunnelConfig::factory()->create([
        'tunnel_id' => '',
    ]);

    $service = new TunnelService;

    expect($service->hasCredentials())->toBeFalse();
});

it('returns status array with configured key', function () {
    Process::fake([
        '*cloudflared --version*' => Process::result(output: '2024.1.0'),
        'pgrep*' => Process::result(output: '12345'),
    ]);

    $service = new TunnelService;
    $status = $service->getStatus();

    expect($status)->toHaveKeys(['installed', 'running', 'configured'])
        ->and($status['installed'])->toBeTrue()
        ->and($status['running'])->toBeTrue()
        ->and($status['configured'])->toBeFalse();
});

it('creates tunnel config file', function () {
    $configPath = storage_path('app/test-cloudflared/config.yml');

    File::deleteDirectory(dirname($configPath));

    $service = new TunnelService(configPath: $configPath);
    $result = $service->createTunnel('mydevice', '');

    expect($result)->toBeTrue()
        ->and(File::exists($configPath))->toBeTrue()
        ->and(File::get($configPath))->toContain('mydevice.vibecodepc.com');

    File::deleteDirectory(dirname($configPath));
});

it('refuses to start without credentials', function () {
    Process::fake([
        '*cloudflared --version*' => Process::result(output: '2024.1.0'),
        'pgrep*' => Process::result(exitCode: 1),
    ]);

    $service = new TunnelService;

    expect($service->start())->toBe('Tunnel is not configured. Complete the setup wizard to provision tunnel credentials.');
});

it('starts the tunnel service with valid credentials', function () {
    TunnelConfig::factory()->verified()->create();

    Process::fake([
        '*cloudflared --version*' => Process::result(output: '2024.1.0'),
        'pgrep*' => Process::sequence([
            Process::result(exitCode: 1),
            Process::result(output: '12345'),
        ]),
        'sudo systemctl start*' => Process::result(),
    ]);

    $service = new TunnelService;

    expect($service->start())->toBeNull();
});

it('stops the tunnel service', function () {
    Process::fake([
        'pgrep*' => Process::sequence([
            Process::result(output: '12345'),
            Process::result(exitCode: 1),
        ]),
        'sudo systemctl stop*' => Process::result(),
    ]);

    $service = new TunnelService;

    expect($service->stop())->toBeNull();
});
