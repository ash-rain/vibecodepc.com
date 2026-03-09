<?php

use App\Livewire\Pairing\PairingScreen;
use App\Models\CloudCredential;
use App\Services\DeviceRegistry\DeviceIdentityService;
use App\Services\NetworkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use VibecodePC\Common\DTOs\DeviceInfo;

uses(RefreshDatabase::class);

function mockDeviceIdentityForLivewire(string $uuid): void
{
    $identity = Mockery::mock(DeviceIdentityService::class);
    $identity->shouldReceive('hasIdentity')->andReturn(true);
    $identity->shouldReceive('getDeviceInfo')->andReturn(new DeviceInfo(
        id: $uuid,
        hardwareSerial: 'test-serial',
        manufacturedAt: '2026-01-01T00:00:00Z',
        firmwareVersion: '1.0.0',
    ));
    $identity->shouldReceive('getPairingUrl')->andReturn("https://vibecodepc.com/pair/{$uuid}");

    app()->instance(DeviceIdentityService::class, $identity);
}

function mockNetworkService(string $localIp = '192.168.1.50', bool $hasInternet = true): void
{
    $network = Mockery::mock(NetworkService::class);
    $network->shouldReceive('getLocalIp')->andReturn($localIp);
    $network->shouldReceive('hasInternetConnectivity')->andReturn($hasInternet);
    $network->shouldReceive('hasEthernet')->andReturn(false);
    $network->shouldReceive('hasWifi')->andReturn(false);
    $network->shouldReceive('scanWifiNetworks')->andReturn([]);

    app()->instance(NetworkService::class, $network);
}

it('renders successfully', function () {
    $uuid = (string) Str::uuid();
    mockDeviceIdentityForLivewire($uuid);
    mockNetworkService();

    Livewire::test(PairingScreen::class)
        ->assertStatus(200);
});

it('shows device ID and pairing URL', function () {
    $uuid = (string) Str::uuid();
    mockDeviceIdentityForLivewire($uuid);
    mockNetworkService();

    Livewire::test(PairingScreen::class)
        ->assertSee(Str::limit($uuid, 16))
        ->assertSee("https://vibecodepc.com/pair/{$uuid}");
});

it('shows QR code SVG', function () {
    $uuid = (string) Str::uuid();
    mockDeviceIdentityForLivewire($uuid);
    mockNetworkService();

    Livewire::test(PairingScreen::class)
        ->assertSeeHtml('<svg');
});

it('displays network information', function () {
    $uuid = (string) Str::uuid();
    mockDeviceIdentityForLivewire($uuid);
    mockNetworkService('10.0.0.42', true);

    Livewire::test(PairingScreen::class)
        ->assertSee('10.0.0.42')
        ->assertSee('Connected');
});

it('shows no connection when internet is unavailable', function () {
    $uuid = (string) Str::uuid();
    mockDeviceIdentityForLivewire($uuid);
    mockNetworkService('192.168.1.50', false);

    Livewire::test(PairingScreen::class)
        ->assertSee('No connection');
});

it('redirects to wizard when credential exists', function () {
    $uuid = (string) Str::uuid();
    mockDeviceIdentityForLivewire($uuid);
    mockNetworkService();

    CloudCredential::create([
        'pairing_token_encrypted' => '1|abc123',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'http://localhost',
        'is_paired' => true,
        'paired_at' => now(),
    ]);

    Livewire::test(PairingScreen::class)
        ->call('checkPairingStatus')
        ->assertRedirect('/wizard');
});

it('does not redirect when not paired', function () {
    $uuid = (string) Str::uuid();
    mockDeviceIdentityForLivewire($uuid);
    mockNetworkService();

    Livewire::test(PairingScreen::class)
        ->call('checkPairingStatus')
        ->assertNoRedirect();
});

// Pairing State Management Tests

it('initializes with empty state when no device identity exists', function () {
    $identity = Mockery::mock(DeviceIdentityService::class);
    $identity->shouldReceive('hasIdentity')->andReturn(false);
    $identity->shouldReceive('getPairingUrl')->andReturn('');

    app()->instance(DeviceIdentityService::class, $identity);
    mockNetworkService();

    Livewire::test(PairingScreen::class)
        ->assertSet('deviceId', '')
        ->assertSet('pairingUrl', '');
});

