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

it('allows tunnel requests through without authentication (optional auth)', function () {
    $this->get(route('dashboard'), ['CF-Connecting-IP' => '1.2.3.4'])
        ->assertSuccessful();
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

it('allows tunnel requests through to specific dashboard pages without authentication', function () {
    // With optional auth, tunnel requests are allowed through
    $this->get(route('dashboard.settings'), ['CF-Connecting-IP' => '1.2.3.4'])
        ->assertSuccessful();
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
