<?php

use App\Services\CloudApiClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use VibecodePC\Common\DTOs\DeviceStatusResult;
use VibecodePC\Common\DTOs\PairingResult;
use VibecodePC\Common\Enums\DeviceStatus;

it('getDeviceStatus returns a DeviceStatusResult', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = 'https://vibecodepc.test';

    Http::fake([
        "{$cloudUrl}/api/devices/{$uuid}/status" => Http::response([
            'device_id' => $uuid,
            'status' => 'unclaimed',
            'pairing' => null,
        ]),
    ]);

    $client = new CloudApiClient($cloudUrl);
    $result = $client->getDeviceStatus($uuid);

    expect($result)->toBeInstanceOf(DeviceStatusResult::class)
        ->and($result->deviceId)->toBe($uuid)
        ->and($result->status)->toBe(DeviceStatus::Unclaimed)
        ->and($result->pairing)->toBeNull();
});

it('getDeviceStatus handles pairing data', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = 'https://vibecodepc.test';

    Http::fake([
        "{$cloudUrl}/api/devices/{$uuid}/status" => Http::response([
            'device_id' => $uuid,
            'status' => 'claimed',
            'pairing' => [
                'device_id' => $uuid,
                'token' => '1|abc123',
                'username' => 'testuser',
                'email' => 'test@example.com',
                'ip_hint' => '192.168.1.100',
            ],
        ]),
    ]);

    $client = new CloudApiClient($cloudUrl);
    $result = $client->getDeviceStatus($uuid);

    expect($result->status)->toBe(DeviceStatus::Claimed)
        ->and($result->pairing)->toBeInstanceOf(PairingResult::class)
        ->and($result->pairing->token)->toBe('1|abc123')
        ->and($result->pairing->username)->toBe('testuser')
        ->and($result->pairing->email)->toBe('test@example.com')
        ->and($result->pairing->ipHint)->toBe('192.168.1.100');
});

it('throws on HTTP errors', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = 'https://vibecodepc.test';

    Http::fake([
        "{$cloudUrl}/api/devices/{$uuid}/status" => Http::response(
            ['message' => 'Device not found'],
            404,
        ),
    ]);

    $client = new CloudApiClient($cloudUrl);
    $client->getDeviceStatus($uuid);
})->throws(RequestException::class);

it('throws on server errors', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = 'https://vibecodepc.test';

    Http::fake([
        "{$cloudUrl}/api/devices/{$uuid}/status" => Http::response(
            ['message' => 'Internal server error'],
            500,
        ),
    ]);

    $client = new CloudApiClient($cloudUrl);
    $client->getDeviceStatus($uuid);
})->throws(RequestException::class);
