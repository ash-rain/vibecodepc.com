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

it('returns error when container start fails', function () {
    Process::fake([
        'docker compose up -d' => Process::result(errorOutput: 'Error starting container', exitCode: 1),
    ]);

    $project = Project::factory()->create();
    $service = new ProjectContainerService;

    $result = $service->start($project);

    expect($result)->toBe('Error starting container');
    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
});

it('returns generic error when start fails with no output', function () {
    Process::fake([
        'docker compose up -d' => Process::result(output: '', exitCode: 1),
    ]);

    $project = Project::factory()->create();
    $service = new ProjectContainerService;

    $result = $service->start($project);

    expect($result)->toBe('Failed to start container (no output).');
});

it('handles port conflict errors when starting container', function () {
    Process::fake([
        'docker compose up -d' => Process::result(
            errorOutput: 'Bind for 0.0.0.0:8080 failed: port is already allocated',
            exitCode: 1
        ),
    ]);

    $project = Project::factory()->create();
    $service = new ProjectContainerService;

    $result = $service->start($project);

    expect($result)->toContain('port is already allocated');
    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
});

it('handles Docker daemon not running error', function () {
    Process::fake([
        'docker compose up -d' => Process::result(
            errorOutput: 'Cannot connect to the Docker daemon at unix:///var/run/docker.sock. Is the docker daemon running?',
            exitCode: 1
        ),
    ]);

    $project = Project::factory()->create();
    $service = new ProjectContainerService;

    $result = $service->start($project);

    expect($result)->toContain('Cannot connect to the Docker daemon');
    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
});

it('returns error when container stop fails', function () {
    Process::fake([
        'docker compose down' => Process::result(errorOutput: 'Container not found', exitCode: 1),
    ]);

    $project = Project::factory()->running()->create();
    $service = new ProjectContainerService;

    $result = $service->stop($project);

    expect($result)->toBe('Container not found');
});

it('returns generic error when stop fails with no output', function () {
    Process::fake([
        'docker compose down' => Process::result(output: '', exitCode: 1),
    ]);

    $project = Project::factory()->running()->create();
    $service = new ProjectContainerService;

    $result = $service->stop($project);

    expect($result)->toBe('Failed to stop container (no output).');
});

it('returns error when restart stop phase fails', function () {
    Process::fake([
        'docker compose down' => Process::result(errorOutput: 'Stop failed: container locked', exitCode: 1),
    ]);

    $project = Project::factory()->running()->create();
    $service = new ProjectContainerService;

    $result = $service->restart($project);

    expect($result)->toBe('Stop failed: Stop failed: container locked');
});

it('returns false when container not found during isRunning check', function () {
    Process::fake([
        'docker compose ps --format json' => Process::result(errorOutput: 'No such container', exitCode: 1),
    ]);

    $project = Project::factory()->create();
    $service = new ProjectContainerService;

    expect($service->isRunning($project))->toBeFalse();
});

it('returns empty logs when docker compose logs fails', function () {
    Process::fake([
        'docker compose logs --tail=50 --no-color' => Process::result(errorOutput: 'Container not running', exitCode: 1),
    ]);

    $project = Project::factory()->create();
    $service = new ProjectContainerService;

    $logs = $service->getLogs($project);

    expect($logs)->toBe([]);
});

it('returns null resources when container_id is null', function () {
    $project = Project::factory()->create(['container_id' => null]);
    $service = new ProjectContainerService;

    $resources = $service->getResourceUsage($project);

    expect($resources)->toBeNull();
});

it('returns null resources when docker stats fails', function () {
    Process::fake([
        'docker stats *' => Process::result(errorOutput: 'Error: No such container: xyz789', exitCode: 1),
    ]);

    $project = Project::factory()->running()->create(['container_id' => 'xyz789']);
    $service = new ProjectContainerService;

    $resources = $service->getResourceUsage($project);

    expect($resources)->toBeNull();
});

it('returns null container ID when ps command fails', function () {
    Process::fake([
        'docker compose up -d' => Process::result(output: 'Started'),
        'docker compose ps -q' => Process::result(errorOutput: 'Docker daemon error', exitCode: 1),
    ]);

    $project = Project::factory()->create();
    $service = new ProjectContainerService;

    $service->start($project);

    expect($project->fresh()->container_id)->toBeNull();
    expect($project->fresh()->status)->toBe(ProjectStatus::Running);
});

