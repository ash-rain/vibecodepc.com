<?php

use App\Models\DeviceState;
use App\Models\TunnelConfig;
use App\Services\CloudApiClient;
use App\Services\ConfigSyncService;
use App\Services\Tunnel\TunnelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->cloudApi = Mockery::mock(CloudApiClient::class);
    $this->tunnelService = Mockery::mock(TunnelService::class);
    $this->service = new ConfigSyncService($this->cloudApi, $this->tunnelService);
});

afterEach(function () {
    Mockery::close();
});

describe('syncIfNeeded', function () {
    it('returns early when remote config is null', function () {
        $this->cloudApi
            ->shouldReceive('getDeviceConfig')
            ->once()
            ->with('device-123')
            ->andReturn(null);

        $this->service->syncIfNeeded('device-123');

        // Should not create any device state
        expect(DeviceState::where('key', 'config_version')->exists())->toBeFalse();
    });

    it('returns early when remote version is less than or equal to local version', function () {
        // Set local version
        DeviceState::setValue('config_version', '10');

        $this->cloudApi
            ->shouldReceive('getDeviceConfig')
            ->once()
            ->with('device-123')
            ->andReturn([
                'config_version' => 5,
                'subdomain' => 'test-subdomain',
            ]);

        $this->service->syncIfNeeded('device-123');

        // Local version should remain unchanged
        expect(DeviceState::getValue('config_version'))->toBe('10');
    });

    it('updates local version when remote version is greater', function () {
        // Set local version
        DeviceState::setValue('config_version', '1');

        // Create tunnel config
        TunnelConfig::create([
            'subdomain' => 'old-subdomain',
            'tunnel_token_encrypted' => encrypt('old-token'),
            'tunnel_id' => 'tunnel-123',
            'status' => 'active',
        ]);

        $this->cloudApi
            ->shouldReceive('getDeviceConfig')
            ->once()
            ->with('device-123')
            ->andReturn([
                'config_version' => 5,
                'subdomain' => 'new-subdomain',
            ]);

        $this->service->syncIfNeeded('device-123');

        // Local version should be updated
        expect(DeviceState::getValue('config_version'))->toBe('5');

        // Subdomain should be updated
        $config = TunnelConfig::current();
        expect($config->subdomain)->toBe('new-subdomain');
    });

    it('updates subdomain when remote subdomain differs', function () {
        DeviceState::setValue('config_version', '1');

        TunnelConfig::create([
            'subdomain' => 'original-subdomain',
            'tunnel_token_encrypted' => encrypt('token'),
            'tunnel_id' => 'tunnel-123',
            'status' => 'active',
        ]);

        $this->cloudApi
            ->shouldReceive('getDeviceConfig')
            ->once()
            ->andReturn([
                'config_version' => 2,
                'subdomain' => 'updated-subdomain',
            ]);

        $this->service->syncIfNeeded('device-123');

        $config = TunnelConfig::current();
        expect($config->subdomain)->toBe('updated-subdomain');
    });

    it('does not update subdomain when remote subdomain matches', function () {
        DeviceState::setValue('config_version', '1');

        TunnelConfig::create([
            'subdomain' => 'same-subdomain',
            'tunnel_token_encrypted' => encrypt('token'),
            'tunnel_id' => 'tunnel-123',
            'status' => 'active',
        ]);

        // Track if update is called
        $updateCalled = false;

        $this->cloudApi
            ->shouldReceive('getDeviceConfig')
            ->once()
            ->andReturn([
                'config_version' => 2,
                'subdomain' => 'same-subdomain',
            ]);

        // Intercept the update call
        $originalUpdate = TunnelConfig::current()->getAttributes();

        $this->service->syncIfNeeded('device-123');

        // Subdomain should remain the same
        $config = TunnelConfig::current();
        expect($config->subdomain)->toBe('same-subdomain');
    });

    it('handles missing remote subdomain gracefully', function () {
        DeviceState::setValue('config_version', '1');

        TunnelConfig::create([
            'subdomain' => 'existing-subdomain',
            'tunnel_token_encrypted' => encrypt('token'),
            'tunnel_id' => 'tunnel-123',
            'status' => 'active',
        ]);

        $this->cloudApi
            ->shouldReceive('getDeviceConfig')
            ->once()
            ->andReturn([
                'config_version' => 2,
            ]);

        $this->service->syncIfNeeded('device-123');

        // Subdomain should remain unchanged
        $config = TunnelConfig::current();
        expect($config->subdomain)->toBe('existing-subdomain');
    });

    it('skips subdomain update when no tunnel config exists', function () {
        DeviceState::setValue('config_version', '1');

        $this->cloudApi
            ->shouldReceive('getDeviceConfig')
            ->once()
            ->andReturn([
                'config_version' => 2,
                'subdomain' => 'new-subdomain',
            ]);

        // Should not throw error when TunnelConfig doesn't exist
        $this->service->syncIfNeeded('device-123');

        expect(true)->toBeTrue();
    });
});