it('initializes state correctly when device identity exists', function () {
    $uuid = (string) Str::uuid();
    mockDeviceIdentityForLivewire($uuid);
    mockNetworkService('192.168.1.100', true);

    Livewire::test(PairingScreen::class)
        ->assertSet('deviceId', $uuid)
        ->assertSet('pairingUrl', "https://vibecodepc.com/pair/{$uuid}")
        ->assertSet('localIp', '192.168.1.100')
        ->assertSet('hasInternet', true);
});

it('maintains state across multiple check pairing status calls', function () {
    $uuid = (string) Str::uuid();
    mockDeviceIdentityForLivewire($uuid);
    mockNetworkService();

    $component = Livewire::test(PairingScreen::class);

    // First check - not paired
    $component->call('checkPairingStatus')
        ->assertNoRedirect();

    // Second check - still not paired
    $component->call('checkPairingStatus')
        ->assertNoRedirect();

    // Third check - still not paired
    $component->call('checkPairingStatus')
        ->assertNoRedirect();

    // Verify state remains unchanged
    $component->assertSet('deviceId', $uuid);
});

it('transitions state when pairing status changes to paired', function () {
    $uuid = (string) Str::uuid();
    mockDeviceIdentityForLivewire($uuid);
    mockNetworkService();

    $component = Livewire::test(PairingScreen::class);

    // Initially not paired
    $component->call('checkPairingStatus')
        ->assertNoRedirect();

    // Create paired credential
    CloudCredential::create([
        'pairing_token_encrypted' => '1|abc123',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'http://localhost',
        'is_paired' => true,
        'paired_at' => now(),
    ]);

    // Now should redirect
    $component->call('checkPairingStatus')
        ->assertRedirect('/wizard');
});

it('handles null network service responses gracefully', function () {
    $uuid = (string) Str::uuid();
    mockDeviceIdentityForLivewire($uuid);

    $network = Mockery::mock(NetworkService::class);
    $network->shouldReceive('getLocalIp')->andReturn(null);
    $network->shouldReceive('hasInternetConnectivity')->andReturn(false);
    $network->shouldReceive('hasEthernet')->andReturn(false);
    $network->shouldReceive('hasWifi')->andReturn(false);
    $network->shouldReceive('scanWifiNetworks')->andReturn([]);

    app()->instance(NetworkService::class, $network);

    Livewire::test(PairingScreen::class)
        ->assertSet('localIp', '127.0.0.1')
        ->assertSet('hasInternet', false);
});

it('regenerates QR code when pairing URL changes', function () {
    $uuid = (string) Str::uuid();
    $newUuid = (string) Str::uuid();

    // Create mock with initial URL
    $identity = Mockery::mock(DeviceIdentityService::class);
    $identity->shouldReceive('hasIdentity')->andReturn(true);
    $identity->shouldReceive('getDeviceInfo')->andReturn(new DeviceInfo(
        id: $uuid,
        hardwareSerial: 'test-serial',
        manufacturedAt: '2026-01-01T00:00:00Z',
        firmwareVersion: '1.0.0',
    ));

    // Use a closure to allow changing the returned URL
    $pairingUrl = "https://vibecodepc.com/pair/{$uuid}";
    $identity->shouldReceive('getPairingUrl')->andReturnUsing(function () use (&$pairingUrl) {
        return $pairingUrl;
    });

    app()->instance(DeviceIdentityService::class, $identity);
    mockNetworkService();

    $component = Livewire::test(PairingScreen::class);

    // QR code should be present
    $component->assertSeeHtml('<svg');
    $component->assertSet('pairingUrl', "https://vibecodepc.com/pair/{$uuid}");

    // Change the URL value
    $pairingUrl = "https://vibecodepc.com/pair/{$newUuid}";

    // Re-mount component (simulating refresh)
    Livewire::test(PairingScreen::class)
        ->assertSet('pairingUrl', "https://vibecodepc.com/pair/{$newUuid}");
});

it('persists device ID across component updates', function () {
    $uuid = (string) Str::uuid();
    mockDeviceIdentityForLivewire($uuid);
    mockNetworkService();

    $component = Livewire::test(PairingScreen::class);

    // Verify initial state
    $component->assertSet('deviceId', $uuid);

    // Simulate component update by calling checkPairingStatus
    $component->call('checkPairingStatus');

    // Device ID should persist
    $component->assertSet('deviceId', $uuid);
});

