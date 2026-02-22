<?php

declare(strict_types=1);

use App\Services\Tunnel\TunnelService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

it('checks if cloudflared is installed', function () {
    Process::fake([
        'cloudflared --version*' => Process::result(output: '2024.1.0'),
    ]);

    $service = new TunnelService;

    expect($service->isInstalled())->toBeTrue();
});

it('reports not installed when version command fails', function () {
    Process::fake([
        'cloudflared --version*' => Process::result(exitCode: 1),
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

it('returns status array', function () {
    Process::fake([
        'cloudflared --version*' => Process::result(output: '2024.1.0'),
        'pgrep*' => Process::result(output: '12345'),
    ]);

    $service = new TunnelService;
    $status = $service->getStatus();

    expect($status)->toHaveKeys(['installed', 'running'])
        ->and($status['installed'])->toBeTrue()
        ->and($status['running'])->toBeTrue();
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

it('starts the tunnel service', function () {
    Process::fake([
        '*' => Process::result(),
    ]);

    $service = new TunnelService;

    expect($service->start())->toBeTrue();
});

it('stops the tunnel service', function () {
    Process::fake([
        '*' => Process::result(),
    ]);

    $service = new TunnelService;

    expect($service->stop())->toBeTrue();
});
