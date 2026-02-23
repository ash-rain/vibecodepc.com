<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use VibecodePC\Common\Enums\DeviceStatus;

class DevicePairingWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_redirects_to_login_when_unauthenticated_and_device_is_unclaimed(): void
    {
        $device = Device::factory()->create();

        $response = $this->get("/pair/{$device->uuid}");

        $response->assertRedirect(route('login'));
    }

    public function test_show_returns_404_for_non_existent_device(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/pair/'.Str::uuid());

        $response->assertStatus(404);
    }

    public function test_show_displays_claim_view_for_unclaimed_device_when_authenticated(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->create();

        $response = $this->actingAs($user)->get("/pair/{$device->uuid}");

        $response->assertOk()
            ->assertViewIs('pairing.claim')
            ->assertViewHas('device', $device)
            ->assertViewHas('user', $user);
    }

    public function test_show_displays_already_claimed_view_for_device_claimed_by_another_user(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $device = Device::factory()->claimed($owner)->create();

        $response = $this->actingAs($otherUser)->get("/pair/{$device->uuid}");

        $response->assertOk()
            ->assertViewIs('pairing.already-claimed')
            ->assertViewHas('device', $device);
    }

    public function test_show_displays_owned_view_for_device_claimed_by_current_user(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->claimed($user)->create();

        $response = $this->actingAs($user)->get("/pair/{$device->uuid}");

        $response->assertOk()
            ->assertViewIs('pairing.owned')
            ->assertViewHas('device', $device);
    }

    public function test_claim_claims_device_and_redirects_to_success(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['_token' => 'test'])
            ->post("/pair/{$device->uuid}/claim", ['_token' => 'test']);

        $response->assertRedirect(route('pairing.setup', $device->uuid));

        // Verify the device was claimed
        $device->refresh();
        $this->assertEquals(DeviceStatus::Claimed, $device->status);
        $this->assertEquals($user->id, $device->user_id);
        $this->assertNotNull($device->paired_at);
    }

    public function test_success_shows_success_view_for_device_owner(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->claimed($user)->create();

        $response = $this->actingAs($user)->get("/pair/{$device->uuid}/success");

        $response->assertOk()
            ->assertViewIs('pairing.success')
            ->assertViewHas('device', $device)
            ->assertViewHas('user', $user);
    }

    public function test_success_page_contains_continue_to_setup_link(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->claimed($user)->create();

        $response = $this->actingAs($user)->get("/pair/{$device->uuid}/success");

        $response->assertOk()
            ->assertSee('Continue to Setup')
            ->assertSee(route('pairing.setup', $device->uuid));
    }

    public function test_success_returns_403_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $device = Device::factory()->claimed($owner)->create();

        $response = $this->actingAs($otherUser)->get("/pair/{$device->uuid}/success");

        $response->assertStatus(403);
    }
}
