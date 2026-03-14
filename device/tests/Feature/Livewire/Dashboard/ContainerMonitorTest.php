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
        ->assertSuccessful();
});

it('can subscribe to logs for a project', function () {
    $project = Project::factory()->running()->create();

    Livewire::test(ContainerMonitor::class)
        ->call('subscribeToLogs', $project->id)
        ->assertSet("openLogPanels.{$project->id}", true)
        ->assertSet("logs.{$project->id}", []);

    Process::assertRan(fn ($process) => str_contains($process->command, 'docker compose') && str_contains($process->command, 'logs --tail=100 --no-color'));
});

it('can unsubscribe from logs for a project', function () {
    $project = Project::factory()->running()->create();

    Livewire::test(ContainerMonitor::class)
        ->call('subscribeToLogs', $project->id)
        ->assertSet("openLogPanels.{$project->id}", true)
        ->call('unsubscribeFromLogs', $project->id)
        ->assertSet('openLogPanels', []);
});

it('refreshes open logs on poll', function () {
    $project = Project::factory()->running()->create();

    Livewire::test(ContainerMonitor::class)
        ->call('subscribeToLogs', $project->id)
        ->call('poll');

    Process::assertRan(fn ($process) => str_contains($process->command, 'docker compose') && str_contains($process->command, 'logs --tail=100 --no-color'));
});

it('does not refresh logs for unsubscribed projects on poll', function () {
    $project = Project::factory()->running()->create();

    Livewire::test(ContainerMonitor::class)
        ->call('poll');

    Process::assertNotRan(fn ($process) => str_contains($process->command, 'docker compose') && str_contains($process->command, 'logs'));
});

it('handles errors when starting a project', function () {
    $project = Project::factory()->stopped()->create();

    Process::fake([
        '*' => Process::result(errorOutput: 'Docker daemon not running', exitCode: 1),
    ]);

    Livewire::test(ContainerMonitor::class)
        ->call('startProject', $project->id)
        ->assertSet("actionErrors.{$project->id}", 'Docker daemon not running');
});

it('handles errors when stopping a project', function () {
    $project = Project::factory()->running()->create();

    Process::fake([
        '*' => Process::result(errorOutput: 'Container not found', exitCode: 1),
    ]);

    Livewire::test(ContainerMonitor::class)
        ->call('stopProject', $project->id)
        ->assertSet("actionErrors.{$project->id}", 'Container not found');
});

it('handles errors when restarting a project', function () {
    $project = Project::factory()->running()->create();

    Process::fake([
        '*' => Process::result(errorOutput: 'Permission denied', exitCode: 1),
    ]);

    Livewire::test(ContainerMonitor::class)
        ->call('restartProject', $project->id)
        ->assertSet("actionErrors.{$project->id}", 'Stop failed: Permission denied');
});

it('can dismiss error messages', function () {
    $project = Project::factory()->running()->create();

    Process::fake([
        '*' => Process::result(errorOutput: 'Some error', exitCode: 1),
    ]);

    Livewire::test(ContainerMonitor::class)
        ->call('stopProject', $project->id)
        ->assertSet("actionErrors.{$project->id}", 'Some error')
        ->call('dismissError', $project->id)
        ->assertSet('actionErrors', []);
});

it('clears error on successful action', function () {
    $project = Project::factory()->stopped()->create();

    Process::fake([
        '*' => Process::result(errorOutput: 'Initial error', exitCode: 1),
    ]);

    $component = Livewire::test(ContainerMonitor::class)
        ->call('startProject', $project->id)
        ->assertSet("actionErrors.{$project->id}", 'Initial error');

    Process::fake([
        '*' => Process::result(output: 'Success'),
    ]);

    $component->call('startProject', $project->id)
        ->assertSet('actionErrors', []);
});

it('clears command input after running command', function () {
    $project = Project::factory()->running()->create();

    Livewire::test(ContainerMonitor::class)
        ->set("commandInputs.{$project->id}", 'ls -la')
        ->call('runCommand', $project->id)
        ->assertSet("commandInputs.{$project->id}", '');
});

it('shows error state for projects with error status', function () {
    Project::factory()->create(['status' => 'error', 'name' => 'Error Project']);

    Livewire::test(ContainerMonitor::class)
        ->assertSee('Error Project')
        ->assertSet('totalError', 1);
});

it('displays container resource usage for running projects', function () {
    Project::factory()->running()->create(['name' => 'Resource Test']);

    Livewire::test(ContainerMonitor::class)
        ->assertSee('Resource Test')
        ->assertSee('CPU:');
});
