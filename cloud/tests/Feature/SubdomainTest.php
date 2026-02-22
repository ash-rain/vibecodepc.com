<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Device;
use App\Models\TunnelRoute;
use App\Models\User;
use App\Services\SubdomainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubdomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_subdomain_edit_requires_authentication(): void
    {
        $response = $this->get('/dashboard/subdomain');

        $response->assertRedirect('/login');
    }

    public function test_subdomain_edit_renders_form(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);

        $response = $this->actingAs($user)->get('/dashboard/subdomain');

        $response->assertOk()
            ->assertViewIs('dashboard.subdomain.edit')
            ->assertSee('testuser');
    }

    public function test_subdomain_can_be_updated(): void
    {
        $user = User::factory()->create(['username' => 'oldname']);

        $response = $this->actingAs($user)
            ->put('/dashboard/subdomain', ['username' => 'newname']);

        $response->assertRedirect(route('dashboard.subdomain.edit'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'username' => 'newname',
        ]);
    }

    public function test_subdomain_update_cascades_to_tunnel_routes(): void
    {
        $user = User::factory()->create(['username' => 'oldname']);
        $device = Device::factory()->claimed($user)->create();
        TunnelRoute::factory()->create([
            'device_id' => $device->id,
            'subdomain' => 'oldname',
        ]);

        $this->actingAs($user)
            ->put('/dashboard/subdomain', ['username' => 'newname']);

        $this->assertDatabaseHas('tunnel_routes', [
            'device_id' => $device->id,
            'subdomain' => 'newname',
        ]);
        $this->assertDatabaseMissing('tunnel_routes', [
            'device_id' => $device->id,
            'subdomain' => 'oldname',
        ]);
    }

    public function test_subdomain_rejects_reserved_words(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->put('/dashboard/subdomain', ['username' => 'admin']);

        $response->assertSessionHasErrors('username');
    }

    public function test_subdomain_rejects_invalid_format(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->put('/dashboard/subdomain', ['username' => '1badname']);

        $response->assertSessionHasErrors('username');
    }

    public function test_subdomain_rejects_too_short(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->put('/dashboard/subdomain', ['username' => 'ab']);

        $response->assertSessionHasErrors('username');
    }

    public function test_subdomain_rejects_duplicate(): void
    {
        User::factory()->create(['username' => 'taken']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->put('/dashboard/subdomain', ['username' => 'taken']);

        $response->assertSessionHasErrors('username');
    }

    public function test_subdomain_allows_keeping_same_username(): void
    {
        $user = User::factory()->create(['username' => 'myname']);

        $response = $this->actingAs($user)
            ->put('/dashboard/subdomain', ['username' => 'myname']);

        $response->assertRedirect(route('dashboard.subdomain.edit'))
            ->assertSessionHas('status');
    }

    public function test_subdomain_service_reserved_check(): void
    {
        $service = new SubdomainService;

        $this->assertTrue($service->isReserved('admin'));
        $this->assertTrue($service->isReserved('api'));
        $this->assertFalse($service->isReserved('myproject'));
    }

    public function test_subdomain_service_availability_check(): void
    {
        $user = User::factory()->create(['username' => 'taken']);
        $service = new SubdomainService;

        $this->assertFalse($service->isAvailable('taken'));
        $this->assertTrue($service->isAvailable('taken', $user->id));
        $this->assertTrue($service->isAvailable('available'));
        $this->assertFalse($service->isAvailable('admin'));
    }
}
