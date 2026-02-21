<?php

declare(strict_types=1);

use App\Livewire\Wizard\Tunnel;
use App\Services\CloudApiClient;
use App\Services\WizardProgressService;
use Livewire\Livewire;
use VibecodePC\Common\Enums\WizardStep;

beforeEach(function () {
    app(WizardProgressService::class)->seedProgress();
});

it('renders the tunnel step', function () {
    Livewire::test(Tunnel::class)
        ->assertStatus(200)
        ->assertSee('Tunnel Setup');
});

it('validates subdomain format', function () {
    Livewire::test(Tunnel::class)
        ->set('subdomain', 'a')
        ->call('checkAvailability')
        ->assertHasErrors(['subdomain']);
});

it('checks subdomain availability', function () {
    $mock = Mockery::mock(CloudApiClient::class);
    $mock->shouldReceive('checkSubdomainAvailability')
        ->with('testuser')
        ->once()
        ->andReturn(true);
    app()->instance(CloudApiClient::class, $mock);

    Livewire::test(Tunnel::class)
        ->set('subdomain', 'testuser')
        ->call('checkAvailability')
        ->assertSet('subdomainAvailable', true);
});

it('reports unavailable subdomain', function () {
    $mock = Mockery::mock(CloudApiClient::class);
    $mock->shouldReceive('checkSubdomainAvailability')
        ->with('taken')
        ->once()
        ->andReturn(false);
    app()->instance(CloudApiClient::class, $mock);

    Livewire::test(Tunnel::class)
        ->set('subdomain', 'taken')
        ->call('checkAvailability')
        ->assertSet('subdomainAvailable', false);
});

it('completes the tunnel step', function () {
    Livewire::test(Tunnel::class)
        ->call('complete')
        ->assertDispatched('step-completed');

    expect(app(WizardProgressService::class)->isStepCompleted(WizardStep::Tunnel))->toBeTrue();
});

it('skips the tunnel step', function () {
    Livewire::test(Tunnel::class)
        ->call('skip')
        ->assertDispatched('step-skipped');
});
