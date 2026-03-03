<?php

declare(strict_types=1);

use App\Livewire\Wizard\Complete;
use App\Models\DeviceState;
use App\Services\CodeServer\CodeServerService;
use App\Services\DeviceStateService;
use App\Services\WizardProgressService;
use Livewire\Livewire;
use VibecodePC\Common\Enums\WizardStep;

beforeEach(function () {
    $service = app(WizardProgressService::class);
    $service->seedProgress();
    $service->completeStep(WizardStep::Welcome, ['timezone' => 'UTC']);
    $service->skipStep(WizardStep::AiServices);
    $service->skipStep(WizardStep::GitHub);
    $service->skipStep(WizardStep::CodeServer);
    $service->skipStep(WizardStep::Tunnel);

    $mock = Mockery::mock(CodeServerService::class);
    $mock->shouldReceive('getUrl')->andReturn('http://localhost:8443');
    app()->instance(CodeServerService::class, $mock);
});

it('renders the complete step', function () {
    Livewire::test(Complete::class)
        ->assertStatus(200)
        ->assertSee('Device Ready for Local Use');
});

it('shows summary of completed and skipped steps', function () {
    Livewire::test(Complete::class)
        ->assertSee('Welcome & Account')
        ->assertSee('Configured')
        ->assertSee('Skipped');
});

it('transitions to dashboard mode', function () {
    Livewire::test(Complete::class)
        ->call('goToDashboard')
        ->assertRedirect(route('home'));

    expect(DeviceState::getValue(DeviceStateService::MODE_KEY))->toBe(DeviceStateService::MODE_DASHBOARD);
});

it('builds summary excluding the complete step itself', function () {
    $component = Livewire::test(Complete::class);

    $summary = $component->get('summary');

    $stepValues = array_column($summary, 'step');

    expect($stepValues)->not->toContain('complete')
        ->and($summary)->toHaveCount(5);
});

it('shows Setup Complete message when tunnel is completed', function () {
    $service = app(WizardProgressService::class);
    $service->completeStep(WizardStep::Tunnel);

    Livewire::test(Complete::class)
        ->assertSee('Setup Complete')
        ->assertDontSee('Device Ready for Local Use');
});

it('shows Pair Device Now button when tunnel is skipped', function () {
    Livewire::test(Complete::class)
        ->assertSee('Pair Device Now')
        ->assertSee(route('dashboard.tunnels'));
});
