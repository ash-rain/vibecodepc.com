<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk()
            ->assertSee('Profile Settings')
            ->assertSee($user->name)
            ->assertSee($user->email);
    }

    public function test_profile_page_requires_authentication(): void
    {
        $response = $this->get('/profile');

        $response->assertRedirect('/login');
    }

    public function test_profile_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put('/profile', [
            'name' => 'Updated Name',
            'username' => 'newusername',
            'email' => 'updated@example.com',
        ]);

        $response->assertRedirect('/profile')
            ->assertSessionHas('profile_success');

        $user->refresh();
        $this->assertEquals('Updated Name', $user->name);
        $this->assertEquals('newusername', $user->username);
        $this->assertEquals('updated@example.com', $user->email);
    }

    public function test_email_verification_resets_when_email_changes(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->put('/profile', [
            'name' => $user->name,
            'username' => $user->username,
            'email' => 'newemail@example.com',
        ]);

        $user->refresh();
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_not_reset_when_email_unchanged(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->put('/profile', [
            'name' => 'New Name',
            'username' => $user->username,
            'email' => $user->email,
        ]);

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_profile_update_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put('/profile', [
            'name' => '',
            'username' => '',
            'email' => '',
        ]);

        $response->assertSessionHasErrors(['name', 'username', 'email']);
    }

    public function test_profile_update_validates_unique_email(): void
    {
        $existingUser = User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put('/profile', [
            'name' => $user->name,
            'username' => $user->username,
            'email' => 'taken@example.com',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_profile_update_validates_unique_username(): void
    {
        $existingUser = User::factory()->create(['username' => 'taken']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put('/profile', [
            'name' => $user->name,
            'username' => 'taken',
            'email' => $user->email,
        ]);

        $response->assertSessionHasErrors(['username']);
    }

    public function test_profile_update_allows_own_email(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put('/profile', [
            'name' => 'Updated',
            'username' => $user->username,
            'email' => $user->email,
        ]);

        $response->assertRedirect('/profile')
            ->assertSessionHas('profile_success');
    }

    public function test_profile_update_validates_username_format(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put('/profile', [
            'name' => $user->name,
            'username' => 'INVALID_USERNAME',
            'email' => $user->email,
        ]);

        $response->assertSessionHasErrors(['username']);
    }

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'old-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertRedirect('/profile')
            ->assertSessionHas('password_success');

        $user->refresh();
        $this->assertTrue(Hash::check('new-password-123', $user->password));
    }

    public function test_password_update_requires_correct_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertSessionHasErrors(['current_password']);
    }

    public function test_password_update_requires_confirmation(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'old-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertSessionHasErrors(['password']);
    }

    public function test_nav_shows_user_name(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk()
            ->assertSee($user->name);
    }
}
