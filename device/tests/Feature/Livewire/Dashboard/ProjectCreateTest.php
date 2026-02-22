<?php

declare(strict_types=1);

use App\Livewire\Dashboard\ProjectCreate;
use App\Models\GitHubCredential;
use App\Models\Project;
use App\Services\Projects\ProjectCloneService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;
use VibecodePC\Common\Enums\ProjectStatus;

beforeEach(function () {
    Process::fake();
});

it('renders the mode selection on step 0', function () {
    Livewire::test(ProjectCreate::class)
        ->assertStatus(200)
        ->assertSet('step', 0)
        ->assertSee('New Project')
        ->assertSee('Clone from GitHub')
        ->assertSee('Clone from URL');
});

it('advances to step 1 in template mode', function () {
    Livewire::test(ProjectCreate::class)
        ->call('selectMode', 'template')
        ->assertSet('step', 1)
        ->assertSet('mode', 'template')
        ->assertSee('Framework');
});

it('advances to step 1 in git-url mode', function () {
    Livewire::test(ProjectCreate::class)
        ->call('selectMode', 'git-url')
        ->assertSet('step', 1)
        ->assertSet('mode', 'git-url')
        ->assertSee('Git URL');
});

it('shows framework options in template mode', function () {
    Livewire::test(ProjectCreate::class)
        ->call('selectMode', 'template')
        ->assertSee('Laravel')
        ->assertSee('Next.js')
        ->assertSee('Astro')
        ->assertSee('FastAPI')
        ->assertSee('Static HTML')
        ->assertSee('Custom');
});

it('validates required fields before proceeding in template mode', function () {
    Livewire::test(ProjectCreate::class)
        ->call('selectMode', 'template')
        ->call('nextStep')
        ->assertHasErrors(['name', 'framework']);
});

it('advances to step 2 with valid template input', function () {
    Livewire::test(ProjectCreate::class)
        ->call('selectMode', 'template')
        ->set('name', 'Test Project')
        ->set('framework', 'laravel')
        ->call('nextStep')
        ->assertSet('step', 2);
});

it('prevents duplicate project names', function () {
    Project::factory()->create(['name' => 'Existing']);

    Livewire::test(ProjectCreate::class)
        ->call('selectMode', 'template')
        ->set('name', 'Existing')
        ->set('framework', 'laravel')
        ->call('nextStep')
        ->assertHasErrors('name');
});

it('can go back from step 1 to step 0', function () {
    Livewire::test(ProjectCreate::class)
        ->call('selectMode', 'template')
        ->assertSet('step', 1)
        ->call('back')
        ->assertSet('step', 0)
        ->assertSet('mode', '');
});

it('can go back from step 2 to step 1', function () {
    Livewire::test(ProjectCreate::class)
        ->call('selectMode', 'template')
        ->set('name', 'Test')
        ->set('framework', 'laravel')
        ->call('nextStep')
        ->assertSet('step', 2)
        ->call('back')
        ->assertSet('step', 1);
});

// GitHub mode tests

it('loads repos when selecting github mode', function () {
    GitHubCredential::create([
        'access_token_encrypted' => 'gho_test_token',
        'github_username' => 'testuser',
        'github_email' => 'test@example.com',
        'github_name' => 'Test User',
        'has_copilot' => true,
    ]);

    Http::fake([
        'api.github.com/user/repos*' => Http::response([
            [
                'full_name' => 'testuser/my-repo',
                'name' => 'my-repo',
                'description' => 'A test repo',
                'private' => false,
                'default_branch' => 'main',
                'language' => 'PHP',
                'updated_at' => '2026-02-20T10:00:00Z',
            ],
        ]),
    ]);

    Livewire::test(ProjectCreate::class)
        ->call('selectMode', 'github')
        ->assertSet('step', 1)
        ->assertSet('mode', 'github')
        ->assertCount('repos', 1);
});

