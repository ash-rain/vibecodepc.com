<?php

declare(strict_types=1);

use App\Livewire\Dashboard\ProjectDetail;
use App\Models\Project;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;
use VibecodePC\Common\Enums\ProjectFramework;

beforeEach(function () {
    Process::fake();
});

it('renders the project detail page', function () {
    $project = Project::factory()->forFramework(ProjectFramework::Laravel)->create(['name' => 'My App']);

    Livewire::test(ProjectDetail::class, ['project' => $project])
        ->assertStatus(200)
        ->assertSee('My App')
        ->assertSee('Laravel');
});

it('can start a project', function () {
    $project = Project::factory()->create();

    Livewire::test(ProjectDetail::class, ['project' => $project])
        ->call('start');

    Process::assertRan('docker compose up -d');
});

it('can stop a running project', function () {
    $project = Project::factory()->running()->create();

    Livewire::test(ProjectDetail::class, ['project' => $project])
        ->call('stop');

    Process::assertRan('docker compose down');
});

it('can add environment variables', function () {
    $project = Project::factory()->create();

    Livewire::test(ProjectDetail::class, ['project' => $project])
        ->set('newEnvKey', 'MY_KEY')
        ->set('newEnvValue', 'my_value')
        ->call('addEnvVar')
        ->assertSet('newEnvKey', '')
        ->assertSet('newEnvValue', '');

    expect($project->fresh()->env_vars)->toHaveKey('MY_KEY');
});

it('can remove environment variables', function () {
    $project = Project::factory()->create(['env_vars' => ['FOO' => 'bar']]);

    Livewire::test(ProjectDetail::class, ['project' => $project])
        ->call('removeEnvVar', 'FOO');

    expect($project->fresh()->env_vars)->not->toHaveKey('FOO');
});

it('shows preview button for running project', function () {
    $project = Project::factory()->running()->create(['port' => 3000]);

    Livewire::test(ProjectDetail::class, ['project' => $project])
        ->assertSeeHtml('http://localhost:3000')
        ->assertSee('Preview');
});

it('does not show preview button for stopped project', function () {
    $project = Project::factory()->stopped()->create(['port' => 3000]);

    Livewire::test(ProjectDetail::class, ['project' => $project])
        ->assertDontSee('Preview');
});

it('shows open editor button', function () {
    $project = Project::factory()->create();

    Livewire::test(ProjectDetail::class, ['project' => $project])
        ->assertSee('Open Editor');
});

it('can open the editor', function () {
    $project = Project::factory()->create();

    Livewire::test(ProjectDetail::class, ['project' => $project])
        ->call('openInEditor')
        ->assertRedirect(route('dashboard.code-editor', ['folder' => $project->path]));
});
