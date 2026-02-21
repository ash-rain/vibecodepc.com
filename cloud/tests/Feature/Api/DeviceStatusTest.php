<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use VibecodePC\Common\Enums\DeviceStatus;

class DeviceStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_404_for_non_existent_device(): void
    {
        $response = $this->getJson('/api/devices/' . Str::uuid() . '/status');

        $response->assertStatus(404)
            ->assertJson(['error' => 'Device not found']);
    }

    public function test_returns_unclaimed_status_for_unclaimed_device(): void
    {
        $device = Device::factory()->create();

        $response = $this->getJson("/api/devices/{$device->uuid}/status");

        $response->assertOk()
            ->assertJson([
                'device_id' => $device->uuid,
                'status' => 'unclaimed',
                'pairing' => null,
            ]);
    }

    public function test_returns_claimed_status_for_claimed_device(): void
    {
        $device = Device::factory()->claimed()->create();

        $response = $this->getJson("/api/devices/{$device->uuid}/status");

        $response->assertOk()
            ->assertJson([
                'device_id' => $device->uuid,
                'status' => 'claimed',
                'pairing' => null,
            ]);
    }

    public function test_returns_pairing_data_when_token_exists_and_clears_it(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->claimed($user)->create([
            'pairing_token_encrypted' => 'test-pairing-token-123',
        ]);

        $response = $this->getJson("/api/devices/{$device->uuid}/status");

        $response->assertOk()
            ->assertJson([
                'device_id' => $device->uuid,
                'status' => 'claimed',
                'pairing' => [
                    'device_id' => $device->uuid,
                    'token' => 'test-pairing-token-123',
                    'username' => $user->username,
                    'email' => $user->email,
                ],
            ]);

        // Token should be cleared after retrieval (one-time read)
        $device->refresh();
        $this->assertNull($device->pairing_token_encrypted);

        // Second request should not include pairing data
        $secondResponse = $this->getJson("/api/devices/{$device->uuid}/status");

        $secondResponse->assertOk()
            ->assertJson([
                'device_id' => $device->uuid,
                'status' => 'claimed',
                'pairing' => null,
            ]);
    }
}
