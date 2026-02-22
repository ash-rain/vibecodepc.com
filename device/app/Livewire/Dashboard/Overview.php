<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\AiProviderConfig;
use App\Models\CloudCredential;
use App\Models\GitHubCredential;
use App\Models\Project;
use App\Models\ProjectLog;
use App\Services\Tunnel\TunnelService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.dashboard', ['title' => 'Overview'])]
#[Title('Overview — VibeCodePC')]
class Overview extends Component
{
    public string $username = '';

    public int $projectCount = 0;

    public int $runningCount = 0;

    public bool $tunnelRunning = false;

    public int $aiProviderCount = 0;

    public bool $hasCopilot = false;

    /** @var array<int, array{message: string, type: string, created_at: string}> */
    public array $recentActivity = [];

    public function mount(TunnelService $tunnelService): void
    {
        $credential = CloudCredential::current();
        $this->username = $credential?->cloud_username ?? 'User';

        $this->projectCount = Project::count();
        $this->runningCount = Project::running()->count();
        $this->tunnelRunning = $tunnelService->isRunning();
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
