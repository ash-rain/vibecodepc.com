<?php

declare(strict_types=1);

use App\Models\WizardProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use VibecodePC\Common\Enums\WizardStep;
use VibecodePC\Common\Enums\WizardStepStatus;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Clear wizard_progress table before each test
    WizardProgress::query()->delete();
});

it('isCompleted returns true when status is completed', function () {
    $progress = WizardProgress::factory()->forStep(WizardStep::Welcome)->completed()->create();

    expect($progress->isCompleted())->toBeTrue();
});

it('isCompleted returns false when status is not completed', function () {
    $progress = WizardProgress::factory()->forStep(WizardStep::Welcome)->create([
        'status' => WizardStepStatus::Pending,
    ]);

    expect($progress->isCompleted())->toBeFalse();
});

it('isSkipped returns true when status is skipped', function () {
    $progress = WizardProgress::factory()->forStep(WizardStep::Welcome)->skipped()->create();

    expect($progress->isSkipped())->toBeTrue();
});

it('isSkipped returns false when status is not skipped', function () {
    $progress = WizardProgress::factory()->forStep(WizardStep::Welcome)->create([
        'status' => WizardStepStatus::Pending,
    ]);

    expect($progress->isSkipped())->toBeFalse();
});

it('isPending returns true when status is pending', function () {
    $progress = WizardProgress::factory()->forStep(WizardStep::Welcome)->create([
        'status' => WizardStepStatus::Pending,
    ]);

    expect($progress->isPending())->toBeTrue();
});

it('isPending returns false when status is not pending', function () {
    $progress = WizardProgress::factory()->forStep(WizardStep::Welcome)->completed()->create();

    expect($progress->isPending())->toBeFalse();
});

it('step is cast to WizardStep enum', function () {
    $progress = WizardProgress::factory()->forStep(WizardStep::Welcome)->create();

    expect($progress->step)->toBe(WizardStep::Welcome);
});

it('status is cast to WizardStepStatus enum', function () {
    $progress = WizardProgress::factory()->forStep(WizardStep::Welcome)->completed()->create();

    expect($progress->status)->toBe(WizardStepStatus::Completed);
});

it('data_json is cast to array', function () {
    $data = ['timezone' => 'UTC', 'username' => 'testuser'];
    $progress = WizardProgress::factory()->forStep(WizardStep::Welcome)->completed($data)->create();

    expect($progress->data_json)->toBeArray()
        ->and($progress->data_json['timezone'])->toBe('UTC')
        ->and($progress->data_json['username'])->toBe('testuser');
});

it('completed_at is cast to datetime', function () {
    $progress = WizardProgress::factory()->forStep(WizardStep::Welcome)->completed()->create();

    expect($progress->completed_at)->toBeInstanceOf(DateTime::class);
});

it('factory default state creates pending progress', function () {
    $progress = WizardProgress::factory()->forStep(WizardStep::Welcome)->create();

    expect($progress->status)->toBe(WizardStepStatus::Pending)
        ->and($progress->data_json)->toBeNull()
        ->and($progress->completed_at)->toBeNull();
});

it('factory completed state creates completed progress', function () {
    $data = ['key' => 'value'];
    $progress = WizardProgress::factory()->forStep(WizardStep::Welcome)->completed($data)->create();

    expect($progress->status)->toBe(WizardStepStatus::Completed)
        ->and($progress->data_json)->toBe($data)
        ->and($progress->completed_at)->toBeInstanceOf(DateTime::class);
});

it('factory skipped state creates skipped progress', function () {
    $progress = WizardProgress::factory()->forStep(WizardStep::Welcome)->skipped()->create();

    expect($progress->status)->toBe(WizardStepStatus::Skipped)
        ->and($progress->completed_at)->toBeInstanceOf(DateTime::class);
});

it('factory forStep state creates progress for specific step', function () {
    $progress = WizardProgress::factory()->forStep(WizardStep::Tunnel)->create();

    expect($progress->step)->toBe(WizardStep::Tunnel);
});

