<?php

declare(strict_types=1);

use App\Livewire\Dashboard\ContainerMonitor;
use App\Models\Project;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;
use VibecodePC\Common\Enums\ProjectFramework;

beforeEach(function () {
    Process::fake();
});

it('renders the container monitor page', function () {
    Livewire::test(ContainerMonitor::class)
        ->assertStatus(200)
        ->assertSee('Containers');
});

it('shows empty state when no projects', function () {
    Livewire::test(ContainerMonitor::class)
        ->assertSee('No containers');
});

it('lists projects with their statuses', function () {
    Project::factory()->running()->forFramework(ProjectFramework::Laravel)->create(['name' => 'Running App']);
    Project::factory()->stopped()->forFramework(ProjectFramework::NextJs)->create(['name' => 'Stopped App']);

    Livewire::test(ContainerMonitor::class)
        ->assertSee('Running App')
        ->assertSee('Laravel')
        ->assertSee('Stopped App')
        ->assertSee('Next.js')
        ->assertSet('totalRunning', 1)
        ->assertSet('totalStopped', 1);
});

it('can start a project', function () {
    $project = Project::factory()->stopped()->create();

    Livewire::test(ContainerMonitor::class)
        ->call('startProject', $project->id);

    Process::assertRan(fn ($process) => str_contains($process->command, 'docker compose') && str_contains($process->command, 'up -d'));
});

it('can stop a project', function () {
    $project = Project::factory()->running()->create();

    Livewire::test(ContainerMonitor::class)
        ->call('stopProject', $project->id);

    Process::assertRan(fn ($process) => str_contains($process->command, 'docker compose') && str_ends_with($process->command, 'down'));
});

it('can restart a project', function () {
    $project = Project::factory()->running()->create();

    Livewire::test(ContainerMonitor::class)
        ->call('restartProject', $project->id);

    Process::assertRan(fn ($process) => str_contains($process->command, 'docker compose') && str_ends_with($process->command, 'down'));
    Process::assertRan(fn ($process) => str_contains($process->command, 'docker compose') && str_contains($process->command, 'up -d'));
});

it('can load logs for a project', function () {
    $project = Project::factory()->running()->create();

    Livewire::test(ContainerMonitor::class)
        ->call('loadLogs', $project->id);

    Process::assertRan(fn ($process) => str_contains($process->command, 'docker compose') && str_contains($process->command, 'logs --tail=100 --no-color'));
});

it('can run a command in a container', function () {
    $project = Project::factory()->running()->create();

    Livewire::test(ContainerMonitor::class)
        ->set("commandInputs.{$project->id}", 'ls -la')
        ->call('runCommand', $project->id);

    Process::assertRan(fn ($process) => str_contains($process->command, 'docker compose') && str_contains($process->command, 'exec -T app ls -la'));
});

it('does not run empty command', function () {
    $project = Project::factory()->running()->create();

    Livewire::test(ContainerMonitor::class)
        ->set("commandInputs.{$project->id}", '')
        ->call('runCommand', $project->id)
        ->assertSet('commandOutputs', []);
});

it('shows error when running command on stopped container', function () {
    $project = Project::factory()->stopped()->create();

    Livewire::test(ContainerMonitor::class)
        ->set("commandInputs.{$project->id}", 'ls')
        ->call('runCommand', $project->id)
        ->assertSet("commandOutputs.{$project->id}", 'No running container found.');
});

it('refreshes data on poll', function () {
    $project = Project::factory()->running()->create(['name' => 'Poll Test']);

    $component = Livewire::test(ContainerMonitor::class)
        ->assertSet('totalRunning', 1);

    $project->update(['status' => 'stopped', 'container_id' => null]);

    $component->call('poll')
        ->assertSet('totalRunning', 0)
        ->assertSet('totalStopped', 1);
});

it('is accessible at the containers route', function () {
    $this->get(route('dashboard.containers'))
        ->assertStatus(200);
});