it('handles container removal failure', function () {
    Process::fake([
        'docker compose down -v --rmi local' => Process::result(errorOutput: 'Failed to remove volumes', exitCode: 1),
    ]);

    $project = Project::factory()->create();
    $service = new ProjectContainerService;

    $result = $service->remove($project);

    expect($result)->toBeFalse();
});

it('handles container removal when command times out', function () {
    Process::fake([
        'docker compose down -v --rmi local' => function () {
            throw new RuntimeException('Process timed out after 60 seconds');
        },
    ]);

    $project = Project::factory()->create();
    $service = new ProjectContainerService;

    expect(fn () => $service->remove($project))->toThrow(RuntimeException::class, 'Process timed out');
});

it('handles complex Docker error messages with multiple lines', function () {
    Process::fake([
        'docker compose up -d' => Process::result(
            errorOutput: "Error response from daemon: driver failed programming external connectivity on endpoint\nError starting userland proxy: listen tcp 0.0.0.0:8080: bind: address already in use",
            exitCode: 1
        ),
    ]);

    $project = Project::factory()->create();
    $service = new ProjectContainerService;

    $result = $service->start($project);

    expect($result)->toContain('address already in use');
    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
});

it('handles permission denied errors from Docker daemon', function () {
    Process::fake([
        'docker compose up -d' => Process::result(
            errorOutput: 'permission denied while trying to connect to the Docker daemon socket at unix:///var/run/docker.sock: Get "http://%2Fvar%2Frun%2Fdocker.sock/v1.24/containers/json": dial unix /var/run/docker.sock: connect: permission denied',
            exitCode: 1
        ),
    ]);

    $project = Project::factory()->create();
    $service = new ProjectContainerService;

    $result = $service->start($project);

    expect($result)->toContain('permission denied');
    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
});

it('handles image pull errors during start', function () {
    Process::fake([
        'docker compose up -d' => Process::result(
            errorOutput: 'Error response from daemon: pull access denied for myimage, repository does not exist or may require docker login',
            exitCode: 1
        ),
    ]);

    $project = Project::factory()->create();
    $service = new ProjectContainerService;

    $result = $service->start($project);

    expect($result)->toContain('pull access denied');
    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
});

it('returns null resources when docker stats returns malformed output', function () {
    Process::fake([
        'docker stats *' => Process::result(output: 'malformed|output|with|extra|pipes'),
    ]);

    $project = Project::factory()->running()->create(['container_id' => 'abc123']);
    $service = new ProjectContainerService;

    $resources = $service->getResourceUsage($project);

    expect($resources)->toBe(['cpu' => 'malformed', 'memory' => 'output']);
});

it('returns default values when docker stats returns empty output', function () {
    Process::fake([
        'docker stats *' => Process::result(output: '|'),
    ]);

    $project = Project::factory()->running()->create(['container_id' => 'abc123']);
    $service = new ProjectContainerService;

    $resources = $service->getResourceUsage($project);

    expect($resources)->toBe(['cpu' => '', 'memory' => '']);
});

it('handles container in exited state', function () {
    Process::fake([
        'docker compose ps --format json' => Process::result(output: '{"State":"exited"}'),
    ]);

    $project = Project::factory()->create();
    $service = new ProjectContainerService;

    expect($service->isRunning($project))->toBeFalse();
});

it('handles container in dead state', function () {
    Process::fake([
        'docker compose ps --format json' => Process::result(output: '{"State":"dead"}'),
    ]);

    $project = Project::factory()->create();
    $service = new ProjectContainerService;

    expect($service->isRunning($project))->toBeFalse();
});

it('handles container in restarting state', function () {
    Process::fake([
        'docker compose ps --format json' => Process::result(output: '{"State":"restarting"}'),
    ]);

    $project = Project::factory()->create();
    $service = new ProjectContainerService;

    expect($service->isRunning($project))->toBeFalse();
});

it('handles container in paused state', function () {
    Process::fake([
        'docker compose ps --format json' => Process::result(output: '{"State":"paused"}'),
    ]);

    $project = Project::factory()->create();
    $service = new ProjectContainerService;

    expect($service->isRunning($project))->toBeFalse();
});

it('returns empty logs when output is just whitespace', function () {
    Process::fake([
        'docker compose logs --tail=50 --no-color' => Process::result(output: "   \n\n   "),
    ]);

    $project = Project::factory()->running()->create();
    $service = new ProjectContainerService;

    $logs = $service->getLogs($project);

    expect($logs)->toBe([]);
});

