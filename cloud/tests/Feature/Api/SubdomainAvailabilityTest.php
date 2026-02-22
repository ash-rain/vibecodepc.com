<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubdomainAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_available_subdomain_returns_true(): void
    {
        $response = $this->getJson('/api/subdomains/mydevice/availability');

        $response->assertOk()
            ->assertJson([
                'available' => true,
                'reason' => null,
            ]);
    }

    public function test_taken_subdomain_returns_false(): void
    {
        User::factory()->create(['username' => 'taken']);

        $response = $this->getJson('/api/subdomains/taken/availability');

        $response->assertOk()
            ->assertJson([
                'available' => false,
                'reason' => 'This subdomain is already taken or reserved.',
            ]);
    }

    public function test_reserved_subdomain_returns_false(): void
    {
        $response = $this->getJson('/api/subdomains/admin/availability');

        $response->assertOk()
            ->assertJson([
                'available' => false,
                'reason' => 'This subdomain is already taken or reserved.',
            ]);
    }

    public function test_invalid_format_returns_false(): void
    {
        $response = $this->getJson('/api/subdomains/ab/availability');

        $response->assertOk()
            ->assertJson([
                'available' => false,
                'reason' => 'Invalid subdomain format.',
            ]);
    }

    public function test_subdomain_starting_with_hyphen_returns_false(): void
    {
        $response = $this->getJson('/api/subdomains/-invalid/availability');

        $response->assertOk()
            ->assertJson([
                'available' => false,
                'reason' => 'Invalid subdomain format.',
            ]);
    }
}