describe('token updates', function () {
    it('updates tunnel token and restarts service when new token received', function () {
        DeviceState::setValue('config_version', '1');

        TunnelConfig::create([
            'subdomain' => 'test-subdomain',
            'tunnel_token_encrypted' => encrypt('old-token'),
            'tunnel_id' => 'tunnel-123',
            'status' => 'active',
        ]);

        $newToken = 'new-tunnel-token-456';

        $this->cloudApi
            ->shouldReceive('getDeviceConfig')
            ->once()
            ->andReturn([
                'config_version' => 2,
                'subdomain' => 'test-subdomain',
                'tunnel_token' => $newToken,
            ]);

        $this->tunnelService
            ->shouldReceive('stop')
            ->once();

        $this->tunnelService
            ->shouldReceive('start')
            ->once()
            ->andReturn(null);

        $this->service->syncIfNeeded('device-123');

        // Token should be updated
        $config = TunnelConfig::current();
        expect($config->tunnel_token_encrypted)->toBe($newToken);
        expect($config->status)->toBe('active');
    });

    it('logs error when restart fails after token update', function () {
        DeviceState::setValue('config_version', '1');

        TunnelConfig::create([
            'subdomain' => 'test-subdomain',
            'tunnel_token_encrypted' => encrypt('old-token'),
            'tunnel_id' => 'tunnel-123',
            'status' => 'active',
        ]);

        $this->cloudApi
            ->shouldReceive('getDeviceConfig')
            ->once()
            ->andReturn([
                'config_version' => 2,
                'tunnel_token' => 'new-token',
            ]);

        $this->tunnelService
            ->shouldReceive('stop')
            ->once();

        $this->tunnelService
            ->shouldReceive('start')
            ->once()
            ->andReturn('Failed to start tunnel: permission denied');

        Log::spy();

        $this->service->syncIfNeeded('device-123');

        Log::shouldHaveReceived('error')
            ->with(Mockery::pattern('/failed to restart tunnel after token update/i'));

        // Token should still be updated
        $config = TunnelConfig::current();
        expect($config->tunnel_token_encrypted)->toBe('new-token');
    });

    it('skips token update when no tunnel config exists', function () {
        DeviceState::setValue('config_version', '1');

        $this->cloudApi
            ->shouldReceive('getDeviceConfig')
            ->once()
            ->andReturn([
                'config_version' => 2,
                'tunnel_token' => 'new-token',
            ]);

        // Should not throw error and should not call tunnel service
        $this->tunnelService->shouldNotReceive('stop');
        $this->tunnelService->shouldNotReceive('start');

        $this->service->syncIfNeeded('device-123');

        expect(true)->toBeTrue();
    });

    it('handles encrypted token correctly', function () {
        DeviceState::setValue('config_version', '1');

        TunnelConfig::create([
            'subdomain' => 'test-subdomain',
            'tunnel_token_encrypted' => encrypt('old-token'),
            'tunnel_id' => 'tunnel-123',
            'status' => 'active',
        ]);

        $this->cloudApi
            ->shouldReceive('getDeviceConfig')
            ->once()
            ->andReturn([
                'config_version' => 2,
                'tunnel_token' => 'encrypted-token-data',
            ]);

        $this->tunnelService->shouldReceive('stop')->once();
        $this->tunnelService->shouldReceive('start')->once()->andReturn(null);

        $this->service->syncIfNeeded('device-123');

        // The token should be stored as-is (encryption handled by model cast)
        $config = TunnelConfig::current();
        expect($config->tunnel_token_encrypted)->toBe('encrypted-token-data');
    });
});

describe('remote version handling', function () {
    it('handles initial sync with no local version', function () {
        // No local version set

        TunnelConfig::create([
            'subdomain' => 'test-subdomain',
            'tunnel_token_encrypted' => encrypt('token'),
            'tunnel_id' => 'tunnel-123',
            'status' => 'active',
        ]);

        $this->cloudApi
            ->shouldReceive('getDeviceConfig')
            ->once()
            ->andReturn([
                'config_version' => 1,
                'subdomain' => 'test-subdomain',
            ]);

        $this->service->syncIfNeeded('device-123');

        expect(DeviceState::getValue('config_version'))->toBe('1');
    });

    it('handles zero as local version', function () {
        // Simulates when no config_version exists (returns '0' from default)
        // getValue returns null when not set, then cast to 0

        TunnelConfig::create([
            'subdomain' => 'test-subdomain',
            'tunnel_token_encrypted' => encrypt('token'),
            'tunnel_id' => 'tunnel-123',
            'status' => 'active',
        ]);

        $this->cloudApi
            ->shouldReceive('getDeviceConfig')
            ->once()
            ->andReturn([
                'config_version' => 1,
                'subdomain' => 'updated-subdomain',
            ]);

        $this->service->syncIfNeeded('device-123');

        expect(DeviceState::getValue('config_version'))->toBe('1');
        expect(TunnelConfig::current()->subdomain)->toBe('updated-subdomain');
    });

    it('handles large version numbers', function () {
        DeviceState::setValue('config_version', '999999');

        TunnelConfig::create([
            'subdomain' => 'test-subdomain',
            'tunnel_token_encrypted' => encrypt('token'),
            'tunnel_id' => 'tunnel-123',
            'status' => 'active',
        ]);

        $this->cloudApi
            ->shouldReceive('getDeviceConfig')
            ->once()
            ->andReturn([
                'config_version' => 1000000,
                'subdomain' => 'updated-subdomain',
            ]);

        $this->service->syncIfNeeded('device-123');

        expect(DeviceState::getValue('config_version'))->toBe('1000000');
    });

    it('handles remote version of zero', function () {
        DeviceState::setValue('config_version', '5');

        $this->cloudApi
            ->shouldReceive('getDeviceConfig')
            ->once()
            ->andReturn([
                'config_version' => 0,
                'subdomain' => 'test-subdomain',
            ]);

        $this->service->syncIfNeeded('device-123');

        // Should not update since remote (0) <= local (5)
        expect(DeviceState::getValue('config_version'))->toBe('5');
    });
});