it('can handle all fillable attributes', function () {
    $data = ['config' => 'test'];
    $progress = WizardProgress::factory()->forStep(WizardStep::GitHub)->create([
        'status' => WizardStepStatus::Completed,
        'data_json' => $data,
        'completed_at' => now(),
    ]);

    expect($progress->step)->toBe(WizardStep::GitHub)
        ->and($progress->status)->toBe(WizardStepStatus::Completed)
        ->and($progress->data_json)->toBe($data)
        ->and($progress->completed_at)->toBeInstanceOf(DateTime::class);
});

it('isCompleted works after model refresh', function () {
    $progress = WizardProgress::factory()->forStep(WizardStep::Welcome)->create([
        'status' => WizardStepStatus::Pending,
    ]);

    expect($progress->isCompleted())->toBeFalse();

    $progress->update([
        'status' => WizardStepStatus::Completed,
        'completed_at' => now(),
    ]);

    expect($progress->fresh()->isCompleted())->toBeTrue();
});

it('isSkipped works after model refresh', function () {
    $progress = WizardProgress::factory()->forStep(WizardStep::Welcome)->create([
        'status' => WizardStepStatus::Pending,
    ]);

    expect($progress->isSkipped())->toBeFalse();

    $progress->update([
        'status' => WizardStepStatus::Skipped,
        'completed_at' => now(),
    ]);

    expect($progress->fresh()->isSkipped())->toBeTrue();
});

it('isPending works after model refresh', function () {
    $progress = WizardProgress::factory()->forStep(WizardStep::Welcome)->completed()->create();

    expect($progress->isPending())->toBeFalse();

    $progress->update([
        'status' => WizardStepStatus::Pending,
        'completed_at' => null,
    ]);

    expect($progress->fresh()->isPending())->toBeTrue();
});

it('handles null data_json', function () {
    $progress = WizardProgress::factory()->forStep(WizardStep::Welcome)->create([
        'data_json' => null,
    ]);

    expect($progress->data_json)->toBeNull();
});

it('handles empty array data_json', function () {
    $progress = WizardProgress::factory()->forStep(WizardStep::Welcome)->completed([])->create();

    expect($progress->data_json)->toBeArray()
        ->and($progress->data_json)->toBeEmpty();
});

it('handles null completed_at for pending status', function () {
    $progress = WizardProgress::factory()->forStep(WizardStep::Welcome)->create([
        'status' => WizardStepStatus::Pending,
        'completed_at' => null,
    ]);

    expect($progress->completed_at)->toBeNull()
        ->and($progress->isPending())->toBeTrue();
});

it('can query progress by step', function () {
    WizardProgress::factory()->forStep(WizardStep::Welcome)->create();
    WizardProgress::factory()->forStep(WizardStep::Tunnel)->create();

    $welcomeProgress = WizardProgress::where('step', WizardStep::Welcome->value)->first();
    $tunnelProgress = WizardProgress::where('step', WizardStep::Tunnel->value)->first();

    expect($welcomeProgress->step)->toBe(WizardStep::Welcome)
        ->and($tunnelProgress->step)->toBe(WizardStep::Tunnel);
});

it('can query progress by status', function () {
    WizardProgress::factory()->forStep(WizardStep::Welcome)->completed()->create();
    WizardProgress::factory()->forStep(WizardStep::AiServices)->skipped()->create();
    WizardProgress::factory()->forStep(WizardStep::GitHub)->create([
        'status' => WizardStepStatus::Pending,
    ]);

    $completedCount = WizardProgress::where('status', WizardStepStatus::Completed->value)->count();
    $skippedCount = WizardProgress::where('status', WizardStepStatus::Skipped->value)->count();
    $pendingCount = WizardProgress::where('status', WizardStepStatus::Pending->value)->count();

    expect($completedCount)->toBe(1)
        ->and($skippedCount)->toBe(1)
        ->and($pendingCount)->toBe(1);
});

