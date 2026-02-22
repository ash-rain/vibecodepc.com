<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_page_requires_authentication(): void
    {
        $response = $this->get('/billing');

        $response->assertRedirect('/login');
    }

    public function test_billing_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/billing');

        $response->assertOk()
            ->assertSee('Billing')
            ->assertSee('Subscription')
            ->assertSee('Current Plan');
    }

    public function test_billing_page_shows_free_tier_for_unsubscribed_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/billing');

        $response->assertOk()
            ->assertSee('Free')
            ->assertSee('Upgrade')
            ->assertSee('Why upgrade?')
            ->assertSee('More subdomains')
            ->assertSee('200 GB bandwidth')
            ->assertSee('Priority support');
    }

    public function test_subscribe_page_requires_authentication(): void
    {
        $response = $this->get('/billing/subscribe');

        $response->assertRedirect('/login');
    }

    public function test_change_plan_requires_authentication(): void
    {
        $response = $this->post('/billing/change-plan', ['tier' => 'pro']);

        $response->assertRedirect('/login');
    }

    public function test_change_plan_validates_tier(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/billing/change-plan', [
            'tier' => 'invalid',
        ]);

        $response->assertSessionHasErrors(['tier']);
    }

    public function test_change_plan_redirects_without_subscription(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/billing/change-plan', [
            'tier' => 'pro',
        ]);

        $response->assertRedirect('/billing/subscribe')
            ->assertSessionHas('error');
    }

    public function test_cancel_requires_authentication(): void
    {
        $response = $this->post('/billing/cancel');

        $response->assertRedirect('/login');
    }

    public function test_resume_requires_authentication(): void
    {
        $response = $this->post('/billing/resume');

        $response->assertRedirect('/login');
    }

    public function test_billing_page_shows_change_plan_link(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/billing');

        $response->assertOk()
            ->assertSee(route('billing.subscribe'));
    }
}
