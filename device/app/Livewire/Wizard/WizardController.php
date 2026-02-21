<?php

declare(strict_types=1);

namespace App\Livewire\Wizard;

use App\Services\WizardProgressService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use VibecodePC\Common\Enums\WizardStep;
use VibecodePC\Common\Enums\WizardStepStatus;

#[Layout('layouts.device')]
class WizardController extends Component
{
    public string $currentStep = '';

    /** @var array<int, array{step: string, label: string, status: string}> */
    public array $steps = [];

    public function mount(WizardProgressService $progressService): void
    {
        $progressService->seedProgress();

        if ($progressService->isWizardComplete()) {
            $this->redirect(route('dashboard'));

            return;
        }

        $this->currentStep = $progressService->getCurrentStep()->value;
        $this->loadSteps($progressService);
    }

    #[On('step-completed')]
    public function onStepCompleted(): void
    {
        $progressService = app(WizardProgressService::class);

        if ($progressService->isWizardComplete()) {
            $this->redirect(route('dashboard'));

            return;
        }

        $this->currentStep = $progressService->getCurrentStep()->value;
        $this->loadSteps($progressService);
    }

    #[On('step-skipped')]
    public function onStepSkipped(): void
    {
        $this->onStepCompleted();
    }

    public function navigateToStep(string $step): void
    {
        $wizardStep = WizardStep::from($step);
        $progressService = app(WizardProgressService::class);

        if ($progressService->isStepAccessible($wizardStep)) {
            $this->currentStep = $step;
        }
    }

    public function render()
    {
        return view('livewire.wizard.wizard-controller');
    }

    private function loadSteps(WizardProgressService $progressService): void
    {
        $this->steps = [];
        $progress = $progressService->getProgress()->keyBy(fn ($p) => $p->step->value);

        $labels = [
            'welcome' => 'Welcome',
            'ai_services' => 'AI Services',
            'github' => 'GitHub',
            'code_server' => 'VS Code',
            'tunnel' => 'Tunnel',
            'complete' => 'Done',
        ];

        foreach (WizardStep::cases() as $step) {
            $status = $progress->get($step->value)?->status ?? WizardStepStatus::Pending;

            $this->steps[] = [
                'step' => $step->value,
                'label' => $labels[$step->value] ?? $step->value,
                'status' => $status->value,
            ];
        }
    }
}