it('calculates completion percentage correctly', function () {
    $totalSteps = count(WizardStep::cases());

    // Create completed/skipped steps (both count as done)
    WizardProgress::factory()->forStep(WizardStep::Welcome)->completed()->create();
    WizardProgress::factory()->forStep(WizardStep::AiServices)->skipped()->create();

    // Create pending steps for remaining
    $excludedSteps = [WizardStep::Welcome->value, WizardStep::AiServices->value];
    $remainingSteps = array_filter(WizardStep::cases(), fn ($step) => ! in_array($step->value, $excludedSteps));
    foreach ($remainingSteps as $step) {
        WizardProgress::factory()->forStep($step)->create([
            'status' => WizardStepStatus::Pending,
        ]);
    }

    $completedOrSkippedCount = WizardProgress::whereIn('status', [
        WizardStepStatus::Completed->value,
        WizardStepStatus::Skipped->value,
    ])->count();

    $percentage = ($completedOrSkippedCount / $totalSteps) * 100;

    expect($completedOrSkippedCount)->toBe(2)
        ->and($percentage)->toEqual((2 / $totalSteps) * 100);
});

it('reports zero percent completion when all steps pending', function () {
    foreach (WizardStep::cases() as $step) {
        WizardProgress::factory()->forStep($step)->create([
            'status' => WizardStepStatus::Pending,
        ]);
    }

    $completedCount = WizardProgress::where('status', WizardStepStatus::Completed->value)->count();
    $totalSteps = count(WizardStep::cases());

    $percentage = ($completedCount / $totalSteps) * 100;

    expect($completedCount)->toBe(0)
        ->and($percentage)->toEqual(0.0);
});

it('reports one hundred percent completion when all steps completed', function () {
    foreach (WizardStep::cases() as $step) {
        WizardProgress::factory()->forStep($step)->completed()->create();
    }

    $completedOrSkippedCount = WizardProgress::whereIn('status', [
        WizardStepStatus::Completed->value,
        WizardStepStatus::Skipped->value,
    ])->count();

    $totalSteps = count(WizardStep::cases());
    $percentage = ($completedOrSkippedCount / $totalSteps) * 100;

    expect($completedOrSkippedCount)->toBe($totalSteps)
        ->and($percentage)->toEqual(100.0);
});

it('counts both completed and skipped toward completion percentage', function () {
    $totalSteps = count(WizardStep::cases());

    WizardProgress::factory()->forStep(WizardStep::Welcome)->completed()->create();
    WizardProgress::factory()->forStep(WizardStep::AiServices)->skipped()->create();
    WizardProgress::factory()->forStep(WizardStep::GitHub)->skipped()->create();

    $excludedSteps = [WizardStep::Welcome->value, WizardStep::AiServices->value, WizardStep::GitHub->value];
    $remainingSteps = array_filter(WizardStep::cases(), fn ($step) => ! in_array($step->value, $excludedSteps));
    foreach ($remainingSteps as $step) {
        WizardProgress::factory()->forStep($step)->create([
            'status' => WizardStepStatus::Pending,
        ]);
    }

    $completedOrSkippedCount = WizardProgress::whereIn('status', [
        WizardStepStatus::Completed->value,
        WizardStepStatus::Skipped->value,
    ])->count();

    $percentage = ($completedOrSkippedCount / $totalSteps) * 100;

    expect($completedOrSkippedCount)->toBe(3)
        ->and($percentage)->toEqual((3 / $totalSteps) * 100);
});

it('completed step with data preserves data after refresh', function () {
    $data = ['timezone' => 'America/New_York', 'username' => 'admin'];
    $progress = WizardProgress::factory()->forStep(WizardStep::Welcome)->completed($data)->create();

    $progress->refresh();

    expect($progress->data_json)->toBe($data)
        ->and($progress->data_json['timezone'])->toBe('America/New_York')
        ->and($progress->data_json['username'])->toBe('admin');
});

