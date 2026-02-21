<?php

declare(strict_types=1);

use App\Models\Project;
use App\Services\Docker\ProjectContainerService;
use Illuminate\Support\Facades\Process;
use VibecodePC\Common\Enums\ProjectStatus;

it('starts a project container', function () {
    Process::fake([
        'docker compose up -d' => Process::result(output: 'Started'),
        'docker compose ps -q' => Process::result(output: 'abc123'),
    ]);

    $project = Project::factory()->create();
    $service = new ProjectContainerService;

    $result = $service->start($project);

    expect($result)->toBeTrue();
    expect($project->fresh()->status)->toBe(ProjectStatus::Running);
});

it('stops a project container', function () {
    Process::fake([
        'docker compose down' => Process::result(output: 'Stopped'),
    ]);

    $project = Project::factory()->running()->create();
    $service = new ProjectContainerService;

    $result = $service->stop($project);

    expect($result)->toBeTrue();
    expect($project->fresh()->status)->toBe(ProjectStatus::Stopped);
});

it('checks if a container is running', function () {
    Process::fake([
        'docker compose ps --format json' => Process::result(output: '{"State":"running"}'),
    ]);

    $project = Project::factory()->running()->create();
    $service = new ProjectContainerService;

    expect($service->isRunning($project))->toBeTrue();
});

it('gets container logs', function () {
    Process::fake([
        'docker compose logs --tail=10 --no-color' => Process::result(output: "line1\nline2\nline3"),
    ]);

    $project = Project::factory()->running()->create();
    $service = new ProjectContainerService;

    $logs = $service->getLogs($project, 10);

    expect($logs)->toHaveCount(3);
});
