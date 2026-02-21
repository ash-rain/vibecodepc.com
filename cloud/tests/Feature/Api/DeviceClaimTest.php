<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use VibecodePC\Common\Enums\DeviceStatus;

class DeviceClaimTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $device = Device::factory()->create();

        $response = $this->postJson("/api/devices/{$device->uuid}/claim");

        $response->assertStatus(401);
    }

    public function test_returns_404_for_non_existent_device(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/devices/' . Str::uuid() . '/claim');

        $response->assertStatus(404)
            ->assertJson(['error' => 'Device not found']);
    }

    public function test_returns_409_for_already_claimed_device(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $device = Device::factory()->claimed()->create();

        $response = $this->postJson("/api/devices/{$device->uuid}/claim");

        $response->assertStatus(409)
            ->assertJson(['error' => 'Device already claimed']);
    }

    public function test_successfully_claims_unclaimed_device(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $device = Device::factory()->create();

        $response = $this->postJson("/api/devices/{$device->uuid}/claim");

        $response->assertOk()
            ->assertJsonStructure([
                'device_id',
                'token',
                'username',
                'email',
                'ip_hint',
            ])
            ->assertJson([
                'device_id' => $device->uuid,
                'username' => $user->username,
                'email' => $user->email,
            ]);

        // Verify the device was updated in the database
        $device->refresh();
        $this->assertEquals(DeviceStatus::Claimed, $device->status);
        $this->assertEquals($user->id, $device->user_id);
        $this->assertNotNull($device->paired_at);
        $this->assertNotNull($device->pairing_token_encrypted);
    }
}