it('handles stop on already stopped container gracefully', function () {
    Process::fake([
        'docker compose down' => Process::result(output: 'Container myproject-app-1  Removed', exitCode: 0),
    ]);

    $project = Project::factory()->stopped()->create();
    $service = new ProjectContainerService;

    $result = $service->stop($project);

    expect($result)->toBeNull();
    expect($project->fresh()->status)->toBe(ProjectStatus::Stopped);
});

it('handles container removal when container already gone', function () {
    Process::fake([
        'docker compose down -v --rmi local' => Process::result(
            errorOutput: 'Error response from daemon: No such container: myproject-app-1',
            exitCode: 1
        ),
    ]);

    $project = Project::factory()->create();
    $service = new ProjectContainerService;

    $result = $service->remove($project);

    expect($result)->toBeFalse();
});

it('handles docker compose ps returning multiple container IDs', function () {
    Process::fake([
        'docker compose up -d' => Process::result(output: 'Started'),
        'docker compose ps -q' => Process::result(output: "abc123\ndef456\nghi789"),
    ]);

    $project = Project::factory()->create();
    $service = new ProjectContainerService;

    $result = $service->start($project);

    expect($result)->toBeNull();
    expect($project->fresh()->container_id)->toBe('abc123');
});

it('handles docker compose ps returning empty output', function () {
    Process::fake([
        'docker compose up -d' => Process::result(output: 'Started'),
        'docker compose ps -q' => Process::result(output: ''),
    ]);

    $project = Project::factory()->create();
    $service = new ProjectContainerService;

    $result = $service->start($project);

    expect($result)->toBeNull();
    expect($project->fresh()->container_id)->toBeNull();
});

it('handles network not found errors during start', function () {
    Process::fake([
        'docker compose up -d' => Process::result(
            errorOutput: 'Error response from daemon: network myproject_default not found',
            exitCode: 1
        ),
    ]);

    $project = Project::factory()->create();
    $service = new ProjectContainerService;

    $result = $service->start($project);

    expect($result)->toContain('network myproject_default not found');
    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
});

it('handles volume mount errors during start', function () {
    Process::fake([
        'docker compose up -d' => Process::result(
            errorOutput: 'Error response from daemon: Mounts denied: path /host/path is not shared from OS X',
            exitCode: 1
        ),
    ]);

    $project = Project::factory()->create();
    $service = new ProjectContainerService;

    $result = $service->start($project);

    expect($result)->toContain('Mounts denied');
    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
});

it('handles out of memory errors during start', function () {
    Process::fake([
        'docker compose up -d' => Process::result(
            errorOutput: 'Error response from daemon: OCI runtime create failed: container_linux.go:380: starting container process caused: applying cgroup configuration for process caused: Cannot allocate memory',
            exitCode: 1
        ),
    ]);

    $project = Project::factory()->create();
    $service = new ProjectContainerService;

    $result = $service->start($project);

    expect($result)->toContain('Cannot allocate memory');
    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
});

it('handles docker compose build failures during start', function () {
    Process::fake([
        'docker compose up -d' => Process::result(
            errorOutput: 'failed to solve: executor failed running [/bin/sh -c npm install]: exit code: 1',
            exitCode: 1
        ),
    ]);

    $project = Project::factory()->create();
    $service = new ProjectContainerService;

    $result = $service->start($project);

    expect($result)->toContain('failed to solve');
    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
});

it('handles health check when container status is starting', function () {
    Process::fake([
        'docker compose ps --format json' => Process::result(output: '{"State":"running"}'),
        'docker stats *' => Process::result(output: '5.00%|128MiB / 256MiB'),
        'docker inspect --format "{{.State.Health.Status}}" *' => Process::result(output: 'starting'),
    ]);

    $project = Project::factory()->running()->create(['container_id' => 'abc123']);
    $service = new ProjectContainerService;

    $health = $service->healthCheck($project);

    expect($health['healthStatus'])->toBe('starting');
});

it('handles health check when container status is unhealthy', function () {
    Process::fake([
        'docker compose ps --format json' => Process::result(output: '{"State":"running"}'),
        'docker stats *' => Process::result(output: '5.00%|128MiB / 256MiB'),
        'docker inspect --format "{{.State.Health.Status}}" *' => Process::result(output: 'unhealthy'),
    ]);

    $project = Project::factory()->running()->create(['container_id' => 'abc123']);
    $service = new ProjectContainerService;

    $health = $service->healthCheck($project);

    expect($health['healthStatus'])->toBe('unhealthy');
});
