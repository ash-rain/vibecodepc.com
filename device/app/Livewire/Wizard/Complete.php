<?php

declare(strict_types=1);

namespace App\Livewire\Wizard;

use App\Services\CodeServer\CodeServerService;
use App\Services\DeviceStateService;
use App\Services\WizardProgressService;
use Livewire\Component;
use VibecodePC\Common\Enums\WizardStep;

class Complete extends Component
{
    /** @var array<int, array{step: string, label: string, status: string}> */
    public array $summary = [];

    public string $codeServerUrl = '';

    public function mount(WizardProgressService $progressService, CodeServerService $codeServer): void
    {
        $this->codeServerUrl = $codeServer->getUrl();

        $labels = [
            'welcome' => 'Welcome & Account',
            'ai_services' => 'AI Services',
            'github' => 'GitHub',
            'code_server' => 'VS Code',
        ];

        foreach ($progressService->getProgress() as $progress) {
            if ($progress->step === WizardStep::Complete) {
                continue;
            }

            $this->summary[] = [
                'step' => $progress->step->value,
                'label' => $labels[$progress->step->value] ?? $progress->step->value,
                'status' => $progress->status->value,
            ];
        }
    }

    public function goToDashboard(WizardProgressService $progressService, DeviceStateService $stateService): void
    {
        $progressService->completeStep(WizardStep::Complete);
        $stateService->setMode(DeviceStateService::MODE_DASHBOARD);

        $this->redirect(route('home'));
    }

    public function render()
    {
        return view('livewire.wizard.complete');
    }
}
