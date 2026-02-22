<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Device;
use App\Models\TunnelRequestLog;
use App\Models\TunnelRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_detail_shows_traffic_stats(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->online()->create(['user_id' => $user->id]);
        $route = TunnelRoute::factory()->create([
            'device_id' => $device->id,
            'subdomain' => $user->username,
        ]);

        TunnelRequestLog::factory()->count(5)->create([
            'tunnel_route_id' => $route->id,
            'status_code' => 200,
            'response_time_ms' => 100,
        ]);

        $response = $this->actingAs($user)->get("/dashboard/devices/{$device->id}");

        $response->assertOk()
            ->assertViewHas('trafficStats')
            ->assertSee('Traffic Stats')
            ->assertSee($route->full_url);
    }

    public function test_device_detail_without_traffic_stats(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->online()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get("/dashboard/devices/{$device->id}");

        $response->assertOk()
            ->assertViewHas('trafficStats')
            ->assertDontSee('Traffic Stats');
    }

    public function test_tunnel_request_log_belongs_to_tunnel_route(): void
    {
        $log = TunnelRequestLog::factory()->create();

        $this->assertInstanceOf(TunnelRoute::class, $log->tunnelRoute);
    }

    public function test_tunnel_route_has_request_logs(): void
    {
        $route = TunnelRoute::factory()->create();
        TunnelRequestLog::factory()->count(3)->create(['tunnel_route_id' => $route->id]);

        $this->assertCount(3, $route->requestLogs);
    }
}
