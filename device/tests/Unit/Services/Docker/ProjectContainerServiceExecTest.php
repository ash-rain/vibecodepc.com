<?php

declare(strict_types=1);

use App\Models\Project;
use App\Services\Docker\ProjectContainerService;
use Illuminate\Support\Facades\Process;

it('runs a command in a running container', function () {
    Process::fake([
        '*docker compose exec*' => Process::result(output: 'hello world'),
    ]);

    $project = Project::factory()->running()->create();
    $service = new ProjectContainerService;

    $result = $service->execCommand($project, 'echo hello');

    expect($result)->toBe(['success' => true, 'output' => 'hello world']);
});

it('returns error when container is not running', function () {
    $project = Project::factory()->stopped()->create();
    $service = new ProjectContainerService;

    $result = $service->execCommand($project, 'ls');

    expect($result)->toBe(['success' => false, 'output' => 'No running container found.']);
});

it('returns error for empty command', function () {
    $project = Project::factory()->running()->create();
    $service = new ProjectContainerService;

    $result = $service->execCommand($project, '');

    expect($result)->toBe(['success' => false, 'output' => 'Command cannot be empty.']);
});

it('returns error for whitespace-only command', function () {
    $project = Project::factory()->running()->create();
    $service = new ProjectContainerService;

    $result = $service->execCommand($project, '   ');

    expect($result)->toBe(['success' => false, 'output' => 'Command cannot be empty.']);
});

it('captures error output on failure', function () {
    Process::fake([
        '*docker compose exec*' => Process::result(output: '', errorOutput: 'command not found', exitCode: 127),
    ]);

    $project = Project::factory()->running()->create();
    $service = new ProjectContainerService;

    $result = $service->execCommand($project, 'nonexistent');

    expect($result['success'])->toBeFalse();
    expect($result['output'])->toBe('command not found');
});
