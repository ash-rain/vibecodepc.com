<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Device;
use App\Models\DeviceHeartbeat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeviceHeartbeatTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $device = Device::factory()->claimed()->create();

        $response = $this->postJson("/api/devices/{$device->uuid}/heartbeat", [
            'cpu_percent' => 45.2,
        ]);

        $response->assertStatus(401);
    }

    public function test_returns_403_for_non_owned_device(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $device = Device::factory()->claimed()->create();

        $response = $this->postJson("/api/devices/{$device->uuid}/heartbeat", [
            'cpu_percent' => 45.2,
        ]);

        $response->assertStatus(403)
            ->assertJson(['error' => 'Unauthorized']);
    }

    public function test_returns_404_for_non_existent_device(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/devices/'.Str::uuid().'/heartbeat', [
            'cpu_percent' => 45.2,
        ]);

        $response->assertStatus(404)
            ->assertJson(['error' => 'Device not found']);
    }

    public function test_stores_heartbeat_successfully(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $device = Device::factory()->claimed($user)->create();

        $payload = [
            'cpu_percent' => 65.3,
            'cpu_temp' => 52.1,
            'ram_used_mb' => 4096,
            'ram_total_mb' => 8192,
            'disk_used_gb' => 120.50,
            'disk_total_gb' => 256.00,
            'running_projects' => 3,
            'tunnel_active' => true,
            'firmware_version' => '1.2.0',
            'os_version' => 'Debian 12.8',
        ];

        $response = $this->postJson("/api/devices/{$device->uuid}/heartbeat", $payload);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Heartbeat recorded',
                'heartbeat' => [
                    'cpu_percent' => 65.3,
                    'cpu_temp' => 52.1,
                    'ram_used_mb' => 4096,
                    'ram_total_mb' => 8192,
                    'disk_used_gb' => 120.50,
                    'disk_total_gb' => 256.00,
                    'running_projects' => 3,
                    'tunnel_active' => true,
                    'firmware_version' => '1.2.0',
                    'os_version' => 'Debian 12.8',
                ],
            ]);

        // Verify the heartbeat record was created in the database
        $this->assertDatabaseHas('device_heartbeats', [
            'device_id' => $device->id,
            'cpu_percent' => 65.3,
            'cpu_temp' => 52.1,
            'ram_used_mb' => 4096,
            'ram_total_mb' => 8192,
            'running_projects' => 3,
            'tunnel_active' => true,
            'firmware_version' => '1.2.0',
            'os_version' => 'Debian 12.8',
        ]);

        // Verify the device telemetry fields were updated
        $device->refresh();
        $this->assertTrue($device->is_online);
        $this->assertNotNull($device->last_heartbeat_at);
        $this->assertEquals(65.3, $device->cpu_percent);
        $this->assertEquals(52.1, $device->cpu_temp);
        $this->assertEquals(4096, $device->ram_used_mb);
        $this->assertEquals(8192, $device->ram_total_mb);
        $this->assertEquals(120.50, $device->disk_used_gb);
        $this->assertEquals(256.00, $device->disk_total_gb);
        $this->assertEquals('1.2.0', $device->firmware_version);
        $this->assertEquals('Debian 12.8', $device->os_version);
    }

    public function test_stores_heartbeat_with_partial_data(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $device = Device::factory()->claimed($user)->create();

        $payload = [
            'cpu_percent' => 30.5,
            'ram_used_mb' => 2048,
            'ram_total_mb' => 8192,
        ];

        $response = $this->postJson("/api/devices/{$device->uuid}/heartbeat", $payload);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Heartbeat recorded',
                'heartbeat' => [
                    'cpu_percent' => 30.5,
                    'ram_used_mb' => 2048,
                    'ram_total_mb' => 8192,
                ],
            ]);

        // Verify only submitted fields are persisted on the heartbeat
        $this->assertDatabaseHas('device_heartbeats', [
            'device_id' => $device->id,
            'cpu_percent' => 30.5,
            'ram_used_mb' => 2048,
            'ram_total_mb' => 8192,
            'cpu_temp' => null,
            'disk_used_gb' => null,
            'disk_total_gb' => null,
        ]);

        // Verify device telemetry reflects partial update
        $device->refresh();
        $this->assertTrue($device->is_online);
        $this->assertNotNull($device->last_heartbeat_at);
        $this->assertEquals(30.5, $device->cpu_percent);
        $this->assertNull($device->cpu_temp);
        $this->assertEquals(2048, $device->ram_used_mb);
        $this->assertEquals(8192, $device->ram_total_mb);
    }

    public function test_lists_heartbeats_for_device(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $device = Device::factory()->claimed($user)->create();

        // Create heartbeats with staggered created_at to ensure ordering
        $older = DeviceHeartbeat::factory()->create([
            'device_id' => $device->id,
            'cpu_percent' => 20.0,
            'created_at' => now()->subMinutes(10),
        ]);
        $newer = DeviceHeartbeat::factory()->create([
            'device_id' => $device->id,
            'cpu_percent' => 80.0,
            'created_at' => now()->subMinutes(1),
        ]);

        $response = $this->getJson("/api/devices/{$device->uuid}/heartbeats");

        $response->assertOk()
            ->assertJsonCount(2, 'heartbeats');

        // Verify descending order (newest first)
        $heartbeats = $response->json('heartbeats');
        $this->assertEquals($newer->id, $heartbeats[0]['id']);
        $this->assertEquals($older->id, $heartbeats[1]['id']);
    }

    public function test_validation_rejects_invalid_data(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $device = Device::factory()->claimed($user)->create();

        $payload = [
            'cpu_percent' => 150,       // max:100
            'cpu_temp' => -5,           // min:0
            'ram_used_mb' => -100,      // min:0
            'ram_total_mb' => 'abc',    // integer
            'disk_used_gb' => -1,       // min:0
            'running_projects' => -2,   // min:0
            'firmware_version' => str_repeat('x', 51),  // max:50
            'os_version' => str_repeat('x', 101),       // max:100
        ];

        $response = $this->postJson("/api/devices/{$device->uuid}/heartbeat", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'cpu_percent',
                'cpu_temp',
                'ram_used_mb',
                'ram_total_mb',
                'disk_used_gb',
                'running_projects',
                'firmware_version',
                'os_version',
            ]);

        // Ensure no heartbeat was created
        $this->assertDatabaseCount('device_heartbeats', 0);
    }
}
