<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Device;
use App\Models\TunnelRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeviceTunnelTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_tunnel_requires_authentication(): void
    {
        $device = Device::factory()->claimed()->create();

        $response = $this->postJson("/api/devices/{$device->uuid}/tunnel/register", [
            'tunnel_url' => 'https://abc123.trycloudflare.com',
        ]);

        $response->assertStatus(401);
    }

    public function test_register_tunnel_returns_403_for_non_owned_device(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $device = Device::factory()->claimed($owner)->create();

        $response = $this->postJson("/api/devices/{$device->uuid}/tunnel/register", [
            'tunnel_url' => 'https://abc123.trycloudflare.com',
        ]);

        $response->assertStatus(403)
            ->assertJson(['error' => 'Unauthorized']);
    }

    public function test_register_tunnel_stores_url(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $device = Device::factory()->claimed($user)->create();

        $tunnelUrl = 'https://abc123.trycloudflare.com';

        $response = $this->postJson("/api/devices/{$device->uuid}/tunnel/register", [
            'tunnel_url' => $tunnelUrl,
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Tunnel registered',
                'tunnel_url' => $tunnelUrl,
            ]);

        $device->refresh();
        $this->assertEquals($tunnelUrl, $device->tunnel_url);
    }

    public function test_register_tunnel_validates_url(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $device = Device::factory()->claimed($user)->create();

        $response = $this->postJson("/api/devices/{$device->uuid}/tunnel/register", [
            'tunnel_url' => 'not-a-valid-url',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tunnel_url']);
    }

    public function test_update_routes_creates_tunnel_routes(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $device = Device::factory()->claimed($user)->create();

        $response = $this->postJson("/api/devices/{$device->uuid}/tunnel/routes", [
            'subdomain' => 'user',
            'routes' => [
                [
                    'path' => '/',
                    'target_port' => 80,
                    'project_name' => 'myapp',
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Routes updated',
            ]);

        $this->assertDatabaseHas('tunnel_routes', [
            'device_id' => $device->id,
            'subdomain' => 'user',
            'path' => '/',
            'target_port' => 80,
            'project_name' => 'myapp',
            'is_active' => true,
        ]);
    }

    public function test_update_routes_deactivates_old_routes(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $device = Device::factory()->claimed($user)->create();

        // First update: create initial routes
        $this->postJson("/api/devices/{$device->uuid}/tunnel/routes", [
            'subdomain' => 'user',
            'routes' => [
                [
                    'path' => '/',
                    'target_port' => 80,
                    'project_name' => 'old-app',
                ],
                [
                    'path' => '/api',
                    'target_port' => 3000,
                    'project_name' => 'old-api',
                ],
            ],
        ]);

        $this->assertDatabaseCount('tunnel_routes', 2);

        // Second update: replace with new routes for the same subdomain
        $response = $this->postJson("/api/devices/{$device->uuid}/tunnel/routes", [
            'subdomain' => 'user',
            'routes' => [
                [
                    'path' => '/',
                    'target_port' => 8080,
                    'project_name' => 'new-app',
                ],
            ],
        ]);

        $response->assertOk();

        // The route at "/" should be updated and active
        $this->assertDatabaseHas('tunnel_routes', [
            'device_id' => $device->id,
            'subdomain' => 'user',
            'path' => '/',
            'target_port' => 8080,
            'project_name' => 'new-app',
            'is_active' => true,
        ]);

        // The old "/api" route should be deactivated
        $this->assertDatabaseHas('tunnel_routes', [
            'device_id' => $device->id,
            'subdomain' => 'user',
            'path' => '/api',
            'is_active' => false,
        ]);
    }

    public function test_update_routes_validates_input(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $device = Device::factory()->claimed($user)->create();

        // Missing both subdomain and routes
        $response = $this->postJson("/api/devices/{$device->uuid}/tunnel/routes", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['subdomain', 'routes']);
    }

    public function test_list_routes_returns_active_routes(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $device = Device::factory()->claimed($user)->create();

        // Create active and inactive routes
        TunnelRoute::factory()->create([
            'device_id' => $device->id,
            'subdomain' => 'user',
            'path' => '/',
            'target_port' => 80,
            'project_name' => 'active-app',
            'is_active' => true,
        ]);

        TunnelRoute::factory()->inactive()->create([
            'device_id' => $device->id,
            'subdomain' => 'user',
            'path' => '/old',
            'target_port' => 3000,
            'project_name' => 'inactive-app',
        ]);

        $response = $this->getJson("/api/devices/{$device->uuid}/tunnel/routes");

        $response->assertOk()
            ->assertJsonCount(1, 'routes')
            ->assertJsonFragment([
                'subdomain' => 'user',
                'path' => '/',
                'target_port' => 80,
                'project_name' => 'active-app',
                'is_active' => true,
            ]);

        // Verify inactive route is not included
        $response->assertJsonMissing([
            'project_name' => 'inactive-app',
        ]);
    }

    public function test_list_routes_requires_authentication(): void
    {
        $device = Device::factory()->claimed()->create();

        $response = $this->getJson("/api/devices/{$device->uuid}/tunnel/routes");

        $response->assertStatus(401);
    }
}
