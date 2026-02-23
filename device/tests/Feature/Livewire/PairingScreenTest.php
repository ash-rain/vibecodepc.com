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
