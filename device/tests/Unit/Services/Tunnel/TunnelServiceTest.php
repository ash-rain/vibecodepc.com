<?php

declare(strict_types=1);

use App\Models\TunnelConfig;
use App\Services\Tunnel\TunnelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Yaml\Yaml;

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

it('detects valid credentials when tunnel token exists', function () {
    TunnelConfig::factory()->verified()->create();

    $service = new TunnelService;

    expect($service->hasCredentials())->toBeTrue();
});

it('rejects missing credentials when no tunnel config exists', function () {
    $service = new TunnelService;

    expect($service->hasCredentials())->toBeFalse();
});

it('rejects credentials when tunnel token is empty', function () {
    TunnelConfig::factory()->create([
        'tunnel_token_encrypted' => null,
    ]);

    $service = new TunnelService;

    expect($service->hasCredentials())->toBeFalse();
});

it('rejects credentials when tunnel token is empty string', function () {
    TunnelConfig::factory()->create([
        'tunnel_token_encrypted' => '',
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

it('refuses to start without credentials', function () {
    Process::fake([
        '*cloudflared --version*' => Process::result(output: '2024.1.0'),
        'pgrep*' => Process::result(exitCode: 1),
    ]);

    $service = new TunnelService;

    expect($service->start())->toBe('Tunnel is not configured. Complete the setup wizard to provision tunnel credentials.');
});

it('starts the tunnel service with valid token', function () {
    TunnelConfig::factory()->verified()->create();

    Process::fake([
        '*cloudflared --version*' => Process::result(output: '2024.1.0'),
        'pgrep*' => Process::sequence([
            Process::result(exitCode: 1),
            Process::result(output: '12345'),
        ]),
        'sudo systemctl start*' => Process::result(),
    ]);

    $service = new TunnelService(configPath: storage_path('app/test-cloudflared/config.yml'));

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

it('falls back to nohup with token when systemd fails', function () {
    TunnelConfig::factory()->verified()->create();

    Process::fake([
        '*cloudflared --version*' => Process::result(output: '2024.1.0'),
        'pgrep*' => Process::sequence([
            Process::result(exitCode: 1),
            Process::result(output: '12345'),
        ]),
        'sudo systemctl start*' => Process::result(exitCode: 1),
        '*nohup cloudflared tunnel run --token*' => Process::result(output: '99999'),
    ]);

    $service = new TunnelService(configPath: storage_path('app/test-cloudflared/config.yml'));

    expect($service->start())->toBeNull();
});

it('writes ingress rules to the config file with default device app route', function () {
    $configPath = storage_path('app/test-cloudflared-ingress/config.yml');
    $service = new TunnelService(configPath: $configPath, deviceAppPort: 8001);

    $service->updateIngress('myuser', [
        'my-project' => 3000,
        'blog' => 3001,
    ]);

    expect(file_exists($configPath))->toBeTrue();

    $config = Yaml::parseFile($configPath);

    expect($config['ingress'])->toHaveCount(4)
        ->and($config['ingress'][0]['hostname'])->toBe('myuser.vibecodepc.com')
        ->and($config['ingress'][0]['path'])->toBe('/my-project(/.*)?$')
        ->and($config['ingress'][0]['service'])->toBe('http://localhost:3000')
        ->and($config['ingress'][1]['hostname'])->toBe('myuser.vibecodepc.com')
        ->and($config['ingress'][1]['path'])->toBe('/blog(/.*)?$')
        ->and($config['ingress'][1]['service'])->toBe('http://localhost:3001')
        ->and($config['ingress'][2]['hostname'])->toBe('myuser.vibecodepc.com')
        ->and($config['ingress'][2]['service'])->toBe('http://localhost:8001')
        ->and($config['ingress'][2])->not->toHaveKey('path')
        ->and($config['ingress'][3]['service'])->toBe('http_status:404');

    File::deleteDirectory(dirname($configPath));
});

it('writes default device app route when no project routes are provided', function () {
    $configPath = storage_path('app/test-cloudflared-empty/config.yml');
    $service = new TunnelService(configPath: $configPath, deviceAppPort: 8001);

    $service->updateIngress('myuser', []);

    $config = Yaml::parseFile($configPath);

    expect($config['ingress'])->toHaveCount(2)
        ->and($config['ingress'][0]['hostname'])->toBe('myuser.vibecodepc.com')
        ->and($config['ingress'][0]['service'])->toBe('http://localhost:8001')
        ->and($config['ingress'][0])->not->toHaveKey('path')
        ->and($config['ingress'][1]['service'])->toBe('http_status:404');

    File::deleteDirectory(dirname($configPath));
});
