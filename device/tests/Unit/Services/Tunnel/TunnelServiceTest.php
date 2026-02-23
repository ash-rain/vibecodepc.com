<?php

declare(strict_types=1);

use App\Models\TunnelConfig;
use App\Services\Tunnel\TunnelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

uses(RefreshDatabase::class);

it('always reports installed', function () {
    $service = new TunnelService(tokenFilePath: storage_path('app/test-tunnel/token'));

    expect($service->isInstalled())->toBeTrue();
});

it('reports running when token file has content', function () {
    $tokenFile = storage_path('app/test-tunnel-token/token');
    @mkdir(dirname($tokenFile), 0755, true);
    file_put_contents($tokenFile, 'test-token-value');

    $service = new TunnelService(tokenFilePath: $tokenFile);

    expect($service->isRunning())->toBeTrue();

    File::deleteDirectory(dirname($tokenFile));
});

it('reports not running when token file is empty', function () {
    $tokenFile = storage_path('app/test-tunnel-token/token');
    @mkdir(dirname($tokenFile), 0755, true);
    file_put_contents($tokenFile, '');

    $service = new TunnelService(tokenFilePath: $tokenFile);

    expect($service->isRunning())->toBeFalse();

    File::deleteDirectory(dirname($tokenFile));
});

it('reports not running when token file does not exist', function () {
    $tokenFile = storage_path('app/test-tunnel-token-missing/token');

    $service = new TunnelService(tokenFilePath: $tokenFile);

    expect($service->isRunning())->toBeFalse();
});

it('detects valid credentials when tunnel token exists', function () {
    TunnelConfig::factory()->verified()->create();

    $service = new TunnelService(tokenFilePath: storage_path('app/test-tunnel/token'));

    expect($service->hasCredentials())->toBeTrue();
});

it('rejects missing credentials when no tunnel config exists', function () {
    $service = new TunnelService(tokenFilePath: storage_path('app/test-tunnel/token'));

    expect($service->hasCredentials())->toBeFalse();
});

it('rejects credentials when tunnel token is empty', function () {
    TunnelConfig::factory()->create([
        'tunnel_token_encrypted' => null,
    ]);

    $service = new TunnelService(tokenFilePath: storage_path('app/test-tunnel/token'));

    expect($service->hasCredentials())->toBeFalse();
});

it('rejects credentials when tunnel token is empty string', function () {
    TunnelConfig::factory()->create([
        'tunnel_token_encrypted' => '',
    ]);

    $service = new TunnelService(tokenFilePath: storage_path('app/test-tunnel/token'));

    expect($service->hasCredentials())->toBeFalse();
});

it('returns status array with configured key', function () {
    $tokenFile = storage_path('app/test-tunnel-status/token');
    @mkdir(dirname($tokenFile), 0755, true);
    file_put_contents($tokenFile, 'test-token-value');

    $service = new TunnelService(tokenFilePath: $tokenFile);
    $status = $service->getStatus();

    expect($status)->toHaveKeys(['installed', 'running', 'configured'])
        ->and($status['installed'])->toBeTrue()
        ->and($status['running'])->toBeTrue()
        ->and($status['configured'])->toBeFalse();

    File::deleteDirectory(dirname($tokenFile));
});

it('refuses to start without credentials', function () {
    $tokenFile = storage_path('app/test-tunnel-nocreds/token');

    $service = new TunnelService(tokenFilePath: $tokenFile);

    expect($service->start())->toBe('Tunnel is not configured. Complete the setup wizard to provision tunnel credentials.');
});

it('writes token to file on start', function () {
    $tokenFile = storage_path('app/test-tunnel-start/token');
    TunnelConfig::factory()->verified()->create();

    $service = new TunnelService(tokenFilePath: $tokenFile);

    expect($service->start())->toBeNull();
    expect(file_exists($tokenFile))->toBeTrue();
    expect(file_get_contents($tokenFile))->not->toBeEmpty();

    File::deleteDirectory(dirname($tokenFile));
});

it('returns null when already running on start', function () {
    $tokenFile = storage_path('app/test-tunnel-already/token');
    @mkdir(dirname($tokenFile), 0755, true);
    file_put_contents($tokenFile, 'test-token-value');

    TunnelConfig::factory()->verified()->create();

    $service = new TunnelService(tokenFilePath: $tokenFile);

    expect($service->start())->toBeNull();

    File::deleteDirectory(dirname($tokenFile));
});

it('empties token file on stop', function () {
    $tokenFile = storage_path('app/test-tunnel-stop/token');
    @mkdir(dirname($tokenFile), 0755, true);
    file_put_contents($tokenFile, 'test-token-value');

    $service = new TunnelService(tokenFilePath: $tokenFile);

    expect($service->stop())->toBeNull();
    expect(file_get_contents($tokenFile))->toBe('');

    File::deleteDirectory(dirname($tokenFile));
});

it('returns null on stop when already stopped', function () {
    $tokenFile = storage_path('app/test-tunnel-stop-noop/token');
    @mkdir(dirname($tokenFile), 0755, true);
    file_put_contents($tokenFile, '');

    $service = new TunnelService(tokenFilePath: $tokenFile);

    expect($service->stop())->toBeNull();

    File::deleteDirectory(dirname($tokenFile));
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

it('truncates token file and marks config as error on cleanup', function () {
    $tokenFile = storage_path('app/test-tunnel-cleanup/token');
    @mkdir(dirname($tokenFile), 0755, true);
    file_put_contents($tokenFile, 'test-token-value');

    TunnelConfig::factory()->verified()->create();

    $service = new TunnelService(tokenFilePath: $tokenFile);
    $service->cleanup();

    expect(file_get_contents($tokenFile))->toBe('');
    expect(TunnelConfig::current()->status)->toBe('error');

    File::deleteDirectory(dirname($tokenFile));
});
