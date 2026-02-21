<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WizardProgress;
use Illuminate\Database\Eloquent\Factories\Factory;
use VibecodePC\Common\Enums\WizardStep;
use VibecodePC\Common\Enums\WizardStepStatus;

/** @extends Factory<WizardProgress> */
class WizardProgressFactory extends Factory
{
    protected $model = WizardProgress::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'step' => fake()->randomElement(WizardStep::cases()),
            'status' => WizardStepStatus::Pending,
            'data_json' => null,
            'completed_at' => null,
        ];
    }

    public function completed(?array $data = null): static
    {
        return $this->state(fn () => [
            'status' => WizardStepStatus::Completed,
            'data_json' => $data,
            'completed_at' => now(),
        ]);
    }

    public function skipped(): static
    {
        return $this->state(fn () => [
            'status' => WizardStepStatus::Skipped,
            'completed_at' => now(),
        ]);
    }

    public function forStep(WizardStep $step): static
    {
        return $this->state(fn () => [
            'step' => $step,
        ]);
    }
}
