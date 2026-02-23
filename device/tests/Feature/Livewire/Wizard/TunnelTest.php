<?php

declare(strict_types=1);

use App\Livewire\Wizard\Tunnel;
use App\Models\TunnelConfig;
use App\Services\CloudApiClient;
use App\Services\DeviceRegistry\DeviceIdentityService;
use App\Services\Tunnel\TunnelService;
use App\Services\WizardProgressService;
use Livewire\Livewire;
use VibecodePC\Common\DTOs\DeviceInfo;
use VibecodePC\Common\Enums\WizardStep;

beforeEach(function () {
    app(WizardProgressService::class)->seedProgress();
});

it('renders the tunnel step', function () {
    Livewire::test(Tunnel::class)
        ->assertStatus(200)
        ->assertSee('Tunnel Setup');
});

it('validates subdomain format', function () {
    Livewire::test(Tunnel::class)
        ->set('subdomain', 'a')
        ->call('checkAvailability')
        ->assertHasErrors(['subdomain']);
});

it('checks subdomain availability', function () {
    $mock = Mockery::mock(CloudApiClient::class);
    $mock->shouldReceive('checkSubdomainAvailability')
        ->with('testuser')
        ->once()
        ->andReturn(true);
    app()->instance(CloudApiClient::class, $mock);

    Livewire::test(Tunnel::class)
        ->set('subdomain', 'testuser')
        ->call('checkAvailability')
        ->assertSet('subdomainAvailable', true);
});

it('reports unavailable subdomain', function () {
    $mock = Mockery::mock(CloudApiClient::class);
    $mock->shouldReceive('checkSubdomainAvailability')
        ->with('taken')
        ->once()
        ->andReturn(false);
    app()->instance(CloudApiClient::class, $mock);

    Livewire::test(Tunnel::class)
        ->set('subdomain', 'taken')
        ->call('checkAvailability')
        ->assertSet('subdomainAvailable', false);
});

it('provisions tunnel via cloud api and starts it', function () {
    $cloudMock = Mockery::mock(CloudApiClient::class);
    $cloudMock->shouldReceive('provisionTunnel')
        ->with('device-uuid-123', 'testuser')
        ->once()
        ->andReturn([
            'tunnel_id' => 'cf-tunnel-id',
            'tunnel_token' => 'test-jwt-token',
        ]);
    app()->instance(CloudApiClient::class, $cloudMock);

    $identityMock = Mockery::mock(DeviceIdentityService::class);
    $identityMock->shouldReceive('getDeviceInfo')
        ->once()
        ->andReturn(new DeviceInfo(id: 'device-uuid-123', hardwareSerial: 'abc', manufacturedAt: '2025-01-01', firmwareVersion: '1.0.0'));
    app()->instance(DeviceIdentityService::class, $identityMock);

    $tunnelMock = Mockery::mock(TunnelService::class);
    $tunnelMock->shouldReceive('start')
        ->once()
        ->andReturn(null);
    app()->instance(TunnelService::class, $tunnelMock);

    Livewire::test(Tunnel::class)
        ->set('subdomain', 'testuser')
        ->set('subdomainAvailable', true)
        ->call('setupTunnel')
        ->assertSet('status', 'active')
        ->assertSet('tunnelActive', true);

    $config = TunnelConfig::current();
    expect($config)->not->toBeNull()
        ->and($config->tunnel_id)->toBe('cf-tunnel-id')
        ->and($config->tunnel_token_encrypted)->toBe('test-jwt-token')
        ->and($config->subdomain)->toBe('testuser')
        ->and($config->status)->toBe('active');
});

it('shows error when cloud provisioning fails', function () {
    $cloudMock = Mockery::mock(CloudApiClient::class);
    $cloudMock->shouldReceive('provisionTunnel')
        ->once()
        ->andThrow(new \Exception('Cloud API error'));
    app()->instance(CloudApiClient::class, $cloudMock);

    $identityMock = Mockery::mock(DeviceIdentityService::class);
    $identityMock->shouldReceive('getDeviceInfo')
        ->once()
        ->andReturn(new DeviceInfo(id: 'device-uuid-123', hardwareSerial: 'abc', manufacturedAt: '2025-01-01', firmwareVersion: '1.0.0'));
    app()->instance(DeviceIdentityService::class, $identityMock);

    Livewire::test(Tunnel::class)
        ->set('subdomain', 'testuser')
        ->set('subdomainAvailable', true)
        ->call('setupTunnel')
        ->assertSet('status', 'error')
        ->assertSee('Failed to provision tunnel');
});

it('shows error when tunnel start fails after provisioning', function () {
    $cloudMock = Mockery::mock(CloudApiClient::class);
    $cloudMock->shouldReceive('provisionTunnel')
        ->once()
        ->andReturn([
            'tunnel_id' => 'cf-tunnel-id',
            'tunnel_token' => 'test-jwt-token',
        ]);
    app()->instance(CloudApiClient::class, $cloudMock);

    $identityMock = Mockery::mock(DeviceIdentityService::class);
    $identityMock->shouldReceive('getDeviceInfo')
        ->once()
        ->andReturn(new DeviceInfo(id: 'device-uuid-123', hardwareSerial: 'abc', manufacturedAt: '2025-01-01', firmwareVersion: '1.0.0'));
    app()->instance(DeviceIdentityService::class, $identityMock);

    $tunnelMock = Mockery::mock(TunnelService::class);
    $tunnelMock->shouldReceive('start')
        ->once()
        ->andReturn('Failed to start cloudflared.');
    $tunnelMock->shouldReceive('cleanup')->once();
    app()->instance(TunnelService::class, $tunnelMock);

    Livewire::test(Tunnel::class)
        ->set('subdomain', 'testuser')
        ->set('subdomainAvailable', true)
        ->call('setupTunnel')
        ->assertSet('status', 'error')
        ->assertSee('Tunnel provisioned but failed to start');
});

it('completes the tunnel step', function () {
    Livewire::test(Tunnel::class)
        ->call('complete')
        ->assertDispatched('step-completed');

    expect(app(WizardProgressService::class)->isStepCompleted(WizardStep::Tunnel))->toBeTrue();
});

it('skips the tunnel step', function () {
    Livewire::test(Tunnel::class)
        ->call('skip')
        ->assertDispatched('step-skipped');
});
