<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Device;
use App\Models\User;
use App\Services\CloudflareTunnelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class DeviceTunnelProvisionTest extends TestCase
{
    use RefreshDatabase;

    private MockInterface $cfMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cfMock = $this->mock(CloudflareTunnelService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createTunnel')
                ->andReturn(['id' => 'tunnel-uuid-123', 'name' => 'device-test'])
                ->byDefault();

            $mock->shouldReceive('configureTunnelIngress')
                ->byDefault();

            $mock->shouldReceive('createDnsRecord')
                ->byDefault();

            $mock->shouldReceive('getTunnelToken')
                ->andReturn('eyJ0dW5uZWxfdG9rZW4iOiJ0ZXN0In0=')
                ->byDefault();
        });
    }

    public function test_provision_creates_tunnel_and_returns_token(): void
    {
        $user = User::factory()->create(['username' => null]);
        Sanctum::actingAs($user);

        $device = Device::factory()->claimed($user)->create();

        $response = $this->postJson("/api/devices/{$device->uuid}/tunnel/provision", [
            'subdomain' => 'mydevice',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['tunnel_id', 'tunnel_token'])
            ->assertJson([
                'tunnel_id' => 'tunnel-uuid-123',
                'tunnel_token' => 'eyJ0dW5uZWxfdG9rZW4iOiJ0ZXN0In0=',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'username' => 'mydevice',
        ]);

        $this->assertDatabaseHas('devices', [
            'id' => $device->id,
            'tunnel_url' => 'https://mydevice.vibecodepc.com',
        ]);

        $this->assertDatabaseHas('tunnel_routes', [
            'device_id' => $device->id,
            'subdomain' => 'mydevice',
            'path' => '/',
            'target_port' => config('cloudflare.device_app_port'),
            'is_active' => true,
        ]);
    }

    public function test_provision_requires_authentication(): void
    {
        $device = Device::factory()->claimed()->create();

        $response = $this->postJson("/api/devices/{$device->uuid}/tunnel/provision", [
            'subdomain' => 'mydevice',
        ]);

        $response->assertStatus(401);
    }

    public function test_provision_returns_403_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $device = Device::factory()->claimed($owner)->create();

        $response = $this->postJson("/api/devices/{$device->uuid}/tunnel/provision", [
            'subdomain' => 'mydevice',
        ]);

        $response->assertStatus(403);
    }

    public function test_provision_returns_409_when_subdomain_taken(): void
    {
        User::factory()->create(['username' => 'taken']);

        $user = User::factory()->create(['username' => null]);
        Sanctum::actingAs($user);

        $device = Device::factory()->claimed($user)->create();

        $response = $this->postJson("/api/devices/{$device->uuid}/tunnel/provision", [
            'subdomain' => 'taken',
        ]);

        $response->assertStatus(409)
            ->assertJson(['error' => 'Subdomain is not available.']);
    }

    public function test_provision_validates_subdomain_format(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $device = Device::factory()->claimed($user)->create();

        $response = $this->postJson("/api/devices/{$device->uuid}/tunnel/provision", [
            'subdomain' => 'A',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['subdomain']);
    }

    public function test_provision_validates_subdomain_required(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $device = Device::factory()->claimed($user)->create();

        $response = $this->postJson("/api/devices/{$device->uuid}/tunnel/provision", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['subdomain']);
    }

    public function test_provision_rejects_reserved_subdomain(): void
    {
        $user = User::factory()->create(['username' => null]);
        Sanctum::actingAs($user);

        $device = Device::factory()->claimed($user)->create();

        $response = $this->postJson("/api/devices/{$device->uuid}/tunnel/provision", [
            'subdomain' => 'admin',
        ]);

        $response->assertStatus(409)
            ->assertJson(['error' => 'Subdomain is not available.']);
    }

    public function test_provision_calls_cloudflare_apis_in_order(): void
    {
        $this->cfMock->shouldReceive('createTunnel')
            ->once()
            ->with('device-test-uuid')
            ->andReturn(['id' => 'cf-tunnel-id', 'name' => 'device-test-uuid']);

        $this->cfMock->shouldReceive('configureTunnelIngress')
            ->once()
            ->with('cf-tunnel-id', 'newuser.vibecodepc.com', (int) config('cloudflare.device_app_port'));

        $this->cfMock->shouldReceive('createDnsRecord')
            ->once()
            ->with('newuser', 'cf-tunnel-id');

        $this->cfMock->shouldReceive('getTunnelToken')
            ->once()
            ->with('cf-tunnel-id')
            ->andReturn('token-jwt');

        $user = User::factory()->create(['username' => null]);
        Sanctum::actingAs($user);

        $device = Device::factory()->claimed($user)->create(['uuid' => 'test-uuid']);

        $response = $this->postJson("/api/devices/{$device->uuid}/tunnel/provision", [
            'subdomain' => 'newuser',
        ]);

        $response->assertOk()
            ->assertJson([
                'tunnel_id' => 'cf-tunnel-id',
                'tunnel_token' => 'token-jwt',
            ]);
    }
}
