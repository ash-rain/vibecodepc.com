<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Device;
use App\Models\TunnelRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TunnelProxyTest extends TestCase
{
    use RefreshDatabase;

    public function test_proxy_returns_404_for_unknown_subdomain(): void
    {
        $response = $this->get('http://unknown.vibecodepc.com/');

        $response->assertNotFound();
    }

    public function test_proxy_does_not_intercept_main_domain(): void
    {
        // Non-subdomain requests should pass through to normal routes
        $response = $this->get('/');

        $response->assertOk();
    }

    public function test_proxy_returns_502_for_offline_device(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $device = Device::factory()->offline()->create(['user_id' => $user->id]);
        TunnelRoute::factory()->create([
            'device_id' => $device->id,
            'subdomain' => 'testuser',
            'path' => '/',
            'is_active' => true,
        ]);

        $response = $this->get('http://testuser.vibecodepc.com/');

        $response->assertStatus(502);
    }

    public function test_proxy_forwards_request_to_online_device(): void
    {
        Http::fake([
            '*' => Http::response('Hello from device!', 200, ['Content-Type' => 'text/plain']),
        ]);

        $user = User::factory()->create(['username' => 'testuser']);
        $device = Device::factory()->online()->create([
            'user_id' => $user->id,
            'tunnel_url' => 'https://tunnel.example.com',
        ]);
        TunnelRoute::factory()->create([
            'device_id' => $device->id,
            'subdomain' => 'testuser',
            'path' => '/',
            'target_port' => 3000,
            'is_active' => true,
        ]);

        $response = $this->get('http://testuser.vibecodepc.com/');

        $response->assertOk();
        $response->assertSee('Hello from device!');
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'tunnel.example.com:3000');
        });
    }

    public function test_proxy_falls_back_to_root_route(): void
    {
        Http::fake([
            '*' => Http::response('Root response', 200),
        ]);

        $user = User::factory()->create(['username' => 'testuser']);
        $device = Device::factory()->online()->create([
            'user_id' => $user->id,
            'tunnel_url' => 'https://tunnel.example.com',
        ]);
        TunnelRoute::factory()->create([
            'device_id' => $device->id,
            'subdomain' => 'testuser',
            'path' => '/',
            'target_port' => 3000,
            'is_active' => true,
        ]);

        $response = $this->get('http://testuser.vibecodepc.com/some/path');

        $response->assertOk();
    }

    public function test_proxy_logs_tunnel_request(): void
    {
        Http::fake([
            '*' => Http::response('OK', 200),
        ]);

        $user = User::factory()->create(['username' => 'logtest']);
        $device = Device::factory()->online()->create([
            'user_id' => $user->id,
            'tunnel_url' => 'https://tunnel.example.com',
        ]);
        $route = TunnelRoute::factory()->create([
            'device_id' => $device->id,
            'subdomain' => 'logtest',
            'path' => '/',
            'target_port' => 3000,
            'is_active' => true,
        ]);

        $this->get('http://logtest.vibecodepc.com/');

        $this->assertDatabaseHas('tunnel_request_logs', [
            'tunnel_route_id' => $route->id,
            'status_code' => 200,
        ]);
    }

    public function test_subdomain_rate_limiter_blocks_excessive_requests(): void
    {
        $user = User::factory()->create(['username' => 'ratelimited']);
        $device = Device::factory()->offline()->create(['user_id' => $user->id]);
        TunnelRoute::factory()->create([
            'device_id' => $device->id,
            'subdomain' => 'ratelimited',
            'path' => '/',
            'is_active' => true,
        ]);

        $rateLimited = false;

        for ($i = 0; $i < 65; $i++) {
            $response = $this->get('http://ratelimited.vibecodepc.com/');

            if ($response->status() === 429) {
                $rateLimited = true;
                break;
            }
        }

        $this->assertTrue($rateLimited, 'Rate limiter should have triggered.');
    }
}
