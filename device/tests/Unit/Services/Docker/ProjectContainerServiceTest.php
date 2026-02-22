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

    expect($result)->toBeNull();
    expect($project->fresh()->status)->toBe(ProjectStatus::Running);
});

it('stops a project container', function () {
    Process::fake([
        'docker compose down' => Process::result(output: 'Stopped'),
    ]);

    $project = Project::factory()->running()->create();
    $service = new ProjectContainerService;

    $result = $service->stop($project);

    expect($result)->toBeNull();
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

it('builds compose command with path translation when host path is set', function () {
    $service = new ProjectContainerService(
        hostProjectsPath: '/home/user/vibecodepc/device/storage/app/projects',
        containerProjectsPath: '/var/www/html/storage/app/projects',
    );

    $project = Project::factory()->create([
        'path' => '/var/www/html/storage/app/projects/my-app',
    ]);

    $command = $service->composeCommand($project, 'up -d');

    expect($command)
        ->toContain('docker compose -f')
        ->toContain('/var/www/html/storage/app/projects/my-app/docker-compose.yml')
        ->toContain('--project-directory')
        ->toContain('/home/user/vibecodepc/device/storage/app/projects/my-app')
        ->toContain('up -d');
});

it('builds plain compose command when host path is not set', function () {
    $service = new ProjectContainerService;

    $project = Project::factory()->create([
        'path' => '/var/www/html/storage/app/projects/my-app',
    ]);

    $command = $service->composeCommand($project, 'up -d');

    expect($command)->toBe('docker compose up -d');
});

it('translates container path to host path', function () {
    $service = new ProjectContainerService(
        hostProjectsPath: '/home/user/repo/device/storage/app/projects',
        containerProjectsPath: '/var/www/html/storage/app/projects',
    );

    $result = $service->translateProjectPath('/var/www/html/storage/app/projects/my-app');

    expect($result)->toBe('/home/user/repo/device/storage/app/projects/my-app');
});

it('translates nested project paths correctly', function () {
    $service = new ProjectContainerService(
        hostProjectsPath: '/home/user/repo/device/storage/app/projects',
        containerProjectsPath: '/var/www/html/storage/app/projects',
    );

    $result = $service->translateProjectPath('/var/www/html/storage/app/projects/org/deep-app');

    expect($result)->toBe('/home/user/repo/device/storage/app/projects/org/deep-app');
});

it('returns null for path translation when not configured', function () {
    $service = new ProjectContainerService;

    $result = $service->translateProjectPath('/var/www/html/storage/app/projects/my-app');

    expect($result)->toBeNull();
});

it('returns null for path translation when path does not match container base', function () {
    $service = new ProjectContainerService(
        hostProjectsPath: '/home/user/repo/device/storage/app/projects',
        containerProjectsPath: '/var/www/html/storage/app/projects',
    );

    $result = $service->translateProjectPath('/some/other/path/my-app');

    expect($result)->toBeNull();
});