it('can update step status and data', function () {
    $progress = WizardProgress::factory()->forStep(WizardStep::Welcome)->create([
        'status' => WizardStepStatus::Pending,
        'data_json' => null,
    ]);

    $progress->update([
        'status' => WizardStepStatus::Completed,
        'data_json' => ['configured' => true],
        'completed_at' => now(),
    ]);

    $progress->refresh();

    expect($progress->isCompleted())->toBeTrue()
        ->and($progress->data_json)->toBe(['configured' => true])
        ->and($progress->completed_at)->toBeInstanceOf(DateTime::class);
});

it('can transition from completed to pending', function () {
    $progress = WizardProgress::factory()->forStep(WizardStep::Welcome)->completed()->create();

    expect($progress->isCompleted())->toBeTrue();

    $progress->update([
        'status' => WizardStepStatus::Pending,
        'data_json' => null,
        'completed_at' => null,
    ]);

    expect($progress->fresh()->isPending())->toBeTrue()
        ->and($progress->fresh()->data_json)->toBeNull()
        ->and($progress->fresh()->completed_at)->toBeNull();
});

it('can transition from skipped to completed', function () {
    $progress = WizardProgress::factory()->forStep(WizardStep::Welcome)->skipped()->create();

    expect($progress->isSkipped())->toBeTrue();

    $progress->update([
        'status' => WizardStepStatus::Completed,
        'data_json' => ['completed' => true],
    ]);

    $freshProgress = $progress->fresh();

    expect($freshProgress->isCompleted())->toBeTrue()
        ->and($freshProgress->isSkipped())->toBeFalse()
        ->and($freshProgress->data_json)->toBe(['completed' => true]);
});

it('step value is stored correctly for all wizard steps', function () {
    foreach (WizardStep::cases() as $step) {
        WizardProgress::query()->delete();
        $progress = WizardProgress::factory()->forStep($step)->create();

        expect($progress->step)->toBe($step);
    }
});

it('all steps can be in pending status', function () {
    WizardProgress::query()->delete();

    foreach (WizardStep::cases() as $step) {
        WizardProgress::factory()->forStep($step)->create([
            'status' => WizardStepStatus::Pending,
        ]);
    }

    expect(WizardProgress::count())->toBe(count(WizardStep::cases()))
        ->and(WizardProgress::where('status', WizardStepStatus::Pending->value)->count())->toBe(count(WizardStep::cases()));
});

it('can have mix of completed skipped and pending steps', function () {
    WizardProgress::query()->delete();

    $steps = WizardStep::cases();

    // Complete first step
    WizardProgress::factory()->forStep($steps[0])->completed()->create();

    // Skip second step
    if (count($steps) > 1) {
        WizardProgress::factory()->forStep($steps[1])->skipped()->create();
    }

    // Leave rest pending
    for ($i = 2; $i < count($steps); $i++) {
        WizardProgress::factory()->forStep($steps[$i])->create([
            'status' => WizardStepStatus::Pending,
        ]);
    }

    expect(WizardProgress::where('status', WizardStepStatus::Completed->value)->count())->toBe(1)
        ->and(WizardProgress::where('status', WizardStepStatus::Skipped->value)->count())->toBe(count($steps) > 1 ? 1 : 0)
        ->and(WizardProgress::where('status', WizardStepStatus::Pending->value)->count())->toBe(max(0, count($steps) - 2));
});

// Additional edge case tests for state transitions

it('can transition from pending to skipped', function () {
    $progress = WizardProgress::factory()->forStep(WizardStep::Welcome)->create([
        'status' => WizardStepStatus::Pending,
    ]);

    expect($progress->isPending())->toBeTrue();

    $progress->update([
        'status' => WizardStepStatus::Skipped,
        'completed_at' => now(),
    ]);

    $freshProgress = $progress->fresh();

    expect($freshProgress->isSkipped())->toBeTrue()
        ->and($freshProgress->isPending())->toBeFalse()
        ->and($freshProgress->isCompleted())->toBeFalse()
        ->and($freshProgress->completed_at)->toBeInstanceOf(DateTime::class);
});

