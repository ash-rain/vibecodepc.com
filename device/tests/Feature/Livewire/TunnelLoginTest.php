<?php

declare(strict_types=1);

use App\Livewire\TunnelLogin;
use App\Models\DeviceState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    DeviceState::setValue('admin_password_hash', Hash::make('test-password'));
});

it('renders the tunnel login component', function () {
    Livewire::test(TunnelLogin::class)
        ->assertSuccessful()
        ->assertSee('Device Access')
        ->assertSee('Admin Password');
});

it('has empty password field initially', function () {
    Livewire::test(TunnelLogin::class)
        ->assertSet('password', '')
        ->assertSet('error', null);
});

it('validates password is required', function () {
    Livewire::test(TunnelLogin::class)
        ->set('password', '')
        ->call('authenticate')
        ->assertHasErrors(['password' => 'required'])
        ->assertNoRedirect();
});

it('rejects invalid password', function () {
    Livewire::test(TunnelLogin::class)
        ->set('password', 'wrong-password')
        ->call('authenticate')
        ->assertSet('error', 'Invalid password.')
        ->assertSet('password', '')
        ->assertNoRedirect();
});

it('rejects password when no admin password is set', function () {
    DeviceState::setValue('admin_password_hash', null);

    Livewire::test(TunnelLogin::class)
        ->set('password', 'any-password')
        ->call('authenticate')
        ->assertSet('error', 'Invalid password.')
        ->assertNoRedirect();
});

it('authenticates with correct password', function () {
    Livewire::test(TunnelLogin::class)
        ->set('password', 'test-password')
        ->call('authenticate')
        ->assertSet('error', null)
        ->assertRedirect(route('dashboard'));
});

it('sets tunnel_authenticated flag in session', function () {
    Livewire::test(TunnelLogin::class)
        ->set('password', 'test-password')
        ->call('authenticate');

    expect(session('tunnel_authenticated'))->toBeTrue();
});

it('redirects to intended url after login', function () {
    session()->put('tunnel_auth_intended_url', '/settings');

    Livewire::test(TunnelLogin::class)
        ->set('password', 'test-password')
        ->call('authenticate')
        ->assertRedirect('/settings');
});

it('redirects to dashboard when no intended url', function () {
    session()->forget('tunnel_auth_intended_url');

    Livewire::test(TunnelLogin::class)
        ->set('password', 'test-password')
        ->call('authenticate')
        ->assertRedirect(route('dashboard'));
});

it('clears password field on failed authentication', function () {
    Livewire::test(TunnelLogin::class)
        ->set('password', 'wrong-password')
        ->call('authenticate')
        ->assertSet('password', '');
});

it('preserves error message after validation failure', function () {
    Livewire::test(TunnelLogin::class)
        ->set('password', 'wrong-password')
        ->call('authenticate')
        ->assertSet('error', 'Invalid password.');
});

it('clears error on successful authentication', function () {
    Livewire::test(TunnelLogin::class)
        ->set('password', 'test-password')
        ->call('authenticate')
        ->assertSet('error', null);
});

it('accepts passwords with special characters', function () {
    DeviceState::setValue('admin_password_hash', Hash::make('p@ssw0rd!#$%'));

    Livewire::test(TunnelLogin::class)
        ->set('password', 'p@ssw0rd!#$%')
        ->call('authenticate')
        ->assertSet('error', null)
        ->assertRedirect(route('dashboard'));
});

it('accepts long passwords', function () {
    $longPassword = str_repeat('a', 255);
    DeviceState::setValue('admin_password_hash', Hash::make($longPassword));

    Livewire::test(TunnelLogin::class)
        ->set('password', $longPassword)
        ->call('authenticate')
        ->assertRedirect(route('dashboard'));
});

it('handles unicode passwords', function () {
    $unicodePassword = 'пароль';
    DeviceState::setValue('admin_password_hash', Hash::make($unicodePassword));

    Livewire::test(TunnelLogin::class)
        ->set('password', $unicodePassword)
        ->call('authenticate')
        ->assertRedirect(route('dashboard'));
});

it('removes intended url from session after redirect', function () {
    session()->put('tunnel_auth_intended_url', '/projects');

    Livewire::test(TunnelLogin::class)
        ->set('password', 'test-password')
        ->call('authenticate');

    expect(session('tunnel_auth_intended_url'))->toBeNull();
});

it('does not authenticate with empty stored hash', function () {
    DeviceState::setValue('admin_password_hash', '');

    Livewire::test(TunnelLogin::class)
        ->set('password', 'test-password')
        ->call('authenticate')
        ->assertSet('error', 'Invalid password.')
        ->assertNoRedirect();
});

it('handles multiple failed attempts correctly', function () {
    $component = Livewire::test(TunnelLogin::class);

    // First attempt
    $component->set('password', 'wrong1')
        ->call('authenticate')
        ->assertSet('error', 'Invalid password.');

    // Second attempt
    $component->set('password', 'wrong2')
        ->call('authenticate')
        ->assertSet('error', 'Invalid password.');

    // Third attempt with correct password - redirects, error not cleared but component unmounted
    $component->set('password', 'test-password')
        ->call('authenticate')
        ->assertRedirect(route('dashboard'));
});

it('uses device layout', function () {
    Livewire::test(TunnelLogin::class)
        ->assertSee('Device Access');
});
