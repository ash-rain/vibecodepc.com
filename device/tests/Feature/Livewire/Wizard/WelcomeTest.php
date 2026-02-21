<?php

declare(strict_types=1);

use App\Livewire\Wizard\Welcome;
use App\Models\CloudCredential;
use App\Services\SystemService;
use App\Services\WizardProgressService;
use Livewire\Livewire;
use VibecodePC\Common\Enums\WizardStep;

beforeEach(function () {
    app(WizardProgressService::class)->seedProgress();
});

it('renders the welcome step', function () {
    Livewire::test(Welcome::class)
        ->assertStatus(200)
        ->assertSee('Welcome to VibeCodePC');
});

it('displays cloud credential info', function () {
    CloudCredential::create([
        'pairing_token_encrypted' => 'test-token',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => true,
        'paired_at' => now(),
    ]);

    Livewire::test(Welcome::class)
        ->assertSet('cloudUsername', 'testuser')
        ->assertSet('cloudEmail', 'test@example.com');
});

it('validates required fields', function () {
    Livewire::test(Welcome::class)
        ->set('adminPassword', '')
        ->set('acceptedTos', false)
        ->call('complete')
        ->assertHasErrors(['adminPassword', 'acceptedTos']);
});

it('validates password confirmation', function () {
    Livewire::test(Welcome::class)
        ->set('adminPassword', 'password123')
        ->set('adminPasswordConfirmation', 'different')
        ->set('timezone', 'UTC')
        ->set('acceptedTos', true)
        ->call('complete')
        ->assertHasErrors(['adminPassword']);
});

it('validates minimum password length', function () {
    Livewire::test(Welcome::class)
        ->set('adminPassword', 'short')
        ->set('adminPasswordConfirmation', 'short')
        ->set('timezone', 'UTC')
        ->set('acceptedTos', true)
        ->call('complete')
        ->assertHasErrors(['adminPassword']);
});

it('completes the welcome step with valid data', function () {
    $mock = Mockery::mock(SystemService::class);
    $mock->shouldReceive('getCurrentTimezone')->andReturn('UTC');
    $mock->shouldReceive('getAvailableTimezones')->andReturn(['UTC', 'America/New_York']);
    $mock->shouldReceive('setAdminPassword')->once()->andReturn(true);
    $mock->shouldReceive('setTimezone')->once()->andReturn(true);
    app()->instance(SystemService::class, $mock);

    Livewire::test(Welcome::class)
        ->set('adminPassword', 'securepassword')
        ->set('adminPasswordConfirmation', 'securepassword')
        ->set('timezone', 'UTC')
        ->set('acceptedTos', true)
        ->call('complete')
        ->assertHasNoErrors()
        ->assertDispatched('step-completed');

    expect(app(WizardProgressService::class)->isStepCompleted(WizardStep::Welcome))->toBeTrue();
});
