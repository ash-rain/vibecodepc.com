<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Device;
use App\Models\DeviceHeartbeat;
use App\Models\TunnelRequestLog;
use App\Models\TunnelRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtisanCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_mark_stale_devices_offline(): void
    {
        $user = User::factory()->create();

        // Online device with recent heartbeat
        $recentDevice = Device::factory()->online()->create([
            'user_id' => $user->id,
            'last_heartbeat_at' => now()->subMinutes(2),
        ]);

        // Online device with stale heartbeat
        $staleDevice = Device::factory()->online()->create([
            'user_id' => $user->id,
            'last_heartbeat_at' => now()->subMinutes(10),
        ]);

        $this->artisan('devices:mark-stale')
            ->expectsOutputToContain('1 stale device(s)')
            ->assertSuccessful();

        $this->assertTrue($recentDevice->fresh()->is_online);
        $this->assertFalse($staleDevice->fresh()->is_online);
    }

    public function test_mark_stale_devices_with_no_stale(): void
    {
        $this->artisan('devices:mark-stale')
            ->expectsOutputToContain('0 stale device(s)')
            ->assertSuccessful();
    }

    public function test_prune_heartbeats(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->claimed($user)->create();

        // Old heartbeat
        DeviceHeartbeat::factory()->create([
            'device_id' => $device->id,
            'created_at' => now()->subDays(31),
        ]);

        // Recent heartbeat
        DeviceHeartbeat::factory()->create([
            'device_id' => $device->id,
            'created_at' => now()->subDays(5),
        ]);

        $this->artisan('heartbeats:prune')
            ->expectsOutputToContain('1 heartbeat(s)')
            ->assertSuccessful();

        $this->assertDatabaseCount('device_heartbeats', 1);
    }

    public function test_prune_heartbeats_with_custom_days(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->claimed($user)->create();

        DeviceHeartbeat::factory()->create([
            'device_id' => $device->id,
            'created_at' => now()->subDays(8),
        ]);

        $this->artisan('heartbeats:prune --days=7')
            ->expectsOutputToContain('1 heartbeat(s)')
            ->assertSuccessful();
    }

    public function test_prune_tunnel_request_logs(): void
    {
        $route = TunnelRoute::factory()->create();

        // Old log
        TunnelRequestLog::factory()->create([
            'tunnel_route_id' => $route->id,
            'created_at' => now()->subDays(91),
        ]);

        // Recent log
        TunnelRequestLog::factory()->create([
            'tunnel_route_id' => $route->id,
            'created_at' => now()->subDays(30),
        ]);

        $this->artisan('tunnel-logs:prune')
            ->expectsOutputToContain('1 tunnel request log(s)')
            ->assertSuccessful();

        $this->assertDatabaseCount('tunnel_request_logs', 1);
    }

    public function test_prune_tunnel_request_logs_with_custom_days(): void
    {
        $route = TunnelRoute::factory()->create();

        TunnelRequestLog::factory()->create([
            'tunnel_route_id' => $route->id,
            'created_at' => now()->subDays(15),
        ]);

        $this->artisan('tunnel-logs:prune --days=14')
            ->expectsOutputToContain('1 tunnel request log(s)')
            ->assertSuccessful();
    }
}