it('selects a repo and auto-populates name', function () {
    GitHubCredential::create([
        'access_token_encrypted' => 'gho_test_token',
        'github_username' => 'testuser',
        'github_email' => 'test@example.com',
        'github_name' => 'Test User',
        'has_copilot' => true,
    ]);

    Http::fake([
        'api.github.com/user/repos*' => Http::response([]),
    ]);

    Livewire::test(ProjectCreate::class)
        ->call('selectMode', 'github')
        ->call('selectRepo', 'testuser/my-repo')
        ->assertSet('selectedRepo', 'testuser/my-repo')
        ->assertSet('name', 'my-repo');
});

it('validates selected repo before proceeding in github mode', function () {
    GitHubCredential::create([
        'access_token_encrypted' => 'gho_test_token',
        'github_username' => 'testuser',
        'github_email' => 'test@example.com',
        'github_name' => 'Test User',
        'has_copilot' => true,
    ]);

    Http::fake([
        'api.github.com/user/repos*' => Http::response([]),
    ]);

    Livewire::test(ProjectCreate::class)
        ->call('selectMode', 'github')
        ->set('name', 'my-project')
        ->call('nextStep')
        ->assertHasErrors('selectedRepo');
});

it('clones a github repo and redirects to project detail', function () {
    GitHubCredential::create([
        'access_token_encrypted' => 'gho_test_token',
        'github_username' => 'testuser',
        'github_email' => 'test@example.com',
        'github_name' => 'Test User',
        'has_copilot' => true,
    ]);

    Http::fake([
        'api.github.com/user/repos*' => Http::response([]),
    ]);

    $mockCloneService = Mockery::mock(ProjectCloneService::class);
    $mockCloneService->shouldReceive('clone')
        ->once()
        ->andReturn(Project::factory()->create(['status' => ProjectStatus::Cloning]));

    app()->instance(ProjectCloneService::class, $mockCloneService);

    Livewire::test(ProjectCreate::class)
        ->call('selectMode', 'github')
        ->call('selectRepo', 'testuser/my-repo')
        ->set('name', 'my-repo')
        ->call('nextStep')
        ->assertSet('step', 2)
        ->call('cloneProject')
        ->assertRedirect();
});

// Git URL mode tests

it('validates git url format', function () {
    Livewire::test(ProjectCreate::class)
        ->call('selectMode', 'git-url')
        ->set('name', 'my-project')
        ->set('gitUrl', 'not-a-valid-url')
        ->call('nextStep')
        ->assertHasErrors('gitUrl');
});

it('accepts valid git URL and advances', function () {
    Livewire::test(ProjectCreate::class)
        ->call('selectMode', 'git-url')
        ->set('name', 'my-project')
        ->set('gitUrl', 'https://github.com/user/repo.git')
        ->call('nextStep')
        ->assertSet('step', 2);
});

it('clones from git url and redirects to project detail', function () {
    $mockCloneService = Mockery::mock(ProjectCloneService::class);
    $mockCloneService->shouldReceive('clone')
        ->once()
        ->with('my-project', 'https://github.com/user/repo.git')
        ->andReturn(Project::factory()->create(['status' => ProjectStatus::Cloning]));

    app()->instance(ProjectCloneService::class, $mockCloneService);

    Livewire::test(ProjectCreate::class)
        ->call('selectMode', 'git-url')
        ->set('name', 'my-project')
        ->set('gitUrl', 'https://github.com/user/repo.git')
        ->call('nextStep')
        ->call('cloneProject')
        ->assertRedirect();
});

it('disables github mode when no credential exists', function () {
    Livewire::test(ProjectCreate::class)
        ->assertSet('hasGitHub', false);
});

it('enables github mode when credential exists', function () {
    GitHubCredential::create([
        'access_token_encrypted' => 'gho_test_token',
        'github_username' => 'testuser',
        'github_email' => 'test@example.com',
        'github_name' => 'Test User',
        'has_copilot' => true,
    ]);

    Livewire::test(ProjectCreate::class)
        ->assertSet('hasGitHub', true);
});