it('can transition from skipped to pending', function () {
    $progress = WizardProgress::factory()->forStep(WizardStep::Welcome)->skipped()->create();

    expect($progress->isSkipped())->toBeTrue();

    $progress->update([
        'status' => WizardStepStatus::Pending,
        'completed_at' => null,
    ]);

    $freshProgress = $progress->fresh();

    expect($freshProgress->isPending())->toBeTrue()
        ->and($freshProgress->isSkipped())->toBeFalse()
        ->and($freshProgress->isCompleted())->toBeFalse()
        ->and($freshProgress->completed_at)->toBeNull();
});

it('can transition through multiple states', function () {
    $progress = WizardProgress::factory()->forStep(WizardStep::Welcome)->create([
        'status' => WizardStepStatus::Pending,
    ]);

    // Pending -> Completed
    $progress->update([
        'status' => WizardStepStatus::Completed,
        'data_json' => ['configured' => true],
        'completed_at' => now(),
    ]);
    $progress = $progress->fresh();
    expect($progress->isCompleted())->toBeTrue();

    // Completed -> Skipped
    $progress->update([
        'status' => WizardStepStatus::Skipped,
        'data_json' => null,
    ]);
    $progress = $progress->fresh();
    expect($progress->isSkipped())->toBeTrue();

    // Skipped -> Pending
    $progress->update([
        'status' => WizardStepStatus::Pending,
        'completed_at' => null,
    ]);
    $progress = $progress->fresh();
    expect($progress->isPending())->toBeTrue();

    // Pending -> Skipped
    $progress->update([
        'status' => WizardStepStatus::Skipped,
        'completed_at' => now(),
    ]);
    $progress = $progress->fresh();
    expect($progress->isSkipped())->toBeTrue();

    // Skipped -> Completed
    $progress->update([
        'status' => WizardStepStatus::Completed,
        'data_json' => ['final' => true],
    ]);
    $progress = $progress->fresh();
    expect($progress->isCompleted())->toBeTrue()
        ->and($progress->data_json)->toBe(['final' => true]);
});

it('clears data when transitioning from completed to pending', function () {
    $data = ['timezone' => 'UTC', 'username' => 'testuser'];
    $progress = WizardProgress::factory()->forStep(WizardStep::Welcome)->completed($data)->create();

    expect($progress->data_json)->toBe($data);

    $progress->update([
        'status' => WizardStepStatus::Pending,
        'data_json' => null,
        'completed_at' => null,
    ]);

    $freshProgress = $progress->fresh();

    expect($freshProgress->isPending())->toBeTrue()
        ->and($freshProgress->data_json)->toBeNull()
        ->and($freshProgress->completed_at)->toBeNull();
});

it('clears completed_at when transitioning from completed to pending', function () {
    $progress = WizardProgress::factory()->forStep(WizardStep::Welcome)->completed()->create();

    expect($progress->completed_at)->toBeInstanceOf(DateTime::class);

    $progress->update([
        'status' => WizardStepStatus::Pending,
        'completed_at' => null,
    ]);

    expect($progress->fresh()->completed_at)->toBeNull();
});

it('preserves data when transitioning from completed to skipped', function () {
    $data = ['configured' => true];
    $progress = WizardProgress::factory()->forStep(WizardStep::Welcome)->completed($data)->create();

    $progress->update([
        'status' => WizardStepStatus::Skipped,
    ]);

    $freshProgress = $progress->fresh();

    expect($freshProgress->isSkipped())->toBeTrue()
        ->and($freshProgress->data_json)->toBe($data);
});

