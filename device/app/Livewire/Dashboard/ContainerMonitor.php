<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\Project;
use App\Services\Docker\ProjectContainerService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use VibecodePC\Common\Enums\ProjectStatus;

#[Layout('layouts.dashboard', ['title' => 'Containers'])]
#[Title('Containers — VibeCodePC')]
class ContainerMonitor extends Component
{
    /** @var array<int, array{id: int, name: string, framework_label: string, status: string, status_color: string, port: int|null, container_id: string|null, cpu: string, memory: string}> */
    public array $containers = [];

    /** @var array<int, array<int, string>> */
    public array $logs = [];

    /** @var array<int, string> */
    public array $commandOutputs = [];

    /** @var array<int, string> */
    public array $commandInputs = [];

    public int $totalRunning = 0;

    public int $totalStopped = 0;

    public int $totalError = 0;

    public function mount(): void
    {
        $this->loadContainers();
    }

    public function poll(): void
    {
        $this->loadContainers();
    }

    public function startProject(int $projectId, ProjectContainerService $containerService): void
    {
        $project = Project::findOrFail($projectId);
        $containerService->start($project);
        $this->loadContainers();
    }

    public function stopProject(int $projectId, ProjectContainerService $containerService): void
    {
        $project = Project::findOrFail($projectId);
        $containerService->stop($project);
        $this->loadContainers();
    }

    public function restartProject(int $projectId, ProjectContainerService $containerService): void
    {
        $project = Project::findOrFail($projectId);
        $containerService->restart($project);
        $this->loadContainers();
    }

    public function loadLogs(int $projectId, ProjectContainerService $containerService): void
    {
        $project = Project::findOrFail($projectId);
        $this->logs[$projectId] = $containerService->getLogs($project, 100);
    }

    public function runCommand(int $projectId, ProjectContainerService $containerService): void
    {
        $command = trim($this->commandInputs[$projectId] ?? '');

        if ($command === '') {
            return;
        }

        $project = Project::findOrFail($projectId);
        $result = $containerService->execCommand($project, $command);

        $this->commandOutputs[$projectId] = $result['output'];
        $this->commandInputs[$projectId] = '';
    }

    public function render()
    {
        return view('livewire.dashboard.container-monitor');
    }

    private function loadContainers(): void
    {
        $containerService = app(ProjectContainerService::class);
        $projects = Project::latest()->get();

        $this->totalRunning = 0;
        $this->totalStopped = 0;
        $this->totalError = 0;

        $this->containers = $projects->map(function (Project $project) use ($containerService) {
            match ($project->status) {
                ProjectStatus::Running => $this->totalRunning++,
                ProjectStatus::Stopped, ProjectStatus::Created, ProjectStatus::Scaffolding, ProjectStatus::Cloning => $this->totalStopped++,
                ProjectStatus::Error => $this->totalError++,
            };

            $usage = $project->isRunning() ? $containerService->getResourceUsage($project) : null;

            return [
                'id' => $project->id,
                'name' => $project->name,
                'framework_label' => $project->framework->label(),
                'status' => $project->status->label(),
                'status_color' => $project->status->color(),
                'port' => $project->port,
                'container_id' => $project->container_id,
                'cpu' => $usage['cpu'] ?? '-',
                'memory' => $usage['memory'] ?? '-',
            ];
        })->all();
    }
}
