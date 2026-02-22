<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Device;
use App\Models\TunnelRoute;
use App\Services\TunnelRoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TunnelRoutingServiceTest extends TestCase
{
    use RefreshDatabase;

    private TunnelRoutingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TunnelRoutingService;
    }

    public function test_register_tunnel_updates_device_tunnel_url(): void
    {
        $device = Device::factory()->claimed()->create();

        $this->service->registerTunnel($device, 'https://mydevice.vibecodepc.com');

        $device->refresh();
        $this->assertEquals('https://mydevice.vibecodepc.com', $device->tunnel_url);
    }

    public function test_update_routes_creates_tunnel_route_records(): void
    {
        $device = Device::factory()->claimed()->create();

        $routes = $this->service->updateRoutes($device, 'testuser', [
            ['path' => '/', 'target_port' => 80, 'project_name' => 'main-app'],
            ['path' => '/api', 'target_port' => 8000, 'project_name' => 'api-app'],
        ]);

        $this->assertCount(2, $routes);
        $this->assertDatabaseHas('tunnel_routes', [
            'device_id' => $device->id,
            'subdomain' => 'testuser',
            'path' => '/',
            'target_port' => 80,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('tunnel_routes', [
            'device_id' => $device->id,
            'subdomain' => 'testuser',
            'path' => '/api',
            'target_port' => 8000,
            'is_active' => true,
        ]);
    }

    public function test_update_routes_deactivates_previous_routes_for_same_subdomain(): void
    {
        $device = Device::factory()->claimed()->create();

        // First update
        $this->service->updateRoutes($device, 'testuser', [
            ['path' => '/', 'target_port' => 80],
            ['path' => '/old', 'target_port' => 3000],
        ]);

        // Second update — /old should become inactive
        $this->service->updateRoutes($device, 'testuser', [
            ['path' => '/', 'target_port' => 8080],
        ]);

        $this->assertDatabaseHas('tunnel_routes', [
            'subdomain' => 'testuser',
            'path' => '/',
            'target_port' => 8080,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('tunnel_routes', [
            'subdomain' => 'testuser',
            'path' => '/old',
            'is_active' => false,
        ]);
    }

    public function test_resolve_route_finds_active_route(): void
    {
        $device = Device::factory()->claimed()->create();
        TunnelRoute::factory()->create([
            'device_id' => $device->id,
            'subdomain' => 'bob',
            'path' => '/',
            'target_port' => 3000,
            'is_active' => true,
        ]);

        $route = $this->service->resolveRoute('bob', '/');

        $this->assertNotNull($route);
        $this->assertEquals(3000, $route->target_port);
    }

    public function test_resolve_route_returns_null_for_inactive_route(): void
    {
        $device = Device::factory()->claimed()->create();
        TunnelRoute::factory()->inactive()->create([
            'device_id' => $device->id,
            'subdomain' => 'bob',
            'path' => '/',
        ]);

        $route = $this->service->resolveRoute('bob', '/');

        $this->assertNull($route);
    }

    public function test_deactivate_device_routes_marks_all_routes_inactive(): void
    {
        $device = Device::factory()->claimed()->create();
        TunnelRoute::factory()->count(3)->create([
            'device_id' => $device->id,
            'is_active' => true,
        ]);

        $count = $this->service->deactivateDeviceRoutes($device);

        $this->assertEquals(3, $count);
        $this->assertEquals(0, $device->tunnelRoutes()->where('is_active', true)->count());
    }
}
