<?php

declare(strict_types=1);

use App\Models\WizardProgress;
use App\Services\WizardProgressService;
use VibecodePC\Common\Enums\WizardStep;
use VibecodePC\Common\Enums\WizardStepStatus;

beforeEach(function () {
    $this->service = new WizardProgressService;
});

it('seeds progress for all wizard steps', function () {
    $this->service->seedProgress();

    expect(WizardProgress::count())->toBe(count(WizardStep::cases()));

    foreach (WizardStep::cases() as $step) {
        $progress = WizardProgress::where('step', $step->value)->first();
        expect($progress)->not->toBeNull()
            ->and($progress->status)->toBe(WizardStepStatus::Pending);
    }
});

it('does not re-seed when progress already exists', function () {
    $this->service->seedProgress();
    $this->service->seedProgress();

    expect(WizardProgress::count())->toBe(count(WizardStep::cases()));
});

it('returns first pending step as current', function () {
    $this->service->seedProgress();

    expect($this->service->getCurrentStep())->toBe(WizardStep::Welcome);
});

it('advances to next step after completing current', function () {
    $this->service->seedProgress();
    $this->service->completeStep(WizardStep::Welcome, ['timezone' => 'UTC']);

    expect($this->service->getCurrentStep())->toBe(WizardStep::AiServices);
});

it('advances to next step after skipping current', function () {
    $this->service->seedProgress();
    $this->service->completeStep(WizardStep::Welcome);
    $this->service->skipStep(WizardStep::AiServices);

    expect($this->service->getCurrentStep())->toBe(WizardStep::GitHub);
});

it('marks step as completed with data', function () {
    $this->service->seedProgress();
    $data = ['timezone' => 'America/New_York'];
    $this->service->completeStep(WizardStep::Welcome, $data);

    $progress = WizardProgress::where('step', WizardStep::Welcome->value)->first();

    expect($progress->isCompleted())->toBeTrue()
        ->and($progress->data_json)->toBe($data)
        ->and($progress->completed_at)->not->toBeNull();
});

it('reports step completion status correctly', function () {
    $this->service->seedProgress();

    expect($this->service->isStepCompleted(WizardStep::Welcome))->toBeFalse();

    $this->service->completeStep(WizardStep::Welcome);

    expect($this->service->isStepCompleted(WizardStep::Welcome))->toBeTrue();
});

it('checks step accessibility for completed and current steps', function () {
    $this->service->seedProgress();

    expect($this->service->isStepAccessible(WizardStep::Welcome))->toBeTrue()
        ->and($this->service->isStepAccessible(WizardStep::AiServices))->toBeFalse();

    $this->service->completeStep(WizardStep::Welcome);

    expect($this->service->isStepAccessible(WizardStep::Welcome))->toBeTrue()
        ->and($this->service->isStepAccessible(WizardStep::AiServices))->toBeTrue()
        ->and($this->service->isStepAccessible(WizardStep::GitHub))->toBeFalse();
});

it('detects wizard completion', function () {
    $this->service->seedProgress();

    expect($this->service->isWizardComplete())->toBeFalse();

    foreach (WizardStep::cases() as $step) {
        $this->service->completeStep($step);
    }

    expect($this->service->isWizardComplete())->toBeTrue();
});

it('detects wizard completion with skipped steps', function () {
    $this->service->seedProgress();

    $this->service->completeStep(WizardStep::Welcome);
    $this->service->skipStep(WizardStep::AiServices);
    $this->service->skipStep(WizardStep::GitHub);
    $this->service->skipStep(WizardStep::CodeServer);
    $this->service->completeStep(WizardStep::Complete);

    expect($this->service->isWizardComplete())->toBeTrue();
});

it('retrieves step data', function () {
    $this->service->seedProgress();
    $data = ['timezone' => 'UTC', 'username' => 'testuser'];
    $this->service->completeStep(WizardStep::Welcome, $data);

    expect($this->service->getStepData(WizardStep::Welcome))->toBe($data)
        ->and($this->service->getStepData(WizardStep::AiServices))->toBeNull();
});

it('resets wizard and re-seeds', function () {
    $this->service->seedProgress();
    $this->service->completeStep(WizardStep::Welcome);

    $this->service->resetWizard();

    expect($this->service->getCurrentStep())->toBe(WizardStep::Welcome)
        ->and(WizardProgress::where('status', WizardStepStatus::Completed->value)->count())->toBe(0);
});

it('returns all progress rows', function () {
    $this->service->seedProgress();

    $progress = $this->service->getProgress();

    expect($progress)->toHaveCount(count(WizardStep::cases()));
});
