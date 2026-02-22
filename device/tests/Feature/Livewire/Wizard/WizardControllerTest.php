<?php

declare(strict_types=1);

use App\Livewire\Wizard\WizardController;
use App\Models\WizardProgress;
use App\Services\WizardProgressService;
use Livewire\Livewire;
use VibecodePC\Common\Enums\WizardStep;

it('renders the wizard controller', function () {
    Livewire::test(WizardController::class)
        ->assertStatus(200)
        ->assertSee('VibeCodePC Setup');
});

it('seeds progress on mount', function () {
    Livewire::test(WizardController::class);

    expect(WizardProgress::count())->toBe(count(WizardStep::cases()));
});

it('starts at the welcome step', function () {
    Livewire::test(WizardController::class)
        ->assertSet('currentStep', 'welcome');
});

it('redirects to dashboard when wizard is complete', function () {
    $service = new WizardProgressService;
    $service->seedProgress();

    foreach (WizardStep::cases() as $step) {
        $service->completeStep($step);
    }

    Livewire::test(WizardController::class)
        ->assertRedirect(route('dashboard'));
});

it('advances step on step-completed event', function () {
    $service = new WizardProgressService;
    $service->seedProgress();
    $service->completeStep(WizardStep::Welcome);

    Livewire::test(WizardController::class)
        ->assertSet('currentStep', 'tunnel');
});

it('allows navigation to completed steps', function () {
    $service = new WizardProgressService;
    $service->seedProgress();
    $service->completeStep(WizardStep::Welcome);

    Livewire::test(WizardController::class)
        ->call('navigateToStep', 'welcome')
        ->assertSet('currentStep', 'welcome');
});

it('prevents navigation to future steps', function () {
    $service = new WizardProgressService;
    $service->seedProgress();

    Livewire::test(WizardController::class)
        ->call('navigateToStep', 'github')
        ->assertSet('currentStep', 'welcome');
});

it('renders progress bar with correct step statuses', function () {
    $service = new WizardProgressService;
    $service->seedProgress();
    $service->completeStep(WizardStep::Welcome);

    Livewire::test(WizardController::class)
        ->assertSee('Welcome')
        ->assertSee('AI Services')
        ->assertSee('GitHub');
});
