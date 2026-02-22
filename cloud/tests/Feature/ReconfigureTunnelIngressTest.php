<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Device;
use App\Models\TunnelRoute;
use App\Models\User;
use App\Services\CloudflareTunnelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ReconfigureTunnelIngressTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fails_for_unknown_device(): void
    {
        $this->artisan('tunnel:reconfigure', ['uuid' => 'nonexistent'])
            ->expectsOutput('Device not found.')
            ->assertFailed();
    }

    public function test_it_fails_when_no_active_route_exists(): void
    {
        $device = Device::factory()->create();

        $this->artisan('tunnel:reconfigure', ['uuid' => $device->uuid])
            ->expectsOutput('No active tunnel route found for this device.')
            ->assertFailed();
    }

    public function test_it_fails_when_cloudflare_tunnel_not_found(): void
    {
        $mock = Mockery::mock(CloudflareTunnelService::class);
        $mock->shouldReceive('findTunnelByName')->once()->andReturnNull();
        $this->app->instance(CloudflareTunnelService::class, $mock);

        $user = User::factory()->create();
        $device = Device::factory()->create(['user_id' => $user->id]);
        TunnelRoute::factory()->create([
            'device_id' => $device->id,
            'subdomain' => 'testuser',
            'is_active' => true,
        ]);

        $this->artisan('tunnel:reconfigure', ['uuid' => $device->uuid])
            ->assertFailed();
    }

    public function test_it_reconfigures_tunnel_ingress(): void
    {
        $mock = Mockery::mock(CloudflareTunnelService::class);
        $mock->shouldReceive('findTunnelByName')
            ->once()
            ->andReturn(['id' => 'tunnel-abc', 'name' => 'device-test']);
        $mock->shouldReceive('configureTunnelIngress')
            ->once()
            ->with('tunnel-abc', 'testuser.vibecodepc.com', 8081);
        $this->app->instance(CloudflareTunnelService::class, $mock);

        $user = User::factory()->create();
        $device = Device::factory()->create(['user_id' => $user->id]);
        $route = TunnelRoute::factory()->create([
            'device_id' => $device->id,
            'subdomain' => 'testuser',
            'target_port' => 3000,
            'is_active' => true,
        ]);

        $this->artisan('tunnel:reconfigure', ['uuid' => $device->uuid, '--port' => 8081])
            ->expectsOutput('Tunnel ingress updated: testuser.vibecodepc.com → localhost:8081')
            ->assertSuccessful();

        $this->assertDatabaseHas('tunnel_routes', [
            'id' => $route->id,
            'target_port' => 8081,
        ]);
    }
}
