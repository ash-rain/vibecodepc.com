<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\Project;
use App\Services\Projects\ProjectScaffoldService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use VibecodePC\Common\Enums\ProjectFramework;

#[Layout('layouts.dashboard', ['title' => 'New Project'])]
#[Title('New Project — VibeCodePC')]
class ProjectCreate extends Component
{
    public string $name = '';

    public string $framework = '';

    public int $step = 1;

    public bool $scaffolding = false;

    public string $error = '';

    /** @var array<int, array{value: string, label: string, port: int}> */
    public array $frameworks = [];

    public function mount(): void
    {
        foreach (ProjectFramework::cases() as $fw) {
            $this->frameworks[] = [
                'value' => $fw->value,
                'label' => $fw->label(),
                'port' => $fw->defaultPort(),
            ];
        }
    }

    public function nextStep(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:50'],
            'framework' => ['required', 'string'],
        ]);

        if (Project::where('name', $this->name)->exists()) {
            $this->addError('name', 'A project with this name already exists.');

            return;
        }

        $maxProjects = config('vibecodepc.projects.max_projects', 10);

        if (Project::count() >= $maxProjects) {
            $this->error = "Maximum of {$maxProjects} projects reached.";

            return;
        }

        $this->step = 2;
    }

    public function scaffold(ProjectScaffoldService $scaffoldService): void
    {
        $this->scaffolding = true;
        $this->error = '';

        $framework = ProjectFramework::from($this->framework);

        $project = $scaffoldService->scaffold($this->name, $framework);

        if ($project->status === \VibecodePC\Common\Enums\ProjectStatus::Error) {
            $this->error = 'Scaffolding failed. Check project logs for details.';
            $this->scaffolding = false;

            return;
        }

        $this->redirect(route('dashboard.projects.show', $project), navigate: false);
    }

    public function back(): void
    {
        $this->step = 1;
    }

    public function render()
    {
        return view('livewire.dashboard.project-create');
    }
}
