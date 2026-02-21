<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\WizardProgress;
use Illuminate\Support\Collection;
use VibecodePC\Common\Enums\WizardStep;
use VibecodePC\Common\Enums\WizardStepStatus;

class WizardProgressService
{
    public function getCurrentStep(): WizardStep
    {
        foreach (WizardStep::cases() as $step) {
            $progress = WizardProgress::where('step', $step->value)->first();

            if (! $progress || $progress->isPending()) {
                return $step;
            }
        }

        return WizardStep::Complete;
    }

    public function completeStep(WizardStep $step, ?array $data = null): void
    {
        WizardProgress::updateOrCreate(
            ['step' => $step->value],
            [
                'status' => WizardStepStatus::Completed,
                'data_json' => $data,
                'completed_at' => now(),
            ],
        );
    }

    public function skipStep(WizardStep $step): void
    {
        WizardProgress::updateOrCreate(
            ['step' => $step->value],
            [
                'status' => WizardStepStatus::Skipped,
                'completed_at' => now(),
            ],
        );
    }

    public function isStepCompleted(WizardStep $step): bool
    {
        $progress = WizardProgress::where('step', $step->value)->first();

        return $progress !== null && $progress->isCompleted();
    }

    public function isStepAccessible(WizardStep $step): bool
    {
        $progress = WizardProgress::where('step', $step->value)->first();

        if ($progress && ($progress->isCompleted() || $progress->isSkipped())) {
            return true;
        }

        return $this->getCurrentStep() === $step;
    }

    public function isWizardComplete(): bool
    {
        foreach (WizardStep::cases() as $step) {
            $progress = WizardProgress::where('step', $step->value)->first();

            if (! $progress || $progress->isPending()) {
                return false;
            }
        }

        return true;
    }

    /** @return Collection<int, WizardProgress> */
    public function getProgress(): Collection
    {
        return WizardProgress::all();
    }

    /** @return array<string, mixed>|null */
    public function getStepData(WizardStep $step): ?array
    {
        return WizardProgress::where('step', $step->value)->value('data_json');
    }

    public function resetWizard(): void
    {
        WizardProgress::truncate();
        $this->seedProgress();
    }

    public function seedProgress(): void
    {
        if (WizardProgress::count() > 0) {
            return;
        }

        foreach (WizardStep::cases() as $step) {
            WizardProgress::create([
                'step' => $step->value,
                'status' => WizardStepStatus::Pending,
            ]);
        }
    }
}
