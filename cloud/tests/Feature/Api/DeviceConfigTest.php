<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeviceConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $device = Device::factory()->claimed()->create();

        $response = $this->getJson("/api/devices/{$device->uuid}/config");

        $response->assertStatus(401);
    }

    public function test_returns_403_for_non_owned_device(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $device = Device::factory()->claimed($owner)->create();

        $response = $this->getJson("/api/devices/{$device->uuid}/config");

        $response->assertStatus(403)
            ->assertJson(['error' => 'Unauthorized']);
    }

    public function test_returns_device_config(): void
    {
        $user = User::factory()->create(['username' => 'alice']);
        Sanctum::actingAs($user);

        $device = Device::factory()->claimed($user)->create([
            'tunnel_url' => 'https://alice.vibecodepc.com',
            'firmware_version' => '2.1.0',
        ]);

        $response = $this->getJson("/api/devices/{$device->uuid}/config");

        $response->assertOk()
            ->assertJsonStructure([
                'device_id',
                'config' => [
                    'subdomain',
                    'tunnel_url',
                    'firmware_version',
                    'heartbeat_interval_seconds',
                ],
            ])
            ->assertJson([
                'device_id' => $device->uuid,
                'config' => [
                    'subdomain' => 'alice',
                    'tunnel_url' => 'https://alice.vibecodepc.com',
                    'firmware_version' => '2.1.0',
                    'heartbeat_interval_seconds' => 60,
                ],
            ]);
    }
}
