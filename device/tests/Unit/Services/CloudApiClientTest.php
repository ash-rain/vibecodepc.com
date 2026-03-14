<?php

use App\Services\CloudApiClient;
use Illuminate\Http\Client\ConnectionException;
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

it('retries on connection exceptions and eventually succeeds', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = 'https://vibecodepc.test';
    $callCount = 0;

    Http::fake([
        "{$cloudUrl}/api/devices/{$uuid}/status" => function () use (&$callCount, $uuid) {
            $callCount++;
            if ($callCount < 2) {
                throw new ConnectionException('Connection refused');
            }

            return Http::response([
                'device_id' => $uuid,
                'status' => 'unclaimed',
                'pairing' => null,
            ]);
        },
    ]);

    $client = new CloudApiClient($cloudUrl);
    $result = $client->getDeviceStatus($uuid);

    expect($callCount)->toBe(2)
        ->and($result)->toBeInstanceOf(DeviceStatusResult::class);
});

it('retries on transient http errors and eventually succeeds', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = 'https://vibecodepc.test';
    $callCount = 0;

    Http::fake([
        "{$cloudUrl}/api/devices/{$uuid}/status" => function () use (&$callCount, $uuid) {
            $callCount++;
            if ($callCount < 2) {
                return Http::response(['error' => 'Service temporarily unavailable'], 503);
            }

            return Http::response([
                'device_id' => $uuid,
                'status' => 'unclaimed',
                'pairing' => null,
            ]);
        },
    ]);

    $client = new CloudApiClient($cloudUrl);
    $result = $client->getDeviceStatus($uuid);

    expect($callCount)->toBe(2)
        ->and($result)->toBeInstanceOf(DeviceStatusResult::class);
});

it('retries up to maximum attempts before giving up on connection errors', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = 'https://vibecodepc.test';
    $callCount = 0;

    Http::fake([
        "{$cloudUrl}/api/devices/{$uuid}/status" => function () use (&$callCount) {
            $callCount++;
            throw new ConnectionException('Network unreachable');
        },
    ]);

    $client = new CloudApiClient($cloudUrl);

    try {
        $client->getDeviceStatus($uuid);
        $this->fail('Expected exception was not thrown');
    } catch (ConnectionException $e) {
        expect($callCount)->toBe(4); // Initial + 3 retries
        expect($e->getMessage())->toContain('Network unreachable');
    }
});

it('retries up to maximum attempts before giving up on server errors', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = 'https://vibecodepc.test';
    $callCount = 0;

    Http::fake([
        "{$cloudUrl}/api/devices/{$uuid}/status" => function () use (&$callCount) {
            $callCount++;

            return Http::response(['error' => 'Bad Gateway'], 502);
        },
    ]);

    $client = new CloudApiClient($cloudUrl);

    try {
        $client->getDeviceStatus($uuid);
        $this->fail('Expected exception was not thrown');
    } catch (RequestException $e) {
        expect($callCount)->toBe(4); // Initial + 3 retries
        expect($e->response->status())->toBe(502);
    }
});

it('does not retry on client errors that are not transient', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = 'https://vibecodepc.test';
    $callCount = 0;

    Http::fake([
        "{$cloudUrl}/api/devices/{$uuid}/status" => function () use (&$callCount) {
            $callCount++;

            return Http::response(['error' => 'Bad Request'], 400);
        },
    ]);

    $client = new CloudApiClient($cloudUrl);

    try {
        $client->getDeviceStatus($uuid);
        $this->fail('Expected exception was not thrown');
    } catch (RequestException $e) {
        expect($callCount)->toBe(1) // No retries for 400 errors
            ->and($e->response->status())->toBe(400);
    }
});

it('retries on 429 rate limit errors', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = 'https://vibecodepc.test';
    $callCount = 0;

    Http::fake([
        "{$cloudUrl}/api/devices/{$uuid}/status" => function () use (&$callCount, $uuid) {
            $callCount++;
            if ($callCount < 2) {
                return Http::response(['error' => 'Too Many Requests'], 429);
            }

            return Http::response([
                'device_id' => $uuid,
                'status' => 'unclaimed',
                'pairing' => null,
            ]);
        },
    ]);

    $client = new CloudApiClient($cloudUrl);
    $result = $client->getDeviceStatus($uuid);

    expect($callCount)->toBe(2)
        ->and($result)->toBeInstanceOf(DeviceStatusResult::class);
});

it('retries on 408 request timeout errors', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = 'https://vibecodepc.test';
    $callCount = 0;

    Http::fake([
        "{$cloudUrl}/api/devices/{$uuid}/status" => function () use (&$callCount, $uuid) {
            $callCount++;
            if ($callCount < 2) {
                return Http::response(['error' => 'Request Timeout'], 408);
            }

            return Http::response([
                'device_id' => $uuid,
                'status' => 'unclaimed',
                'pairing' => null,
            ]);
        },
    ]);

    $client = new CloudApiClient($cloudUrl);
    $result = $client->getDeviceStatus($uuid);

    expect($callCount)->toBe(2)
        ->and($result)->toBeInstanceOf(DeviceStatusResult::class);
});
