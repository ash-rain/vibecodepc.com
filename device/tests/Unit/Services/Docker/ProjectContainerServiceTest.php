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

it('performs health check on running container', function () {
    Process::fake([
        'docker compose ps --format json' => Process::result(output: '{"State":"running"}'),
        'docker stats *' => Process::result(output: '12.34%|256MiB / 512MiB'),
        'docker inspect --format "{{.State.Health.Status}}" *' => Process::result(output: 'healthy'),
    ]);

    $project = Project::factory()->running()->create([
        'container_id' => 'abc123',
        'last_started_at' => now()->subHour(),
    ]);
    $service = new ProjectContainerService;

    $health = $service->healthCheck($project);

    expect($health)
        ->status->toBe(ProjectStatus::Running->value)
        ->isRunning->toBeTrue()
        ->healthStatus->toBe('healthy')
        ->resources->toBe(['cpu' => '12.34%', 'memory' => '256MiB / 512MiB'])
        ->containerId->toBe('abc123')
        ->lastStartedAt->not->toBeNull()
        ->lastStoppedAt->toBeNull()
        ->error->toBeNull();
});

it('performs health check on stopped container', function () {
    Process::fake([
        'docker compose ps --format json' => Process::result(output: ''),
    ]);

    $project = Project::factory()->create([
        'status' => ProjectStatus::Stopped,
        'container_id' => null,
        'last_stopped_at' => now()->subMinutes(30),
    ]);
    $service = new ProjectContainerService;

    $health = $service->healthCheck($project);

    expect($health)
        ->status->toBe(ProjectStatus::Stopped->value)
        ->isRunning->toBeFalse()
        ->healthStatus->toBeNull()
        ->resources->toBeNull()
        ->containerId->toBeNull()
        ->lastStartedAt->toBeNull()
        ->lastStoppedAt->not->toBeNull()
        ->error->toBeNull();
});

it('returns null health status when container has no health check configured', function () {
    Process::fake([
        'docker compose ps --format json' => Process::result(output: '{"State":"running"}'),
        'docker stats *' => Process::result(output: '5.00%|128MiB / 256MiB'),
        'docker inspect --format "{{.State.Health.Status}}" *' => Process::result(output: ''),
    ]);

    $project = Project::factory()->running()->create(['container_id' => 'abc123']);
    $service = new ProjectContainerService;

    $health = $service->healthCheck($project);

    expect($health['healthStatus'])->toBeNull();
});

it('returns null health status when docker inspect fails', function () {
    Process::fake([
        'docker compose ps --format json' => Process::result(output: '{"State":"running"}'),
        'docker stats *' => Process::result(output: '5.00%|128MiB / 256MiB'),
        'docker inspect --format "{{.State.Health.Status}}" *' => Process::result(errorOutput: 'Error: No such container', exitCode: 1),
    ]);

    $project = Project::factory()->running()->create(['container_id' => 'abc123']);
    $service = new ProjectContainerService;

    $health = $service->healthCheck($project);

    expect($health['healthStatus'])->toBeNull();
});
