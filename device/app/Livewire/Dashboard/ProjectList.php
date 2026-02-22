<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\Project;
use App\Services\Docker\ProjectContainerService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\File;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.dashboard', ['title' => 'Projects'])]
#[Title('Projects — VibeCodePC')]
class ProjectList extends Component
{
    /** @var array<int, string> */
    public array $actionErrors = [];

    public function startProject(int $projectId, ProjectContainerService $containerService): void
    {
        $project = Project::findOrFail($projectId);
        $error = $containerService->start($project);

        if ($error !== null) {
            $this->actionErrors[$projectId] = $error;
        } else {
            unset($this->actionErrors[$projectId]);
        }
    }

    public function stopProject(int $projectId, ProjectContainerService $containerService): void
    {
        $project = Project::findOrFail($projectId);
        $error = $containerService->stop($project);

        if ($error !== null) {
            $this->actionErrors[$projectId] = $error;
        } else {
            unset($this->actionErrors[$projectId]);
        }
    }

    public function dismissError(int $projectId): void
    {
        unset($this->actionErrors[$projectId]);
    }

    public function deleteProject(int $projectId, ProjectContainerService $containerService): void
    {
        $project = Project::findOrFail($projectId);

        if ($project->isRunning()) {
            $containerService->stop($project);
        }

        $containerService->remove($project);

        if (File::isDirectory($project->path)) {
            File::deleteDirectory($project->path);
        }

        $project->delete();
    }

    public function openInVsCode(int $projectId): void
    {
        $project = Project::findOrFail($projectId);

        $this->redirect(route('dashboard.code-editor', ['folder' => $project->path]), navigate: false);
    }

    /** @return Collection<int, Project> */
    public function getProjectsProperty(): Collection
    {
        return Project::latest()->get();
    }

    public function render()
    {
        return view('livewire.dashboard.project-list');
    }
}
