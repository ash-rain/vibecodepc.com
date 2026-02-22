<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\Project;
use App\Services\Docker\ProjectContainerService;
use App\Services\Tunnel\TunnelService;
use Illuminate\Support\Facades\File;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.dashboard', ['title' => 'Project Detail'])]
class ProjectDetail extends Component
{
    public Project $project;

    /** @var array<string, string> */
    public array $envVars = [];

    public string $newEnvKey = '';

    public string $newEnvValue = '';

    /** @var array<int, string> */
    public array $containerLogs = [];

    /** @var array{cpu: string, memory: string}|null */
    public ?array $resourceUsage = null;

    public function mount(Project $project, ProjectContainerService $containerService): void
    {
        $this->project = $project;
        $this->envVars = $project->env_vars ?? [];

        if ($project->isRunning()) {
            $this->containerLogs = $containerService->getLogs($project, 30);
            $this->resourceUsage = $containerService->getResourceUsage($project);
        }
    }

    public function start(ProjectContainerService $containerService): void
    {
        $containerService->start($this->project);
        $this->project->refresh();
    }

    public function stop(ProjectContainerService $containerService): void
    {
        $containerService->stop($this->project);
        $this->project->refresh();
    }

    public function restart(ProjectContainerService $containerService): void
    {
        $containerService->restart($this->project);
        $this->project->refresh();
    }

    public function toggleTunnel(TunnelService $tunnelService): void
    {
        $this->project->update([
            'tunnel_enabled' => ! $this->project->tunnel_enabled,
            'tunnel_subdomain_path' => ! $this->project->tunnel_enabled ? $this->project->slug : null,
        ]);

        $this->project->refresh();

        $this->updateTunnelIngress($tunnelService);
    }

    public function saveEnvVars(): void
    {
        $this->project->update(['env_vars' => $this->envVars]);
    }

    public function addEnvVar(): void
    {
        if ($this->newEnvKey === '') {
            return;
        }

        $this->envVars[$this->newEnvKey] = $this->newEnvValue;
        $this->newEnvKey = '';
        $this->newEnvValue = '';
        $this->saveEnvVars();
    }

    public function removeEnvVar(string $key): void
    {
        unset($this->envVars[$key]);
        $this->saveEnvVars();
    }

    public function deleteProject(ProjectContainerService $containerService): void
    {
        if ($this->project->isRunning()) {
            $containerService->stop($this->project);
        }

        $containerService->remove($this->project);

        if (File::isDirectory($this->project->path)) {
            File::deleteDirectory($this->project->path);
        }

        $this->project->delete();
        $this->redirect(route('dashboard.projects'), navigate: false);
    }

    public function openInEditor(): void
    {
        $this->redirect(route('dashboard.code-editor', ['folder' => $this->project->path]), navigate: false);
    }

    public function refreshLogs(ProjectContainerService $containerService): void
    {
        $this->containerLogs = $containerService->getLogs($this->project, 30);
    }

    public function render()
    {
        return view('livewire.dashboard.project-detail');
    }

    private function updateTunnelIngress(TunnelService $tunnelService): void
    {
        $tunnelConfig = \App\Models\TunnelConfig::current();

        if (! $tunnelConfig) {
            return;
        }

        $routes = Project::where('tunnel_enabled', true)
            ->whereNotNull('tunnel_subdomain_path')
            ->whereNotNull('port')
            ->pluck('port', 'tunnel_subdomain_path')
            ->all();

        $tunnelService->updateIngress($tunnelConfig->subdomain, $routes);
    }
}
