<?php

declare(strict_types=1);

use App\Livewire\TunnelLogin;
use App\Models\DeviceState;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    DeviceState::setValue('admin_password_hash', Hash::make('test-password'));
});

it('allows local requests through without authentication', function () {
    $this->get(route('dashboard'))
        ->assertSuccessful();
});

it('redirects tunnel requests to login when unauthenticated', function () {
    $this->get(route('dashboard'), ['CF-Connecting-IP' => '1.2.3.4'])
        ->assertRedirect(route('tunnel.login'));
});

it('renders the tunnel login page', function () {
    Livewire::test(TunnelLogin::class)
        ->assertStatus(200)
        ->assertSee('Device Access')
        ->assertSee('Admin Password');
});

it('rejects invalid password', function () {
    Livewire::test(TunnelLogin::class)
        ->set('password', 'wrong-password')
        ->call('authenticate')
        ->assertSet('error', 'Invalid password.')
        ->assertNoRedirect();
});

it('authenticates with correct password and redirects to dashboard', function () {
    Livewire::test(TunnelLogin::class)
        ->set('password', 'test-password')
        ->call('authenticate')
        ->assertRedirect(route('dashboard'));
});

it('stores tunnel_authenticated flag in session after login', function () {
    Livewire::test(TunnelLogin::class)
        ->set('password', 'test-password')
        ->call('authenticate');

    expect(session('tunnel_authenticated'))->toBeTrue();
});

it('allows authenticated tunnel requests through', function () {
    $this->withSession(['tunnel_authenticated' => true])
        ->get(route('dashboard'), ['CF-Connecting-IP' => '1.2.3.4'])
        ->assertSuccessful();
});

it('preserves intended URL through login flow', function () {
    // First request to a specific page gets redirected
    $this->get(route('dashboard.settings'), ['CF-Connecting-IP' => '1.2.3.4'])
        ->assertRedirect(route('tunnel.login'));

    // Intended URL should be stored in session
    expect(session('tunnel_auth_intended_url'))->toContain('/dashboard/settings');
});

it('rejects empty password', function () {
    Livewire::test(TunnelLogin::class)
        ->set('password', '')
        ->call('authenticate')
        ->assertHasErrors(['password']);
});

it('does not require auth on tunnel login page itself', function () {
    $this->get(route('tunnel.login'), ['CF-Connecting-IP' => '1.2.3.4'])
        ->assertSuccessful();
});

it('allows tunnel access to wizard without authentication', function () {
    $this->get(route('wizard'), ['CF-Connecting-IP' => '1.2.3.4'])
        ->assertSuccessful();
});

it('allows tunnel access to pairing without authentication', function () {
    $this->get(route('pairing'), ['CF-Connecting-IP' => '1.2.3.4'])
        ->assertSuccessful();
});
