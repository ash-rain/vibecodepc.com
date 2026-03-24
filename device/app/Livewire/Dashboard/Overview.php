<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\AiProviderConfig;
use App\Models\CloudCredential;
use App\Models\GitHubCredential;
use App\Models\ProjectLog;
use App\Models\WizardProgress;
use App\Repositories\ProjectRepository;
use App\Services\DevicePairingService;
use App\Services\Tunnel\TunnelService;
use App\Services\WizardProgressService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use VibecodePC\Common\Enums\WizardStep;

#[Layout('layouts.dashboard', ['title' => 'Overview'])]
#[Title('Overview — VibeCodePC')]
class Overview extends Component
{
    public string $username = '';

    public int $projectCount = 0;

    public int $runningCount = 0;

    public bool $tunnelRunning = false;

    public bool $isPaired = false;

    public bool $tunnelAvailable = false;

    public int $aiProviderCount = 0;

    public bool $hasCopilot = false;

    /** @var array<int, array{message: string, type: string, created_at: string}> */
    public array $recentActivity = [];

    public bool $canContinueSetup = false;

    public function mount(TunnelService $tunnelService, ProjectRepository $projectRepository): void
    {
        $this->refreshStatus($tunnelService, $projectRepository);
        $this->checkIfSetupCanBeContinued();
    }

    /**
     * Check if the user can continue setup (wizard was completed but tunnel step was skipped).
     */
    private function checkIfSetupCanBeContinued(): void
    {
        $progressService = app(WizardProgressService::class);

        // Can continue setup if wizard is complete and tunnel step was skipped
        $this->canContinueSetup = $progressService->isWizardComplete() &&
            WizardProgress::where('step', WizardStep::Tunnel->value)
                ->where('status', 'skipped')
                ->exists();
    }

    /**
     * Navigate to the wizard tunnel step to continue setup.
     */
    public function continueSetup(): void
    {
        $this->redirect(route('wizard', ['step' => 'tunnel']));
    }

    /**
     * Poll for tunnel status updates when tunnel was skipped.
     * This allows the UI to auto-refresh when tunnel becomes available.
     */
    public function poll(TunnelService $tunnelService, ProjectRepository $projectRepository): void
    {
        $wasAvailable = $this->tunnelAvailable;
        $this->refreshStatus($tunnelService, $projectRepository);

        // If tunnel just became available, dispatch a browser event
        if (! $wasAvailable && $this->tunnelAvailable) {
            $this->dispatch('tunnel-available');
        }
    }

    private function refreshStatus(TunnelService $tunnelService, ProjectRepository $projectRepository): void
    {
        $credential = CloudCredential::current();
        $this->username = $credential?->cloud_username ?? 'User';

        $pairingService = app(DevicePairingService::class);

        $this->projectCount = $projectRepository->count();
        $this->runningCount = $projectRepository->countRunning();
        $this->tunnelRunning = $tunnelService->isRunning();
        $this->isPaired = $pairingService->isPaired() || $pairingService->isTunnelVerified();
        // tunnelAvailable is true only when tunnel was skipped but token file now exists
        $this->tunnelAvailable = $tunnelService->wasSkippedButNowAvailable();
        $this->aiProviderCount = AiProviderConfig::whereNotNull('validated_at')->count();
        $this->hasCopilot = GitHubCredential::current()?->hasCopilot() ?? false;

        $this->recentActivity = ProjectLog::with('project')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (ProjectLog $log) => [
                'message' => ($log->project?->name ?? 'System').': '.$log->message,
                'type' => $log->type,
                'created_at' => $log->created_at->diffForHumans(),
            ])
            ->all();
    }

    public function render()
    {
        return view('livewire.dashboard.overview');
    }
}
