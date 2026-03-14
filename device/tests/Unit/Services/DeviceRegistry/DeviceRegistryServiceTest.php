<?php

declare(strict_types=1);

use App\Services\CloudApiClient;
use App\Services\DeviceRegistry\DeviceRegistryService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use VibecodePC\Common\DTOs\DeviceInfo;
use VibecodePC\Common\DTOs\DeviceStatusResult;
use VibecodePC\Common\Enums\DeviceStatus;

beforeEach(function () {
    $this->cloudApi = new CloudApiClient('https://vibecodepc.test');
    $this->service = new DeviceRegistryService($this->cloudApi);
});

describe('registerDeviceWithRetry', function () {
    it('registers device successfully on first attempt', function () {
        $device = new DeviceInfo(
            id: 'test-device-id',
            hardwareSerial: 'serial-123',
            manufacturedAt: now()->toIso8601String(),
            firmwareVersion: '1.0.0',
        );

        Http::fake([
            'https://vibecodepc.test/api/devices/register' => Http::response(null, 201),
        ]);

        expect(fn () => $this->service->registerDeviceWithRetry($device))
            ->not->toThrow(\RuntimeException::class);

        Http::assertSent(fn ($request) => $request->url() === 'https://vibecodepc.test/api/devices/register');
    });

    it('retries on connection exception and eventually succeeds', function () {
        $device = new DeviceInfo(
            id: 'test-device-id',
            hardwareSerial: 'serial-123',
            manufacturedAt: now()->toIso8601String(),
            firmwareVersion: '1.0.0',
        );

        $callCount = 0;
        Http::fake([
            'https://vibecodepc.test/api/devices/register' => function () use (&$callCount) {
                $callCount++;
                if ($callCount < 2) {
                    throw new ConnectionException('Connection refused');
                }

                return Http::response(null, 201);
            },
        ]);

        expect(fn () => $this->service->registerDeviceWithRetry($device))
            ->not->toThrow(\RuntimeException::class);

        expect($callCount)->toBe(2);
    });

    it('retries on server errors and eventually succeeds', function () {
        $device = new DeviceInfo(
            id: 'test-device-id',
            hardwareSerial: 'serial-123',
            manufacturedAt: now()->toIso8601String(),
            firmwareVersion: '1.0.0',
        );

        $callCount = 0;
        Http::fake([
            'https://vibecodepc.test/api/devices/register' => function () use (&$callCount) {
                $callCount++;
                if ($callCount < 2) {
                    return Http::response(['message' => 'Service unavailable'], 503);
                }

                return Http::response(null, 201);
            },
        ]);

        expect(fn () => $this->service->registerDeviceWithRetry($device))
            ->not->toThrow(\RuntimeException::class);

        expect($callCount)->toBe(2);
    });

    it('fails after maximum retries on persistent connection errors', function () {
        $device = new DeviceInfo(
            id: 'test-device-id',
            hardwareSerial: 'serial-123',
            manufacturedAt: now()->toIso8601String(),
            firmwareVersion: '1.0.0',
        );

        Http::fake([
            'https://vibecodepc.test/api/devices/register' => function () {
                throw new ConnectionException('Connection refused');
            },
        ]);

        expect(fn () => $this->service->registerDeviceWithRetry($device))
            ->toThrow(RuntimeException::class, 'Failed to register device after 3 attempts');
    });

    it('fails immediately on non-retryable errors', function () {
        $device = new DeviceInfo(
            id: 'test-device-id',
            hardwareSerial: 'serial-123',
            manufacturedAt: now()->toIso8601String(),
            firmwareVersion: '1.0.0',
        );

        $callCount = 0;
        Http::fake([
            'https://vibecodepc.test/api/devices/register' => function () use (&$callCount) {
                $callCount++;

                return Http::response(['message' => 'Bad request'], 400);
            },
        ]);

        expect(fn () => $this->service->registerDeviceWithRetry($device))
            ->toThrow(RuntimeException::class);

        expect($callCount)->toBe(1);
    });
});

