<?php

use App\Livewire\Pairing\PairingScreen;
use App\Services\CloudApiClient;
use App\Services\DeviceRegistry\DeviceIdentityService;
use App\Services\DeviceStateService;
use App\Services\NetworkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use VibecodePC\Common\DTOs\DeviceInfo;
use VibecodePC\Common\DTOs\DeviceStatusResult;
use VibecodePC\Common\DTOs\PairingResult;
use VibecodePC\Common\Enums\DeviceStatus;

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
    $identity->shouldReceive('getPairingUrl')->andReturn("https://vibecodepc.com/id/{$uuid}");

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

function mockCloudApiClient(?DeviceStatusResult $statusResult = null): void
{
    $client = Mockery::mock(CloudApiClient::class);
    $client->shouldReceive('registerDevice')->andReturnNull();

    if ($statusResult) {
        $client->shouldReceive('getDeviceStatus')->andReturn($statusResult);
    } else {
        $client->shouldReceive('getDeviceStatus')->andReturn(
            new DeviceStatusResult(
                deviceId: 'test',
                status: DeviceStatus::Unclaimed,
            ),
        );
    }

    app()->instance(CloudApiClient::class, $client);
}

function mockDeviceStateService(): void
{
    $stateService = Mockery::mock(DeviceStateService::class);
    $stateService->shouldReceive('setMode')->andReturnNull();

    app()->instance(DeviceStateService::class, $stateService);
}

it('renders successfully', function () {
    $uuid = (string) Str::uuid();
    mockDeviceIdentityForLivewire($uuid);
    mockNetworkService();
    mockCloudApiClient();

    Livewire::test(PairingScreen::class)
        ->assertStatus(200);
});

it('shows device ID and pairing URL', function () {
    $uuid = (string) Str::uuid();
    mockDeviceIdentityForLivewire($uuid);
    mockNetworkService();
    mockCloudApiClient();

    Livewire::test(PairingScreen::class)
        ->assertSee(Str::limit($uuid, 16))
        ->assertSee("https://vibecodepc.com/id/{$uuid}");
});

it('shows QR code SVG', function () {
    $uuid = (string) Str::uuid();
    mockDeviceIdentityForLivewire($uuid);
    mockNetworkService();
    mockCloudApiClient();

    Livewire::test(PairingScreen::class)
        ->assertSeeHtml('<svg');
});

it('displays network information', function () {
    $uuid = (string) Str::uuid();
    mockDeviceIdentityForLivewire($uuid);
    mockNetworkService('10.0.0.42', true);
    mockCloudApiClient();

    Livewire::test(PairingScreen::class)
        ->assertSee('10.0.0.42')
        ->assertSee('Connected');
});

it('shows no connection when internet is unavailable', function () {
    $uuid = (string) Str::uuid();
    mockDeviceIdentityForLivewire($uuid);
    mockNetworkService('192.168.1.50', false);
    mockCloudApiClient();

    Livewire::test(PairingScreen::class)
        ->assertSee('No connection');
});

it('checkPairingStatus redirects when paired', function () {
    $uuid = (string) Str::uuid();
    mockDeviceIdentityForLivewire($uuid);
    mockNetworkService();
    mockDeviceStateService();

    mockCloudApiClient(new DeviceStatusResult(
        deviceId: $uuid,
        status: DeviceStatus::Claimed,
        pairing: new PairingResult(
            deviceId: $uuid,
            token: '1|abc123',
            username: 'testuser',
            email: 'test@example.com',
        ),
    ));

    Livewire::test(PairingScreen::class)
        ->call('checkPairingStatus')
        ->assertRedirect('/');
});

it('checkPairingStatus does not redirect when not paired', function () {
    $uuid = (string) Str::uuid();
    mockDeviceIdentityForLivewire($uuid);
    mockNetworkService();
    mockCloudApiClient();

    Livewire::test(PairingScreen::class)
        ->call('checkPairingStatus')
        ->assertNoRedirect();
});
