<?php

declare(strict_types=1);

namespace App\Livewire\Wizard;

use App\Models\QuickTunnel;
use App\Services\CloudApiClient;
use App\Services\DeviceRegistry\DeviceIdentityService;
use App\Services\Tunnel\QuickTunnelService;
use App\Services\WizardProgressService;
use Illuminate\Support\Facades\Log;
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

        // Allow re-entry via query parameter (e.g., ?step=tunnel)
        // This lets users continue setup from the dashboard
        $requestedStep = request()->query('step');

        if ($requestedStep !== null) {
            try {
                $wizardStep = WizardStep::from($requestedStep);

                // Allow navigation to any step if re-entering wizard
                // Reset the step to pending so user can complete it
                if ($progressService->isStepAccessible($wizardStep) || $progressService->isWizardComplete()) {
                    // If step was previously skipped or completed, reset it to pending
                    // so the user can complete it now
                    if ($progressService->isStepCompleted($wizardStep) ||
                        ($progressService->getProgress()->firstWhere('step', $wizardStep->value)?->isSkipped() ?? false)) {
                        $progressService->resetStep($wizardStep);
                    }

                    $this->currentStep = $wizardStep->value;
                    $this->loadSteps($progressService);

                    return;
                }
            } catch (\ValueError $e) {
                // Invalid step, fall through to normal flow
            }
        }

        if ($progressService->isWizardComplete()) {
            $this->redirect(route('dashboard'));

            return;
        }

        $this->currentStep = $progressService->getCurrentStep()->value;
        $this->loadSteps($progressService);

        // If a quick tunnel is running but its URL hasn't been registered
        // with the cloud yet (URL capture timed out during pairing), try now.
        $this->tryRegisterQuickTunnelUrl();
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

    private function tryRegisterQuickTunnelUrl(): void
    {
        $tunnel = QuickTunnel::forDashboard();

        if (! $tunnel) {
            return;
        }

        $url = $tunnel->tunnel_url;

        // If URL wasn't captured yet, try once more
        if (! $url) {
            $url = app(QuickTunnelService::class)->refreshUrl($tunnel);
        }

        if (! $url) {
            return;
        }

        $identity = app(DeviceIdentityService::class);

        if (! $identity->hasIdentity()) {
            return;
        }

        try {
            app(CloudApiClient::class)->registerTunnelUrl($identity->getDeviceInfo()->id, $url);
        } catch (\Throwable $e) {
            Log::warning('Failed to register tunnel URL with cloud from wizard', ['error' => $e->getMessage()]);
        }
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
            'tunnel' => 'Remote Access',
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
