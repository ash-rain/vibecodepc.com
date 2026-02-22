<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\Project;
use App\Models\TunnelConfig;
use App\Services\Tunnel\TunnelService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.dashboard', ['title' => 'Tunnels'])]
#[Title('Tunnels — VibeCodePC')]
class TunnelManager extends Component
{
    public bool $tunnelInstalled = false;

    public bool $tunnelRunning = false;

    public bool $tunnelConfigured = false;

    public ?string $subdomain = null;

    public string $error = '';

    /** @var array<int, array{id: int, name: string, slug: string, port: int|null, tunnel_enabled: bool}> */
    public array $projects = [];

    public function mount(TunnelService $tunnelService): void
    {
        $status = $tunnelService->getStatus();
        $this->tunnelInstalled = $status['installed'];
        $this->tunnelRunning = $status['running'];
        $this->tunnelConfigured = $status['configured'];

        $tunnelConfig = TunnelConfig::current();
        $this->subdomain = $tunnelConfig?->subdomain;

        $this->loadProjects();
    }

    public function toggleProjectTunnel(int $projectId, TunnelService $tunnelService): void
    {
        $project = Project::findOrFail($projectId);
        $project->update([
            'tunnel_enabled' => ! $project->tunnel_enabled,
            'tunnel_subdomain_path' => ! $project->tunnel_enabled ? $project->slug : null,
        ]);

        $this->loadProjects();
        $this->syncIngress($tunnelService);
    }

    public function restartTunnel(TunnelService $tunnelService): void
    {
        $this->error = '';

        $stopError = $tunnelService->stop();

        if ($stopError !== null) {
            $this->error = $stopError;
            $this->tunnelRunning = $tunnelService->isRunning();

            return;
        }

        $startError = $tunnelService->start();

        if ($startError !== null) {
            $this->error = $startError;
        }

        $this->tunnelRunning = $tunnelService->isRunning();
    }

    public function render()
    {
        return view('livewire.dashboard.tunnel-manager');
    }

    private function loadProjects(): void
    {
        $this->projects = Project::all()->map(fn (Project $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'port' => $p->port,
            'tunnel_enabled' => $p->tunnel_enabled,
        ])->all();
    }

    private function syncIngress(TunnelService $tunnelService): void
    {
        if (! $this->subdomain) {
            return;
        }

        $routes = Project::where('tunnel_enabled', true)
            ->whereNotNull('tunnel_subdomain_path')
            ->whereNotNull('port')
            ->pluck('port', 'tunnel_subdomain_path')
            ->all();

        $tunnelService->updateIngress($this->subdomain, $routes);
    }
}
