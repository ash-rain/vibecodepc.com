<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\GitHubCredential;
use App\Models\Project;
use App\Services\GitHub\GitHubRepoService;
use App\Services\Projects\ProjectCloneService;
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

    public int $step = 0;

    public string $error = '';

    public string $mode = '';

    public string $gitUrl = '';

    public string $selectedRepo = '';

    public string $repoSearch = '';

    /** @var array<int, array<string, mixed>> */
    public array $repos = [];

    public bool $loadingRepos = false;

    public bool $hasGitHub = false;

    /** @var array<int, array{value: string, label: string, port: int}> */
    public array $frameworks = [];

    public function mount(): void
    {
        $this->hasGitHub = GitHubCredential::current() !== null;

        foreach (ProjectFramework::cases() as $fw) {
            $this->frameworks[] = [
                'value' => $fw->value,
                'label' => $fw->label(),
                'port' => $fw->defaultPort(),
            ];
        }
    }

    public function selectMode(string $mode): void
    {
        $this->mode = $mode;
        $this->step = 1;
        $this->error = '';

        if ($mode === 'github') {
            $this->loadRepos(app(GitHubRepoService::class));
        }
    }

    public function loadRepos(GitHubRepoService $repoService): void
    {
        $credential = GitHubCredential::current();

        if (! $credential) {
            $this->error = 'No GitHub account connected.';

            return;
        }

        $this->loadingRepos = true;

        try {
            $repos = $repoService->listUserRepos($credential->getToken());

            $this->repos = array_map(fn ($repo) => [
                'fullName' => $repo->fullName,
                'name' => $repo->name,
                'description' => $repo->description,
                'isPrivate' => $repo->isPrivate,
                'language' => $repo->language,
            ], $repos);
        } catch (\Throwable $e) {
            $this->error = 'Failed to load repositories: '.$e->getMessage();
        } finally {
            $this->loadingRepos = false;
        }
    }

    public function searchRepos(GitHubRepoService $repoService): void
    {
        $credential = GitHubCredential::current();

        if (! $credential || $this->repoSearch === '') {
            if ($this->repoSearch === '' && $credential) {
                $this->loadRepos($repoService);
            }

            return;
        }

        $this->loadingRepos = true;

        try {
            $repos = $repoService->searchUserRepos($credential->getToken(), $this->repoSearch);

            $this->repos = array_map(fn ($repo) => [
                'fullName' => $repo->fullName,
                'name' => $repo->name,
                'description' => $repo->description,
                'isPrivate' => $repo->isPrivate,
                'language' => $repo->language,
            ], $repos);
        } catch (\Throwable $e) {
            $this->error = 'Search failed: '.$e->getMessage();
        } finally {
            $this->loadingRepos = false;
        }
    }

    public function selectRepo(string $fullName): void
    {
        $this->selectedRepo = $fullName;

        // Auto-populate project name from repo name
        $parts = explode('/', $fullName);
        $this->name = end($parts);
    }

    public function nextStep(): void
    {
        $this->error = '';

        if ($this->mode === 'template') {
            $this->validate([
                'name' => ['required', 'string', 'min:2', 'max:50'],
                'framework' => ['required', 'string'],
            ]);
        } elseif ($this->mode === 'github') {
            $this->validate([
                'name' => ['required', 'string', 'min:2', 'max:50'],
                'selectedRepo' => ['required', 'string'],
            ]);
        } elseif ($this->mode === 'git-url') {
            $this->validate([
                'name' => ['required', 'string', 'min:2', 'max:50'],
                'gitUrl' => ['required', 'string', 'regex:#^https?://.+\.git$|^git@.+:.+\.git$#'],
            ]);
        }

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
        $this->error = '';

        $framework = ProjectFramework::from($this->framework);
        $project = $scaffoldService->scaffold($this->name, $framework);

        $this->redirect(route('dashboard.projects.show', $project), navigate: false);
    }

    public function cloneProject(ProjectCloneService $cloneService): void
    {
        $this->error = '';

        try {
            if ($this->mode === 'github') {
                $credential = GitHubCredential::current();

                if (! $credential) {
                    $this->error = 'No GitHub account connected.';

                    return;
                }

                $repoService = app(GitHubRepoService::class);
                $cloneUrl = $repoService->authenticatedCloneUrl($credential->getToken(), $this->selectedRepo);
            } else {
                $cloneUrl = $this->gitUrl;
            }

            $project = $cloneService->clone($this->name, $cloneUrl);

            $this->redirect(route('dashboard.projects.show', $project), navigate: false);
        } catch (\Throwable $e) {
            $this->error = 'Clone failed: '.$e->getMessage();
        }
    }

    public function back(): void
    {
        if ($this->step === 1) {
            $this->step = 0;
            $this->mode = '';
            $this->error = '';
            $this->resetCloneState();
        } else {
            $this->step = 1;
        }
    }

    public function render()
    {
        return view('livewire.dashboard.project-create');
    }

    private function resetCloneState(): void
    {
        $this->gitUrl = '';
        $this->selectedRepo = '';
        $this->repoSearch = '';
        $this->repos = [];
    }
}