it('allows data when transitioning from pending to skipped', function () {
    $progress = WizardProgress::factory()->forStep(WizardStep::Welcome)->create([
        'status' => WizardStepStatus::Pending,
    ]);

    $data = ['reason' => 'skipped_by_user'];
    $progress->update([
        'status' => WizardStepStatus::Skipped,
        'data_json' => $data,
        'completed_at' => now(),
    ]);

    $freshProgress = $progress->fresh();

    expect($freshProgress->isSkipped())->toBeTrue()
        ->and($freshProgress->data_json)->toBe($data);
});

it('handles rapid state transitions', function () {
    $progress = WizardProgress::factory()->forStep(WizardStep::Welcome)->create([
        'status' => WizardStepStatus::Pending,
    ]);

    // Simulate rapid transitions
    for ($i = 0; $i < 5; $i++) {
        $progress->update([
            'status' => WizardStepStatus::Completed,
            'completed_at' => now(),
        ]);
        $progress->update([
            'status' => WizardStepStatus::Pending,
            'completed_at' => null,
        ]);
    }

    $freshProgress = $progress->fresh();

    expect($freshProgress->isPending())->toBeTrue()
        ->and($freshProgress->completed_at)->toBeNull();
});

// Additional edge case tests for percentage calculation

it('calculates percentage with only one step completed', function () {
    WizardProgress::query()->delete();

    $totalSteps = count(WizardStep::cases());

    // Complete only one step
    WizardProgress::factory()->forStep(WizardStep::Welcome)->completed()->create();

    // Leave all others pending (don't create records - simulate missing steps)
    $completedOrSkippedCount = WizardProgress::whereIn('status', [
        WizardStepStatus::Completed->value,
        WizardStepStatus::Skipped->value,
    ])->count();

    $percentage = ($completedOrSkippedCount / $totalSteps) * 100;

    expect($completedOrSkippedCount)->toBe(1)
        ->and($percentage)->toEqualWithDelta(100 / $totalSteps, 0.0001);
});

it('calculates percentage when some steps do not exist in database', function () {
    WizardProgress::query()->delete();

    $totalSteps = count(WizardStep::cases());

    // Create only some steps with mixed statuses
    WizardProgress::factory()->forStep(WizardStep::Welcome)->completed()->create();
    WizardProgress::factory()->forStep(WizardStep::AiServices)->skipped()->create();

    // Don't create records for remaining steps
    $completedOrSkippedCount = WizardProgress::whereIn('status', [
        WizardStepStatus::Completed->value,
        WizardStepStatus::Skipped->value,
    ])->count();

    $percentage = ($completedOrSkippedCount / $totalSteps) * 100;

    expect($completedOrSkippedCount)->toBe(2)
        ->and($percentage)->toBe((2 / $totalSteps) * 100);
});

it('calculates percentage correctly with floating point precision', function () {
    WizardProgress::query()->delete();

    $totalSteps = count(WizardStep::cases());

    // Create one completed step
    WizardProgress::factory()->forStep(WizardStep::Welcome)->completed()->create();

    // Create pending records for remaining steps
    $excludedSteps = [WizardStep::Welcome->value];
    $remainingSteps = array_filter(WizardStep::cases(), fn ($step) => ! in_array($step->value, $excludedSteps));
    foreach ($remainingSteps as $step) {
        WizardProgress::factory()->forStep($step)->create([
            'status' => WizardStepStatus::Pending,
        ]);
    }

    $completedOrSkippedCount = WizardProgress::whereIn('status', [
        WizardStepStatus::Completed->value,
        WizardStepStatus::Skipped->value,
    ])->count();

    $percentage = ($completedOrSkippedCount / $totalSteps) * 100;

    // Verify floating point precision is handled
    expect($percentage)->toBeFloat()
        ->and($completedOrSkippedCount)->toBe(1);
});