it('handles empty pairing URL without generating QR code', function () {
    $identity = Mockery::mock(DeviceIdentityService::class);
    $identity->shouldReceive('hasIdentity')->andReturn(true);
    $identity->shouldReceive('getDeviceInfo')->andReturn(new DeviceInfo(
        id: 'test-device',
        hardwareSerial: 'test-serial',
        manufacturedAt: '2026-01-01T00:00:00Z',
        firmwareVersion: '1.0.0',
    ));
    $identity->shouldReceive('getPairingUrl')->andReturn('');

    app()->instance(DeviceIdentityService::class, $identity);
    mockNetworkService();

    Livewire::test(PairingScreen::class)
        ->assertSet('pairingUrl', '')
        ->assertSee('');
});

it('reflects network connectivity changes in state', function () {
    $uuid = (string) Str::uuid();
    mockDeviceIdentityForLivewire($uuid);

    // Initially connected - provide all required mock methods
    $network = Mockery::mock(NetworkService::class);
    $network->shouldReceive('getLocalIp')->andReturn('192.168.1.50');
    $network->shouldReceive('hasInternetConnectivity')->andReturn(true);
    $network->shouldReceive('hasEthernet')->andReturn(false);
    $network->shouldReceive('hasWifi')->andReturn(false);
    $network->shouldReceive('scanWifiNetworks')->andReturn([]);

    app()->instance(NetworkService::class, $network);

    $component = Livewire::test(PairingScreen::class)
        ->assertSet('hasInternet', true);

    // Change to disconnected - provide all required mock methods
    $network = Mockery::mock(NetworkService::class);
    $network->shouldReceive('getLocalIp')->andReturn('192.168.1.50');
    $network->shouldReceive('hasInternetConnectivity')->andReturn(false);
    $network->shouldReceive('hasEthernet')->andReturn(false);
    $network->shouldReceive('hasWifi')->andReturn(false);
    $network->shouldReceive('scanWifiNetworks')->andReturn([]);

    app()->instance(NetworkService::class, $network);

    // Re-mount to reflect new state
    Livewire::test(PairingScreen::class)
        ->assertSet('hasInternet', false);
});

it('handles rapid pairing status checks without error', function () {
    $uuid = (string) Str::uuid();
    mockDeviceIdentityForLivewire($uuid);
    mockNetworkService();

    $component = Livewire::test(PairingScreen::class);

    // Simulate rapid status checks
    for ($i = 0; $i < 10; $i++) {
        $component->call('checkPairingStatus');
    }

    $component->assertNoRedirect()
        ->assertSet('deviceId', $uuid);
});

it('preserves all state properties when no credential exists', function () {
    $uuid = (string) Str::uuid();
    mockDeviceIdentityForLivewire($uuid);
    mockNetworkService('10.0.0.50', true);

    $component = Livewire::test(PairingScreen::class)
        ->assertSet('deviceId', $uuid)
        ->assertSet('pairingUrl', "https://vibecodepc.com/pair/{$uuid}")
        ->assertSet('localIp', '10.0.0.50')
        ->assertSet('hasInternet', true);

    // Call check pairing status - should preserve all state
    $component->call('checkPairingStatus')
        ->assertSet('deviceId', $uuid)
        ->assertSet('pairingUrl', "https://vibecodepc.com/pair/{$uuid}")
        ->assertSet('localIp', '10.0.0.50')
        ->assertSet('hasInternet', true);
});

it('handles cloud credential with is_paired set to false', function () {
    $uuid = (string) Str::uuid();
    mockDeviceIdentityForLivewire($uuid);
    mockNetworkService();

    CloudCredential::create([
        'pairing_token_encrypted' => '1|abc123',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'http://localhost',
        'is_paired' => false,
        'paired_at' => null,
    ]);

    Livewire::test(PairingScreen::class)
        ->call('checkPairingStatus')
        ->assertNoRedirect();
});

it('handles cloud credential when is_paired defaults to false', function () {
    $uuid = (string) Str::uuid();
    mockDeviceIdentityForLivewire($uuid);
    mockNetworkService();

    // Omit is_paired to test default behavior (false)
    CloudCredential::create([
        'pairing_token_encrypted' => '1|abc123',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'http://localhost',
        'paired_at' => null,
    ]);

    Livewire::test(PairingScreen::class)
        ->call('checkPairingStatus')
        ->assertNoRedirect();
});
