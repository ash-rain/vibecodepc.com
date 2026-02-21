<?php

declare(strict_types=1);

use App\Services\Tunnel\TunnelService;
use Illuminate\Support\Facades\Process;

it('checks if cloudflared is installed', function () {
    Process::fake([
        'which cloudflared' => Process::result(output: '/usr/bin/cloudflared'),
    ]);

    $service = new TunnelService;

    expect($service->isInstalled())->toBeTrue();
});

it('reports not installed when which fails', function () {
    Process::fake([
        'which cloudflared' => Process::result(exitCode: 1),
    ]);

    $service = new TunnelService;

    expect($service->isInstalled())->toBeFalse();
});

it('checks if cloudflared is running', function () {
    Process::fake([
        'systemctl is-active cloudflared' => Process::result(output: 'active'),
    ]);

    $service = new TunnelService;

    expect($service->isRunning())->toBeTrue();
});

it('returns status array', function () {
    Process::fake([
        'which cloudflared' => Process::result(output: '/usr/bin/cloudflared'),
        'systemctl is-active cloudflared' => Process::result(output: 'active'),
    ]);

    $service = new TunnelService;
    $status = $service->getStatus();

    expect($status)->toHaveKeys(['installed', 'running'])
        ->and($status['installed'])->toBeTrue()
        ->and($status['running'])->toBeTrue();
});

it('starts the tunnel service', function () {
    Process::fake([
        'sudo systemctl start cloudflared' => Process::result(),
    ]);

    $service = new TunnelService;

    expect($service->start())->toBeTrue();
});

it('stops the tunnel service', function () {
    Process::fake([
        'sudo systemctl stop cloudflared' => Process::result(),
    ]);

    $service = new TunnelService;

    expect($service->stop())->toBeTrue();
});
