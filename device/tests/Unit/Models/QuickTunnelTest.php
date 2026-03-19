<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\QuickTunnel;

it('belongs to a project', function () {
    $project = Project::factory()->create();
    $tunnel = QuickTunnel::factory()->forProject($project)->create();

    expect($tunnel->project)->toBeInstanceOf(Project::class)
        ->and($tunnel->project->id)->toBe($project->id);
});

it('project relationship returns null when project_id is null', function () {
    $tunnel = QuickTunnel::factory()->dashboard()->create();

    expect($tunnel->project)->toBeNull();
});

it('is running when status is running', function () {
    $tunnel = QuickTunnel::factory()->running()->create();

    expect($tunnel->isRunning())->toBeTrue()
        ->and($tunnel->isStarting())->toBeFalse()
        ->and($tunnel->isActive())->toBeTrue();
});

it('is starting when status is starting', function () {
    $tunnel = QuickTunnel::factory()->starting()->create();

    expect($tunnel->isRunning())->toBeFalse()
        ->and($tunnel->isStarting())->toBeTrue()
        ->and($tunnel->isActive())->toBeTrue();
});

it('is active when status is running or starting', function () {
    $runningTunnel = QuickTunnel::factory()->running()->create();
    $startingTunnel = QuickTunnel::factory()->starting()->create();

    expect($runningTunnel->isActive())->toBeTrue()
        ->and($startingTunnel->isActive())->toBeTrue();
});

it('is not active when status is stopped', function () {
    $tunnel = QuickTunnel::factory()->stopped()->create();

    expect($tunnel->isRunning())->toBeFalse()
        ->and($tunnel->isStarting())->toBeFalse()
        ->and($tunnel->isActive())->toBeFalse();
});

it('is not active when status is error', function () {
    $tunnel = QuickTunnel::factory()->error()->create();

    expect($tunnel->isRunning())->toBeFalse()
        ->and($tunnel->isStarting())->toBeFalse()
        ->and($tunnel->isActive())->toBeFalse();
});

it('returns most recent active dashboard tunnel', function () {
    $oldTunnel = QuickTunnel::factory()->stopped()->dashboard()->create([
        'created_at' => now()->subDays(2),
    ]);
    $activeTunnel = QuickTunnel::factory()->running()->dashboard()->create([
        'created_at' => now()->subDay(),
    ]);
    $newestTunnel = QuickTunnel::factory()->starting()->dashboard()->create([
        'created_at' => now(),
    ]);

    $dashboard = QuickTunnel::forDashboard();

    expect($dashboard)->not->toBeNull()
        ->and($dashboard->id)->toBe($newestTunnel->id);
});

it('returns null when no active dashboard tunnel exists', function () {
    QuickTunnel::factory()->stopped()->dashboard()->create();
    QuickTunnel::factory()->error()->dashboard()->create();

    $dashboard = QuickTunnel::forDashboard();

    expect($dashboard)->toBeNull();
});

it('returns most recent active tunnel for project', function () {
    $project = Project::factory()->create();
    $otherProject = Project::factory()->create();

    $oldTunnel = QuickTunnel::factory()->stopped()->forProject($project)->create([
        'created_at' => now()->subDays(2),
    ]);
    $activeTunnel = QuickTunnel::factory()->running()->forProject($project)->create([
        'created_at' => now()->subDay(),
    ]);
    $newestTunnel = QuickTunnel::factory()->starting()->forProject($project)->create([
        'created_at' => now(),
    ]);

    // Create tunnel for different project
    QuickTunnel::factory()->running()->forProject($otherProject)->create();

    $result = QuickTunnel::forProject($project->id);

    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($newestTunnel->id)
        ->and($result->project_id)->toBe($project->id);
});

it('returns null when no active tunnel exists for project', function () {
    $project = Project::factory()->create();

    QuickTunnel::factory()->stopped()->forProject($project)->create();
    QuickTunnel::factory()->error()->forProject($project)->create();

    $result = QuickTunnel::forProject($project->id);

    expect($result)->toBeNull();
});

it('casts local_port to integer', function () {
    $tunnel = QuickTunnel::factory()->create(['local_port' => '8080']);

    expect($tunnel->local_port)->toBeInt()
        ->and($tunnel->local_port)->toBe(8080);
});

it('casts started_at and stopped_at to datetime', function () {
    $tunnel = QuickTunnel::factory()->create([
        'started_at' => '2024-01-15 10:30:00',
        'stopped_at' => '2024-01-15 12:00:00',
    ]);

    expect($tunnel->started_at)->toBeInstanceOf(DateTime::class)
        ->and($tunnel->stopped_at)->toBeInstanceOf(DateTime::class);
});

it('dashboard scope excludes project-associated tunnels', function () {
    $project = Project::factory()->create();

    QuickTunnel::factory()->running()->forProject($project)->create();
    $dashboardTunnel = QuickTunnel::factory()->running()->dashboard()->create();

    $result = QuickTunnel::forDashboard();

    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($dashboardTunnel->id);
});

it('project scope filters by project id only', function () {
    $project = Project::factory()->create();

    QuickTunnel::factory()->running()->forProject($project)->create();
    QuickTunnel::factory()->starting()->forProject($project)->create();

    $result = QuickTunnel::forProject($project->id);

    expect($result)->not->toBeNull()
        ->and($result->project_id)->toBe($project->id);
});

it('handles non-existent project id', function () {
    $result = QuickTunnel::forProject(999999);

    expect($result)->toBeNull();
});

it('factory creates tunnel with project relationship', function () {
    $project = Project::factory()->create();
    $tunnel = QuickTunnel::factory()->forProject($project)->create();

    expect($tunnel->project_id)->toBe($project->id)
        ->and($tunnel->project)->toBeInstanceOf(Project::class);
});

it('factory creates dashboard tunnel with null project', function () {
    $tunnel = QuickTunnel::factory()->dashboard()->create();

    expect($tunnel->project_id)->toBeNull()
        ->and($tunnel->project)->toBeNull();
});

it('status transitions are tracked with timestamps', function () {
    $tunnel = QuickTunnel::factory()->starting()->create();

    expect($tunnel->status)->toBe('starting')
        ->and($tunnel->started_at)->not->toBeNull();

    $tunnel->update(['status' => 'running']);

    expect($tunnel->fresh()->status)->toBe('running');

    $tunnel->update(['status' => 'stopped', 'stopped_at' => now()]);

    expect($tunnel->fresh()->status)->toBe('stopped')
        ->and($tunnel->fresh()->stopped_at)->not->toBeNull();
});

it('multiple dashboard tunnels return newest active one', function () {
    $oldestActive = QuickTunnel::factory()->running()->dashboard()->create([
        'created_at' => now()->subDays(3),
    ]);
    $middleActive = QuickTunnel::factory()->starting()->dashboard()->create([
        'created_at' => now()->subDays(2),
    ]);
    $newestActive = QuickTunnel::factory()->running()->dashboard()->create([
        'created_at' => now()->subDay(),
    ]);

    $dashboard = QuickTunnel::forDashboard();

    expect($dashboard->id)->toBe($newestActive->id);
});
