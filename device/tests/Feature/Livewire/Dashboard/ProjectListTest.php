<?php

declare(strict_types=1);

use App\Livewire\Dashboard\ProjectList;
use App\Models\Project;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;
use VibecodePC\Common\Enums\ProjectFramework;

beforeEach(function () {
    Process::fake();
});

it('renders the project list', function () {
    Livewire::test(ProjectList::class)
        ->assertStatus(200)
        ->assertSee('Your Projects');
});

it('shows empty state when no projects', function () {
    Livewire::test(ProjectList::class)
        ->assertSee('No projects yet');
});

it('lists existing projects', function () {
    Project::factory()->forFramework(ProjectFramework::Laravel)->create(['name' => 'My Laravel App']);

    Livewire::test(ProjectList::class)
        ->assertSee('My Laravel App')
        ->assertSee('Laravel');
});

it('can start a project', function () {
    $project = Project::factory()->create();

    Livewire::test(ProjectList::class)
        ->call('startProject', $project->id);

    Process::assertRan('docker compose up -d');
});

it('can stop a project', function () {
    $project = Project::factory()->running()->create();

    Livewire::test(ProjectList::class)
        ->call('stopProject', $project->id);

    Process::assertRan('docker compose down');
});
