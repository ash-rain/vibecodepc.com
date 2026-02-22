<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Device;
use App\Models\TunnelRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use VibecodePC\Common\Enums\DeviceStatus;

class DeviceDeregisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_can_be_deregistered(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->online()->create(['user_id' => $user->id]);

        $route = TunnelRoute::factory()->create([
            'device_id' => $device->id,
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/devices/{$device->uuid}/deregister");

        $response->assertOk()
            ->assertJson([
                'message' => 'Device deregistered successfully.',
                'device_id' => $device->uuid,
                'status' => 'deactivated',
            ]);

        $device->refresh();
        $this->assertEquals(DeviceStatus::Deactivated, $device->status);
        $this->assertFalse($device->is_online);
        $this->assertFalse($route->fresh()->is_active);
    }

    public function test_deregister_requires_authentication(): void
    {
        $device = Device::factory()->claimed()->create();

        $response = $this->postJson("/api/devices/{$device->uuid}/deregister");

        $response->assertUnauthorized();
    }

    public function test_deregister_requires_ownership(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $device = Device::factory()->online()->create(['user_id' => $otherUser->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/devices/{$device->uuid}/deregister");

        $response->assertForbidden();
    }

    public function test_config_endpoint_includes_config_version(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->online()->create([
            'user_id' => $user->id,
            'config_version' => 3,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/devices/{$device->uuid}/config");

        $response->assertOk()
            ->assertJsonPath('config.config_version', 3);
    }

    public function test_subdomain_change_bumps_config_version(): void
    {
        $user = User::factory()->create(['username' => 'oldname']);
        $device = Device::factory()->claimed($user)->create(['config_version' => 1]);

        $this->actingAs($user)
            ->put('/dashboard/subdomain', ['username' => 'newname']);

        $this->assertEquals(2, $device->fresh()->config_version);
    }
}