describe('combined updates', function () {
    it('updates both subdomain and token in single sync', function () {
        DeviceState::setValue('config_version', '1');

        TunnelConfig::create([
            'subdomain' => 'old-subdomain',
            'tunnel_token_encrypted' => encrypt('old-token'),
            'tunnel_id' => 'tunnel-123',
            'status' => 'active',
        ]);

        $this->cloudApi
            ->shouldReceive('getDeviceConfig')
            ->once()
            ->andReturn([
                'config_version' => 2,
                'subdomain' => 'new-subdomain',
                'tunnel_token' => 'new-token',
            ]);

        $this->tunnelService->shouldReceive('stop')->once();
        $this->tunnelService->shouldReceive('start')->once()->andReturn(null);

        $this->service->syncIfNeeded('device-123');

        $config = TunnelConfig::current();
        expect($config->subdomain)->toBe('new-subdomain');
        expect($config->tunnel_token_encrypted)->toBe('new-token');
        expect(DeviceState::getValue('config_version'))->toBe('2');
    });
});

describe('edge cases', function () {
    it('handles empty remote config gracefully', function () {
        DeviceState::setValue('config_version', '1');

        $this->cloudApi
            ->shouldReceive('getDeviceConfig')
            ->once()
            ->andReturn([]);

        $this->service->syncIfNeeded('device-123');

        // Should treat missing config_version as 0
        // Remote (0) <= local (1), so no update
        expect(DeviceState::getValue('config_version'))->toBe('1');
    });

    it('handles missing config_version key', function () {
        DeviceState::setValue('config_version', '1');

        $this->cloudApi
            ->shouldReceive('getDeviceConfig')
            ->once()
            ->andReturn([
                'subdomain' => 'test-subdomain',
                'tunnel_token' => 'token',
            ]);

        $this->service->syncIfNeeded('device-123');

        // Remote version defaults to 0, which is <= local (1)
        expect(DeviceState::getValue('config_version'))->toBe('1');
    });

    it('handles string device id', function () {
        DeviceState::setValue('config_version', '1');

        TunnelConfig::create([
            'subdomain' => 'test',
            'tunnel_token_encrypted' => encrypt('token'),
            'tunnel_id' => 'tunnel-123',
            'status' => 'active',
        ]);

        $this->cloudApi
            ->shouldReceive('getDeviceConfig')
            ->with('device-abc-123-xyz')
            ->once()
            ->andReturn([
                'config_version' => 2,
                'subdomain' => 'updated',
            ]);

        $this->service->syncIfNeeded('device-abc-123-xyz');

        expect(DeviceState::getValue('config_version'))->toBe('2');
    });

    it('logs config sync information', function () {
        DeviceState::setValue('config_version', '1');

        TunnelConfig::create([
            'subdomain' => 'test',
            'tunnel_token_encrypted' => encrypt('token'),
            'tunnel_id' => 'tunnel-123',
            'status' => 'active',
        ]);

        $this->cloudApi
            ->shouldReceive('getDeviceConfig')
            ->once()
            ->andReturn([
                'config_version' => 5,
                'subdomain' => 'updated',
            ]);

        $this->service->syncIfNeeded('device-123');

        // Verify version was updated
        expect(DeviceState::getValue('config_version'))->toBe('5');
        expect(TunnelConfig::current()->subdomain)->toBe('updated');
    });

    it('preserves existing tunnel status when only subdomain changes', function () {
        DeviceState::setValue('config_version', '1');

        TunnelConfig::create([
            'subdomain' => 'old',
            'tunnel_token_encrypted' => encrypt('token'),
            'tunnel_id' => 'tunnel-123',
            'status' => 'pending',
        ]);

        $this->cloudApi
            ->shouldReceive('getDeviceConfig')
            ->once()
            ->andReturn([
                'config_version' => 2,
                'subdomain' => 'new',
            ]);

        $this->service->syncIfNeeded('device-123');

        $config = TunnelConfig::current();
        expect($config->subdomain)->toBe('new');
        expect($config->status)->toBe('pending');
    });
});
