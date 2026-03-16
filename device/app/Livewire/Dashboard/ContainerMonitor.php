<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\Project;
use App\Repositories\ProjectRepository;
use App\Services\Docker\ProjectContainerService;
use Illuminate\Pagination\Cursor;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use VibecodePC\Common\Enums\ProjectStatus;

use function app;

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

    /** @var array<int, bool> */
    public array $openLogPanels = [];

    /** @var array<int, string> */
    public array $actionErrors = [];

    public int $totalRunning = 0;

    public int $totalStopped = 0;

    public int $totalError = 0;

    public ?string $nextCursor = null;

    public ?string $prevCursor = null;

    public bool $hasMorePages = false;

    public int $perPage = 10;

    public function mount(): void
    {
        $this->loadContainers();
    }

    public function poll(): void
    {
        $this->refreshVisibleContainers();
        $this->refreshTotals();
        $this->refreshOpenLogs();
    }

    public function subscribeToLogs(int $projectId): void
    {
        $this->openLogPanels[$projectId] = true;
        $this->loadLogs($projectId, app(ProjectContainerService::class), app(ProjectRepository::class));
    }

    public function unsubscribeFromLogs(int $projectId): void
    {
        unset($this->openLogPanels[$projectId]);
    }

    public function startProject(int $projectId, ProjectContainerService $containerService, ProjectRepository $projectRepository): void
    {
        $project = $projectRepository->findOrFail($projectId);
        $error = $containerService->start($project);

        if ($error !== null) {
            $this->actionErrors[$projectId] = $error;
        } else {
            unset($this->actionErrors[$projectId]);
        }

        $this->loadContainers();
    }

    public function stopProject(int $projectId, ProjectContainerService $containerService, ProjectRepository $projectRepository): void
    {
        $project = $projectRepository->findOrFail($projectId);
        $error = $containerService->stop($project);

        if ($error !== null) {
            $this->actionErrors[$projectId] = $error;
        } else {
            unset($this->actionErrors[$projectId]);
        }

        $this->loadContainers();
    }

    public function restartProject(int $projectId, ProjectContainerService $containerService, ProjectRepository $projectRepository): void
    {
        $project = $projectRepository->findOrFail($projectId);
        $error = $containerService->restart($project);

        if ($error !== null) {
            $this->actionErrors[$projectId] = $error;
        } else {
            unset($this->actionErrors[$projectId]);
        }

        $this->loadContainers();
    }

    public function dismissError(int $projectId): void
    {
        unset($this->actionErrors[$projectId]);
    }

    public function loadLogs(int $projectId, ProjectContainerService $containerService, ProjectRepository $projectRepository): void
    {
        $project = $projectRepository->findOrFail($projectId);
        $this->logs[$projectId] = $containerService->getLogs($project, 100);
    }

    public function runCommand(int $projectId, ProjectContainerService $containerService, ProjectRepository $projectRepository): void
    {
        $command = trim($this->commandInputs[$projectId] ?? '');

        if ($command === '') {
            return;
        }

        $project = $projectRepository->findOrFail($projectId);
        $result = $containerService->execCommand($project, $command);

        $this->commandOutputs[$projectId] = $result['output'];
        $this->commandInputs[$projectId] = '';
    }

    public function render()
    {
        return view('livewire.dashboard.container-monitor');
    }

    private function refreshOpenLogs(): void
    {
        $containerService = app(ProjectContainerService::class);
        $projectRepository = app(ProjectRepository::class);

        foreach ($this->openLogPanels as $projectId => $active) {
            if (! $active) {
                continue;
            }

            $project = $projectRepository->find($projectId);

            if ($project) {
                $this->logs[$projectId] = $containerService->getLogs($project, 100);
            }
        }
    }

    public function loadMore(): void
    {
        if ($this->nextCursor === null) {
            return;
        }

        $cursor = Cursor::fromEncoded($this->nextCursor);
        if ($cursor === null) {
            return;
        }

        $containerService = app(ProjectContainerService::class);
        $projectRepository = app(ProjectRepository::class);
        $paginator = $projectRepository->paginateLatest($this->perPage, $cursor);

        $this->hasMorePages = $paginator->hasMorePages();
        $this->nextCursor = $paginator->nextCursor()?->encode();

        $newContainers = $paginator->getCollection()->map(function (Project $project) use ($containerService) {
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

        $this->containers = array_merge($this->containers, $newContainers);
    }

    public function resetPagination(): void
    {
        $this->containers = [];
        $this->nextCursor = null;
        $this->hasMorePages = false;
        $this->loadContainers();
    }

    private function loadContainers(): void
    {
        $containerService = app(ProjectContainerService::class);
        $projectRepository = app(ProjectRepository::class);
        $paginator = $projectRepository->paginateLatest($this->perPage);

        $this->hasMorePages = $paginator->hasMorePages();
        $this->nextCursor = $paginator->nextCursor()?->encode();

        $projects = $paginator->getCollection();

        $this->totalRunning = $projectRepository->countByStatus(ProjectStatus::Running);
        $this->totalStopped = $projectRepository->countWhereStatusIn([
            ProjectStatus::Stopped,
            ProjectStatus::Created,
            ProjectStatus::Scaffolding,
            ProjectStatus::Cloning,
        ]);
        $this->totalError = $projectRepository->countByStatus(ProjectStatus::Error);

        $this->containers = $projects->map(function (Project $project) use ($containerService) {
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

    private function refreshVisibleContainers(): void
    {
        if (empty($this->containers)) {
            return;
        }

        $containerService = app(ProjectContainerService::class);
        $projectIds = array_column($this->containers, 'id');
        $projects = Project::whereIn('id', $projectIds)->get()->keyBy('id');

        $this->containers = array_map(function (array $container) use ($projects, $containerService) {
            $project = $projects->get($container['id']);

            if ($project === null) {
                return $container;
            }

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
        }, $this->containers);
    }

    private function refreshTotals(): void
    {
        $this->totalRunning = Project::where('status', ProjectStatus::Running)->count();
        $this->totalStopped = Project::whereIn('status', [
            ProjectStatus::Stopped,
            ProjectStatus::Created,
            ProjectStatus::Scaffolding,
            ProjectStatus::Cloning,
        ])->count();
        $this->totalError = Project::where('status', ProjectStatus::Error)->count();
    }
}
