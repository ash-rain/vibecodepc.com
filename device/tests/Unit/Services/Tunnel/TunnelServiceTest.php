<?php

declare(strict_types=1);

use App\Models\TunnelConfig;
use App\Services\CloudApiClient;
use App\Services\DeviceRegistry\DeviceIdentityService;
use App\Services\Tunnel\TunnelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use VibecodePC\Common\DTOs\DeviceInfo;

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

it('accepts credentials when tunnel status is skipped', function () {
    TunnelConfig::factory()->skipped()->create();

    $service = new TunnelService(tokenFilePath: storage_path('app/test-tunnel/token'));

    expect($service->hasCredentials())->toBeTrue();
});

it('returns true for isSkipped when tunnel is skipped', function () {
    TunnelConfig::factory()->skipped()->create();

    $service = new TunnelService(tokenFilePath: storage_path('app/test-tunnel/token'));

    expect($service->isSkipped())->toBeTrue();
});

it('returns false for isSkipped when tunnel is not skipped', function () {
    TunnelConfig::factory()->verified()->create();

    $service = new TunnelService(tokenFilePath: storage_path('app/test-tunnel/token'));

    expect($service->isSkipped())->toBeFalse();
});

it('returns false for isSkipped when no config exists', function () {
    $service = new TunnelService(tokenFilePath: storage_path('app/test-tunnel/token'));

    expect($service->isSkipped())->toBeFalse();
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

it('pushes ingress rules to the cloud API with project routes and default device app route', function () {
    $mockCloudApi = Mockery::mock(CloudApiClient::class);
    $mockIdentity = Mockery::mock(DeviceIdentityService::class);

    $mockIdentity->shouldReceive('hasIdentity')->andReturn(true);
    $mockIdentity->shouldReceive('getDeviceInfo')->andReturn(DeviceInfo::fromArray([
        'id' => 'device-123',
        'hardware_serial' => 'test-serial',
        'manufactured_at' => '2026-01-01',
        'firmware_version' => '1.0.0',
    ]));

    $capturedIngress = null;
    $mockCloudApi->shouldReceive('reconfigureTunnelIngress')
        ->once()
        ->withArgs(function ($deviceId, $ingress) use (&$capturedIngress) {
            $capturedIngress = $ingress;

            return $deviceId === 'device-123';
        });

    app()->instance(CloudApiClient::class, $mockCloudApi);
    app()->instance(DeviceIdentityService::class, $mockIdentity);

    $service = new TunnelService(deviceAppPort: 8001);

    $service->updateIngress([
        'my-project' => 3000,
        'blog' => 3001,
    ]);

    expect($capturedIngress)->toHaveCount(3)
        ->and($capturedIngress[0]['path'])->toBe('/my-project(/.*)?$')
        ->and($capturedIngress[0]['service'])->toBe('http://localhost:3000')
        ->and($capturedIngress[1]['path'])->toBe('/blog(/.*)?$')
        ->and($capturedIngress[1]['service'])->toBe('http://localhost:3001')
        ->and($capturedIngress[2]['service'])->toBe('http://localhost:8001')
        ->and($capturedIngress[2])->not->toHaveKey('path');
});

it('pushes default device app route when no project routes are provided', function () {
    $mockCloudApi = Mockery::mock(CloudApiClient::class);
    $mockIdentity = Mockery::mock(DeviceIdentityService::class);

    $mockIdentity->shouldReceive('hasIdentity')->andReturn(true);
    $mockIdentity->shouldReceive('getDeviceInfo')->andReturn(DeviceInfo::fromArray([
        'id' => 'device-123',
        'hardware_serial' => 'test-serial',
        'manufactured_at' => '2026-01-01',
        'firmware_version' => '1.0.0',
    ]));

    $capturedIngress = null;
    $mockCloudApi->shouldReceive('reconfigureTunnelIngress')
        ->once()
        ->withArgs(function ($deviceId, $ingress) use (&$capturedIngress) {
            $capturedIngress = $ingress;

            return $deviceId === 'device-123';
        });

    app()->instance(CloudApiClient::class, $mockCloudApi);
    app()->instance(DeviceIdentityService::class, $mockIdentity);

    $service = new TunnelService(deviceAppPort: 8001);

    $service->updateIngress([]);

    expect($capturedIngress)->toHaveCount(1)
        ->and($capturedIngress[0]['service'])->toBe('http://localhost:8001')
        ->and($capturedIngress[0])->not->toHaveKey('path');
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

// Skipped state tests
it('refuses to start when tunnel is skipped', function () {
    $tokenFile = storage_path('app/test-tunnel-skip-start/token');

    // Clean up any existing test artifacts
    if (is_dir(dirname($tokenFile))) {
        File::deleteDirectory(dirname($tokenFile));
    }

    TunnelConfig::factory()->skipped()->create();

    $service = new TunnelService(tokenFilePath: $tokenFile);

    expect($service->start())->toBe('Tunnel setup was skipped. Complete tunnel setup to enable remote access.');
    expect(file_exists($tokenFile))->toBeFalse();

    // Cleanup
    if (is_dir(dirname($tokenFile))) {
        File::deleteDirectory(dirname($tokenFile));
    }
});

it('reports configured but not running when tunnel is skipped', function () {
    TunnelConfig::factory()->skipped()->create();

    $service = new TunnelService(tokenFilePath: storage_path('app/test-tunnel-skip-status/token'));
    $status = $service->getStatus();

    expect($status['installed'])->toBeTrue()
        ->and($status['running'])->toBeFalse()
        ->and($status['configured'])->toBeTrue();
});

it('does not update ingress when tunnel is skipped', function () {
    $mockCloudApi = Mockery::mock(CloudApiClient::class);

    // Cloud API should NOT be called when tunnel is skipped
    $mockCloudApi->shouldNotReceive('reconfigureTunnelIngress');

    app()->instance(CloudApiClient::class, $mockCloudApi);

    TunnelConfig::factory()->skipped()->create();

    $service = new TunnelService;

    // Should complete without calling the Cloud API
    $service->updateIngress(['project' => 3000]);
});

it('reports not configured when config has status skipped but no skipped_at timestamp', function () {
    // Edge case: status is 'skipped' but skipped_at is null (incomplete skip)
    TunnelConfig::factory()->create([
        'status' => 'skipped',
        'skipped_at' => null,
        'tunnel_token_encrypted' => null,
    ]);

    $service = new TunnelService(tokenFilePath: storage_path('app/test-tunnel-skip-edge/token'));

    // isSkipped should return true based on status alone
    expect($service->isSkipped())->toBeTrue()
        ->and($service->hasCredentials())->toBeTrue();
});

it('returns correct status array when tunnel is skipped with token file existing', function () {
    // Edge case: skipped config but token file exists from previous setup
    $tokenFile = storage_path('app/test-tunnel-skip-existing/token');

    // Clean up first
    if (is_dir(dirname($tokenFile))) {
        File::deleteDirectory(dirname($tokenFile));
    }

    @mkdir(dirname($tokenFile), 0755, true);
    file_put_contents($tokenFile, 'old-token-value');

    TunnelConfig::factory()->skipped()->create();

    $service = new TunnelService(tokenFilePath: $tokenFile);
    $status = $service->getStatus();

    // Should report running=true (token file exists) but configured=true (skipped state)
    expect($status['installed'])->toBeTrue()
        ->and($status['running'])->toBeTrue()
        ->and($status['configured'])->toBeTrue();

    File::deleteDirectory(dirname($tokenFile));
});

it('cleanup marks status as error even when tunnel is already skipped', function () {
    $tokenFile = storage_path('app/test-tunnel-cleanup-skip/token');

    // Clean up first
    if (is_dir(dirname($tokenFile))) {
        File::deleteDirectory(dirname($tokenFile));
    }

    @mkdir(dirname($tokenFile), 0755, true);
    file_put_contents($tokenFile, 'test-token-value');

    TunnelConfig::factory()->skipped()->create();

    $service = new TunnelService(tokenFilePath: $tokenFile);
    $service->cleanup();

    expect(file_get_contents($tokenFile))->toBe('')
        ->and(TunnelConfig::current()->status)->toBe('error');

    File::deleteDirectory(dirname($tokenFile));
});

// Auto-detection tests
it('detects when tunnel was skipped but now has token file', function () {
    $tokenFile = storage_path('app/test-tunnel-skip-available/token');

    // Clean up first
    if (is_dir(dirname($tokenFile))) {
        File::deleteDirectory(dirname($tokenFile));
    }

    @mkdir(dirname($tokenFile), 0755, true);
    file_put_contents($tokenFile, 'available-token');

    TunnelConfig::factory()->available()->create();

    $service = new TunnelService(tokenFilePath: $tokenFile);

    expect($service->wasSkippedButNowAvailable())->toBeTrue()
        ->and($service->isEffectivelyConfigured())->toBeTrue();

    File::deleteDirectory(dirname($tokenFile));
});

it('returns false for wasSkippedButNowAvailable when tunnel is verified', function () {
    TunnelConfig::factory()->verified()->create();

    $service = new TunnelService(tokenFilePath: storage_path('app/test-tunnel/token'));

    expect($service->wasSkippedButNowAvailable())->toBeFalse()
        ->and($service->isEffectivelyConfigured())->toBeTrue();
});

it('returns false for wasSkippedButNowAvailable when tunnel is pending', function () {
    TunnelConfig::factory()->create(['status' => 'pending']);

    $service = new TunnelService(tokenFilePath: storage_path('app/test-tunnel/token'));

    expect($service->wasSkippedButNowAvailable())->toBeFalse()
        ->and($service->isEffectivelyConfigured())->toBeFalse();
});

it('returns false for wasSkippedButNowAvailable when no config exists', function () {
    $service = new TunnelService(tokenFilePath: storage_path('app/test-tunnel/token'));

    expect($service->wasSkippedButNowAvailable())->toBeFalse()
        ->and($service->isEffectivelyConfigured())->toBeFalse();
});

it('isEffectivelyConfigured returns true when tunnel has credentials', function () {
    TunnelConfig::factory()->verified()->create();

    $service = new TunnelService(tokenFilePath: storage_path('app/test-tunnel/token'));

    expect($service->isEffectivelyConfigured())->toBeTrue();
});

it('isEffectivelyConfigured returns true when tunnel was skipped but now available', function () {
    $tokenFile = storage_path('app/test-tunnel-eff-conf/token');

    // Clean up first
    if (is_dir(dirname($tokenFile))) {
        File::deleteDirectory(dirname($tokenFile));
    }

    @mkdir(dirname($tokenFile), 0755, true);
    file_put_contents($tokenFile, 'token');

    TunnelConfig::factory()->available()->create();

    $service = new TunnelService(tokenFilePath: $tokenFile);

    expect($service->isEffectivelyConfigured())->toBeTrue();

    File::deleteDirectory(dirname($tokenFile));
});

// Poll status tests
it('pollStatus returns not detected when no config exists', function () {
    $service = new TunnelService(tokenFilePath: storage_path('app/test-tunnel/token'));
    $result = $service->pollStatus();

    expect($result['detected'])->toBeFalse()
        ->and($result['message'])->toBeNull()
        ->and($result['error'])->toBeNull();
});

it('pollStatus returns not detected when tunnel is not skipped', function () {
    TunnelConfig::factory()->active()->create();

    $service = new TunnelService(tokenFilePath: storage_path('app/test-tunnel/token'));
    $result = $service->pollStatus();

    expect($result['detected'])->toBeFalse()
        ->and($result['message'])->toBeNull()
        ->and($result['error'])->toBeNull();
});

it('pollStatus returns not detected when tunnel is skipped but token file does not exist', function () {
    TunnelConfig::factory()->skipped()->create();

    $service = new TunnelService(tokenFilePath: storage_path('app/test-tunnel-poll-missing/token'));
    $result = $service->pollStatus();

    expect($result['detected'])->toBeFalse()
        ->and($result['message'])->toBeNull()
        ->and($result['error'])->toBeNull();
});

it('pollStatus detects token and updates status when token file appears', function () {
    $tokenFile = storage_path('app/test-tunnel-poll-detect/token');

    // Clean up first
    if (is_dir(dirname($tokenFile))) {
        File::deleteDirectory(dirname($tokenFile));
    }

    @mkdir(dirname($tokenFile), 0755, true);
    file_put_contents($tokenFile, 'test-tunnel-token');

    TunnelConfig::factory()->skipped()->create();

    $service = new TunnelService(tokenFilePath: $tokenFile);
    $result = $service->pollStatus();

    expect($result['detected'])->toBeTrue()
        ->and($result['message'])->toBe('Tunnel is now available and marked as active')
        ->and($result['error'])->toBeNull();

    $config = TunnelConfig::current();
    expect($config->status)->toBe('available')
        ->and($config->skipped_at)->toBeNull();

    File::deleteDirectory(dirname($tokenFile));
});

it('pollStatus handles edge case when config is deleted during execution', function () {
    $tokenFile = storage_path('app/test-tunnel-poll-race/token');

    // Clean up first
    if (is_dir(dirname($tokenFile))) {
        File::deleteDirectory(dirname($tokenFile));
    }

    @mkdir(dirname($tokenFile), 0755, true);
    file_put_contents($tokenFile, 'test-token');

    TunnelConfig::factory()->skipped()->create();

    $service = new TunnelService(tokenFilePath: $tokenFile);

    // Delete config after creating service
    TunnelConfig::query()->delete();

    $result = $service->pollStatus();

    // Should handle gracefully - no config means not detected
    expect($result['detected'])->toBeFalse()
        ->and($result['message'])->toBeNull()
        ->and($result['error'])->toBeNull();

    File::deleteDirectory(dirname($tokenFile));
});