describe('getDeviceStatusWithRetry', function () {
    it('returns device status successfully on first attempt', function () {
        $uuid = (string) Str::uuid();

        Http::fake([
            "https://vibecodepc.test/api/devices/{$uuid}/status" => Http::response([
                'device_id' => $uuid,
                'status' => 'unclaimed',
                'pairing' => null,
            ]),
        ]);

        $result = $this->service->getDeviceStatusWithRetry($uuid);

        expect($result)->toBeInstanceOf(DeviceStatusResult::class)
            ->and($result->deviceId)->toBe($uuid)
            ->and($result->status)->toBe(DeviceStatus::Unclaimed);
    });

    it('retries on connection exception and eventually succeeds', function () {
        $uuid = (string) Str::uuid();

        $callCount = 0;
        Http::fake([
            "https://vibecodepc.test/api/devices/{$uuid}/status" => function () use (&$callCount, $uuid) {
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

        $result = $this->service->getDeviceStatusWithRetry($uuid);

        expect($result)->toBeInstanceOf(DeviceStatusResult::class)
            ->and($callCount)->toBe(2);
    });

    it('retries on rate limit errors and eventually succeeds', function () {
        $uuid = (string) Str::uuid();

        $callCount = 0;
        Http::fake([
            "https://vibecodepc.test/api/devices/{$uuid}/status" => function () use (&$callCount, $uuid) {
                $callCount++;
                if ($callCount < 2) {
                    return Http::response(['message' => 'Too many requests'], 429);
                }

                return Http::response([
                    'device_id' => $uuid,
                    'status' => 'unclaimed',
                    'pairing' => null,
                ]);
            },
        ]);

        $result = $this->service->getDeviceStatusWithRetry($uuid);

        expect($result)->toBeInstanceOf(DeviceStatusResult::class)
            ->and($callCount)->toBe(2);
    });

    it('fails after maximum retries on persistent server errors', function () {
        $uuid = (string) Str::uuid();

        Http::fake([
            "https://vibecodepc.test/api/devices/{$uuid}/status" => Http::response(
                ['message' => 'Gateway timeout'],
                504
            ),
        ]);

        expect(fn () => $this->service->getDeviceStatusWithRetry($uuid))
            ->toThrow(RuntimeException::class, 'Failed to get device status after 3 attempts');
    });

    it('fails immediately on 404 errors', function () {
        $uuid = (string) Str::uuid();

        $callCount = 0;
        Http::fake([
            "https://vibecodepc.test/api/devices/{$uuid}/status" => function () use (&$callCount) {
                $callCount++;

                return Http::response(['message' => 'Not found'], 404);
            },
        ]);

        expect(fn () => $this->service->getDeviceStatusWithRetry($uuid))
            ->toThrow(RuntimeException::class);

        expect($callCount)->toBe(1);
    });

    it('handles pairing data in response', function () {
        $uuid = (string) Str::uuid();

        Http::fake([
            "https://vibecodepc.test/api/devices/{$uuid}/status" => Http::response([
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

        $result = $this->service->getDeviceStatusWithRetry($uuid);

        expect($result->status)->toBe(DeviceStatus::Claimed)
            ->and($result->pairing)->not->toBeNull()
            ->and($result->pairing->token)->toBe('1|abc123')
            ->and($result->pairing->username)->toBe('testuser');
    });
});

describe('checkPairingStatusSafe', function () {
    it('returns device status when successful', function () {
        $uuid = (string) Str::uuid();

        Http::fake([
            "https://vibecodepc.test/api/devices/{$uuid}/status" => Http::response([
                'device_id' => $uuid,
                'status' => 'claimed',
                'pairing' => null,
            ]),
        ]);

        $result = $this->service->checkPairingStatusSafe($uuid);

        expect($result)->toBeInstanceOf(DeviceStatusResult::class)
            ->and($result->status)->toBe(DeviceStatus::Claimed);
    });

    it('returns null on failure instead of throwing', function () {
        $uuid = (string) Str::uuid();

        Http::fake([
            "https://vibecodepc.test/api/devices/{$uuid}/status" => Http::response(
                ['message' => 'Not found'],
                404
            ),
        ]);

        $result = $this->service->checkPairingStatusSafe($uuid);

        expect($result)->toBeNull();
    });

    it('returns null after all retries are exhausted', function () {
        $uuid = (string) Str::uuid();

        Http::fake([
            "https://vibecodepc.test/api/devices/{$uuid}/status" => Http::response(
                ['message' => 'Gateway timeout'],
                504
            ),
        ]);

        $result = $this->service->checkPairingStatusSafe($uuid);

        expect($result)->toBeNull();
    });
});

describe('registerDeviceSafe', function () {
    it('returns true on successful registration', function () {
        $device = new DeviceInfo(
            id: 'test-device-id',
            hardwareSerial: 'serial-123',
            manufacturedAt: now()->toIso8601String(),
            firmwareVersion: '1.0.0',
        );

        Http::fake([
            'https://vibecodepc.test/api/devices/register' => Http::response(null, 201),
        ]);

        $result = $this->service->registerDeviceSafe($device);

        expect($result)->toBeTrue();
    });

    it('returns false on failure instead of throwing', function () {
        $device = new DeviceInfo(
            id: 'test-device-id',
            hardwareSerial: 'serial-123',
            manufacturedAt: now()->toIso8601String(),
            firmwareVersion: '1.0.0',
        );

        Http::fake([
            'https://vibecodepc.test/api/devices/register' => Http::response(
                ['message' => 'Bad request'],
                400
            ),
        ]);

        $result = $this->service->registerDeviceSafe($device);

        expect($result)->toBeFalse();
    });

    it('returns false after all retries are exhausted', function () {
        $device = new DeviceInfo(
            id: 'test-device-id',
            hardwareSerial: 'serial-123',
            manufacturedAt: now()->toIso8601String(),
            firmwareVersion: '1.0.0',
        );

        Http::fake([
            'https://vibecodepc.test/api/devices/register' => Http::response(
                ['message' => 'Gateway timeout'],
                504
            ),
        ]);

        $result = $this->service->registerDeviceSafe($device);

        expect($result)->toBeFalse();
    });
});

describe('retryable status codes', function () {
    it('retries on 408 Request Timeout', function () {
        $uuid = (string) Str::uuid();

        $callCount = 0;
        Http::fake([
            "https://vibecodepc.test/api/devices/{$uuid}/status" => function () use (&$callCount, $uuid) {
                $callCount++;
                if ($callCount < 2) {
                    return Http::response(['message' => 'Timeout'], 408);
                }

                return Http::response([
                    'device_id' => $uuid,
                    'status' => 'unclaimed',
                    'pairing' => null,
                ]);
            },
        ]);

        $result = $this->service->getDeviceStatusWithRetry($uuid);

        expect($result)->toBeInstanceOf(DeviceStatusResult::class)
            ->and($callCount)->toBe(2);
    });

    it('retries on 502 Bad Gateway', function () {
        $uuid = (string) Str::uuid();

        $callCount = 0;
        Http::fake([
            "https://vibecodepc.test/api/devices/{$uuid}/status" => function () use (&$callCount, $uuid) {
                $callCount++;
                if ($callCount < 2) {
                    return Http::response(['message' => 'Bad gateway'], 502);
                }

                return Http::response([
                    'device_id' => $uuid,
                    'status' => 'unclaimed',
                    'pairing' => null,
                ]);
            },
        ]);

        $result = $this->service->getDeviceStatusWithRetry($uuid);

        expect($result)->toBeInstanceOf(DeviceStatusResult::class)
            ->and($callCount)->toBe(2);
    });

    it('retries on 503 Service Unavailable', function () {
        $uuid = (string) Str::uuid();

        $callCount = 0;
        Http::fake([
            "https://vibecodepc.test/api/devices/{$uuid}/status" => function () use (&$callCount, $uuid) {
                $callCount++;
                if ($callCount < 2) {
                    return Http::response(['message' => 'Service unavailable'], 503);
                }

                return Http::response([
                    'device_id' => $uuid,
                    'status' => 'unclaimed',
                    'pairing' => null,
                ]);
            },
        ]);

        $result = $this->service->getDeviceStatusWithRetry($uuid);

        expect($result)->toBeInstanceOf(DeviceStatusResult::class)
            ->and($callCount)->toBe(2);
    });

    it('does not retry on 401 Unauthorized', function () {
        $uuid = (string) Str::uuid();

        $callCount = 0;
        Http::fake([
            "https://vibecodepc.test/api/devices/{$uuid}/status" => function () use (&$callCount) {
                $callCount++;

                return Http::response(['message' => 'Unauthorized'], 401);
            },
        ]);

        expect(fn () => $this->service->getDeviceStatusWithRetry($uuid))
            ->toThrow(RuntimeException::class);

        expect($callCount)->toBe(1);
    });

    it('does not retry on 403 Forbidden', function () {
        $uuid = (string) Str::uuid();

        $callCount = 0;
        Http::fake([
            "https://vibecodepc.test/api/devices/{$uuid}/status" => function () use (&$callCount) {
                $callCount++;

                return Http::response(['message' => 'Forbidden'], 403);
            },
        ]);

        expect(fn () => $this->service->getDeviceStatusWithRetry($uuid))
            ->toThrow(RuntimeException::class);

        expect($callCount)->toBe(1);
    });
});
