<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_authentication(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_dashboard_shows_device_list(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->online()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk()
            ->assertViewIs('dashboard.index')
            ->assertViewHas('devices')
            ->assertViewHas('onlineCount', 1)
            ->assertViewHas('totalCount', 1)
            ->assertViewHas('currentTier')
            ->assertViewHas('activeSubdomainCount')
            ->assertSee('Online');
    }

    public function test_dashboard_shows_empty_state_when_no_devices(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk()
            ->assertSee('No devices paired yet')
            ->assertSee('Plug in your VibeCodePC')
            ->assertViewHas('totalCount', 0);
    }

    public function test_dashboard_only_shows_users_own_devices(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Device::factory()->online()->create(['user_id' => $user->id]);
        Device::factory()->online()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk()
            ->assertViewHas('totalCount', 1);
    }

    public function test_device_detail_requires_authentication(): void
    {
        $device = Device::factory()->claimed()->create();

        $response = $this->get("/dashboard/devices/{$device->id}");

        $response->assertRedirect('/login');
    }

    public function test_device_detail_shows_device_info(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->online()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get("/dashboard/devices/{$device->id}");

        $response->assertOk()
            ->assertViewIs('dashboard.devices.show')
            ->assertViewHas('device');
    }

    public function test_device_detail_returns_403_for_non_owned_device(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $device = Device::factory()->claimed($otherUser)->create();

        $response = $this->actingAs($user)->get("/dashboard/devices/{$device->id}");

        $response->assertForbidden();
    }
}