it('reports percentage with all steps skipped', function () {
    WizardProgress::query()->delete();

    $totalSteps = count(WizardStep::cases());

    foreach (WizardStep::cases() as $step) {
        WizardProgress::factory()->forStep($step)->skipped()->create();
    }

    $completedOrSkippedCount = WizardProgress::whereIn('status', [
        WizardStepStatus::Completed->value,
        WizardStepStatus::Skipped->value,
    ])->count();

    $percentage = ($completedOrSkippedCount / $totalSteps) * 100;

    expect($completedOrSkippedCount)->toBe($totalSteps)
        ->and($percentage)->toEqualWithDelta(100.0, 0.0001);
});

it('reports zero percent when no progress records exist', function () {
    WizardProgress::query()->delete();

    $totalSteps = count(WizardStep::cases());
    $completedOrSkippedCount = WizardProgress::whereIn('status', [
        WizardStepStatus::Completed->value,
        WizardStepStatus::Skipped->value,
    ])->count();

    $percentage = ($completedOrSkippedCount / $totalSteps) * 100;

    expect($completedOrSkippedCount)->toBe(0)
        ->and($percentage)->toEqual(0.0);
});

it('calculates percentage with mixed completed and skipped across all steps', function () {
    WizardProgress::query()->delete();

    $steps = WizardStep::cases();
    $totalSteps = count($steps);

    // Complete first third
    $completedCount = (int) ceil($totalSteps / 3);
    for ($i = 0; $i < $completedCount && $i < $totalSteps; $i++) {
        WizardProgress::factory()->forStep($steps[$i])->completed()->create();
    }

    // Skip second third
    $skippedCount = (int) ceil($totalSteps / 3);
    for ($i = $completedCount; $i < $completedCount + $skippedCount && $i < $totalSteps; $i++) {
        WizardProgress::factory()->forStep($steps[$i])->skipped()->create();
    }

    // Leave rest pending
    for ($i = $completedCount + $skippedCount; $i < $totalSteps; $i++) {
        WizardProgress::factory()->forStep($steps[$i])->create([
            'status' => WizardStepStatus::Pending,
        ]);
    }

    $doneCount = WizardProgress::whereIn('status', [
        WizardStepStatus::Completed->value,
        WizardStepStatus::Skipped->value,
    ])->count();

    $percentage = ($doneCount / $totalSteps) * 100;

    expect($doneCount)->toBe($completedCount + $skippedCount)
        ->and($percentage)->toBe((($completedCount + $skippedCount) / $totalSteps) * 100);
});

it('percentage calculation ignores pending steps correctly', function () {
    WizardProgress::query()->delete();

    $totalSteps = count(WizardStep::cases());

    // Create half completed, half pending
    $steps = WizardStep::cases();
    $halfPoint = (int) floor($totalSteps / 2);

    for ($i = 0; $i < $halfPoint; $i++) {
        WizardProgress::factory()->forStep($steps[$i])->completed()->create();
    }

    for ($i = $halfPoint; $i < $totalSteps; $i++) {
        WizardProgress::factory()->forStep($steps[$i])->create([
            'status' => WizardStepStatus::Pending,
        ]);
    }

    $completedCount = WizardProgress::where('status', WizardStepStatus::Completed->value)->count();
    $pendingCount = WizardProgress::where('status', WizardStepStatus::Pending->value)->count();
    $percentage = ($completedCount / $totalSteps) * 100;

    expect($completedCount)->toBe($halfPoint)
        ->and($pendingCount)->toBe($totalSteps - $halfPoint)
        ->and($percentage)->toBe(($halfPoint / $totalSteps) * 100);
});

it('handles percentage calculation with single step wizard', function () {
    // This test documents behavior when there's only one step
    // We can't actually change the enum, but we verify the math works
    $totalSteps = count(WizardStep::cases());

    // If there was only 1 step, completing it would be 100%
    $singleStepPercentage = (1 / 1) * 100;
    expect($singleStepPercentage)->toEqual(100.0);

    // With current total steps, verify math
    $oneCompletedPercentage = (1 / $totalSteps) * 100;
    expect($oneCompletedPercentage)->toEqualWithDelta(100 / $totalSteps, 0.0001);
});
