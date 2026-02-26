<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\DeviceAlreadyClaimedException;
use App\Exceptions\DeviceNotFoundException;
use App\Models\Device;
use App\Models\User;
use App\Services\DeviceRegistryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use VibecodePC\Common\DTOs\DeviceInfo;
use VibecodePC\Common\DTOs\DeviceStatusResult;
use VibecodePC\Common\DTOs\PairingResult;
use VibecodePC\Common\Enums\DeviceStatus;

class DeviceRegistryServiceTest extends TestCase
{
    use RefreshDatabase;

    private DeviceRegistryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DeviceRegistryService;
    }

    public function test_find_by_uuid_returns_device(): void
    {
        $device = Device::factory()->create();

        $result = $this->service->findByUuid($device->uuid);

        $this->assertInstanceOf(Device::class, $result);
        $this->assertEquals($device->uuid, $result->uuid);
    }

    public function test_find_by_uuid_throws_not_found_exception_for_invalid_uuid(): void
    {
        $this->expectException(DeviceNotFoundException::class);

        $this->service->findByUuid(Str::uuid()->toString());
    }

    public function test_get_device_status_returns_correct_dto(): void
    {
        $device = Device::factory()->create();

        $result = $this->service->getDeviceStatus($device->uuid);

        $this->assertInstanceOf(DeviceStatusResult::class, $result);
        $this->assertEquals($device->uuid, $result->deviceId);
        $this->assertEquals(DeviceStatus::Unclaimed, $result->status);
        $this->assertNull($result->pairing);
    }

    public function test_get_device_status_includes_pairing_data_when_token_exists_and_clears_it(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->claimed($user)->create([
            'pairing_token_encrypted' => 'device-pairing-token-xyz',
        ]);

        $result = $this->service->getDeviceStatus($device->uuid);

        $this->assertInstanceOf(DeviceStatusResult::class, $result);
        $this->assertEquals(DeviceStatus::Claimed, $result->status);
        $this->assertNotNull($result->pairing);
        $this->assertInstanceOf(PairingResult::class, $result->pairing);
        $this->assertEquals($device->uuid, $result->pairing->deviceId);
        $this->assertEquals('device-pairing-token-xyz', $result->pairing->token);
        $this->assertEquals($user->username, $result->pairing->username);
        $this->assertEquals($user->email, $result->pairing->email);

        // Token should be cleared after retrieval
        $device->refresh();
        $this->assertNull($device->pairing_token_encrypted);
    }

    public function test_claim_device_updates_device_and_returns_pairing_result(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->create();
        $ipHint = '192.168.1.100';

        $result = $this->service->claimDevice($device->uuid, $user, $ipHint);

        $this->assertInstanceOf(PairingResult::class, $result);
        $this->assertEquals($device->uuid, $result->deviceId);
        $this->assertNotEmpty($result->token);
        $this->assertEquals($user->username, $result->username);
        $this->assertEquals($user->email, $result->email);
        $this->assertEquals($ipHint, $result->ipHint);

        // Verify the device was updated
        $device->refresh();
        $this->assertEquals(DeviceStatus::Claimed, $device->status);
        $this->assertEquals($user->id, $device->user_id);
        $this->assertNotNull($device->paired_at);
        $this->assertEquals($ipHint, $device->ip_hint);
        $this->assertNotNull($device->pairing_token_encrypted);

        // Verify a Sanctum token was created
        $this->assertCount(1, $user->tokens);
        $this->assertEquals("device:{$device->uuid}", $user->tokens->first()->name);
    }

    public function test_claim_device_throws_already_claimed_exception_for_claimed_device(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->claimed()->create();

        $this->expectException(DeviceAlreadyClaimedException::class);

        $this->service->claimDevice($device->uuid, $user);
    }

    public function test_register_device_creates_new_device_from_device_info(): void
    {
        $deviceInfo = new DeviceInfo(
            id: Str::uuid()->toString(),
            hardwareSerial: 'abc123def456',
            manufacturedAt: '2025-01-15T10:00:00Z',
            firmwareVersion: '2.1.0',
        );

        $device = $this->service->registerDevice($deviceInfo);

        $this->assertInstanceOf(Device::class, $device);
        $this->assertEquals($deviceInfo->id, $device->uuid);
        $this->assertEquals($deviceInfo->hardwareSerial, $device->hardware_serial);
        $this->assertEquals($deviceInfo->firmwareVersion, $device->firmware_version);
        $this->assertEquals(DeviceStatus::Unclaimed, $device->status);
        $this->assertDatabaseHas('devices', [
            'uuid' => $deviceInfo->id,
            'hardware_serial' => $deviceInfo->hardwareSerial,
            'firmware_version' => $deviceInfo->firmwareVersion,
            'status' => 'unclaimed',
        ]);
    }
}
