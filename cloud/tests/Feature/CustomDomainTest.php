<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Device;
use App\Models\TunnelRoute;
use App\Models\User;
use App\Services\CustomDomainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CustomDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_domain_can_be_set_via_subdomain_form(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);

        $response = $this->actingAs($user)
            ->put('/dashboard/subdomain', [
                'username' => 'testuser',
                'custom_domain' => 'myapp.example.com',
            ]);

        $response->assertRedirect(route('dashboard.subdomain.edit'));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'custom_domain' => 'myapp.example.com',
        ]);
    }

    public function test_custom_domain_can_be_cleared(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'custom_domain' => 'old.example.com',
            'custom_domain_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->put('/dashboard/subdomain', [
                'username' => 'testuser',
                'custom_domain' => '',
            ]);

        $response->assertRedirect();
        $this->assertNull($user->fresh()->custom_domain);
        $this->assertNull($user->fresh()->custom_domain_verified_at);
    }

    public function test_custom_domain_service_resolve_to_username(): void
    {
        $user = User::factory()->create([
            'username' => 'myuser',
            'custom_domain' => 'app.example.com',
            'custom_domain_verified_at' => now(),
        ]);

        $service = new CustomDomainService;

        $this->assertEquals('myuser', $service->resolveToUsername('app.example.com'));
        $this->assertNull($service->resolveToUsername('unknown.example.com'));
    }

    public function test_unverified_custom_domain_does_not_resolve(): void
    {
        User::factory()->create([
            'username' => 'myuser',
            'custom_domain' => 'unverified.example.com',
            'custom_domain_verified_at' => null,
        ]);

        $service = new CustomDomainService;

        $this->assertNull($service->resolveToUsername('unverified.example.com'));
    }

    public function test_proxy_resolves_custom_domain(): void
    {
        Http::fake([
            '*' => Http::response('Custom domain response', 200),
        ]);

        $user = User::factory()->create([
            'username' => 'customuser',
            'custom_domain' => 'myapp.example.com',
            'custom_domain_verified_at' => now(),
        ]);
        $device = Device::factory()->online()->create([
            'user_id' => $user->id,
            'tunnel_url' => 'https://tunnel.example.com',
        ]);
        TunnelRoute::factory()->create([
            'device_id' => $device->id,
            'subdomain' => 'customuser',
            'path' => '/',
            'target_port' => 3000,
            'is_active' => true,
        ]);

        $response = $this->get('http://myapp.example.com/');

        $response->assertOk();
        $response->assertSee('Custom domain response');
    }

    public function test_subdomain_edit_shows_custom_domain_verification_status(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'custom_domain' => 'verified.example.com',
            'custom_domain_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/dashboard/subdomain');

        $response->assertOk()
            ->assertSee('verified.example.com')
            ->assertSee('Verified');
    }

    public function test_subdomain_edit_shows_pending_verification(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'custom_domain' => 'pending.example.com',
            'custom_domain_verified_at' => null,
        ]);

        $response = $this->actingAs($user)->get('/dashboard/subdomain');

        $response->assertOk()
            ->assertSee('pending.example.com')
            ->assertSee('Pending verification');
    }

    public function test_verify_domain_endpoint(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'custom_domain' => 'test.example.com',
            'custom_domain_verified_at' => null,
        ]);

        // DNS won't resolve in tests, so it should fail verification
        $response = $this->actingAs($user)
            ->post('/dashboard/subdomain/verify-domain');

        $response->assertRedirect(route('dashboard.subdomain.edit'))
            ->assertSessionHas('status');
    }
}
