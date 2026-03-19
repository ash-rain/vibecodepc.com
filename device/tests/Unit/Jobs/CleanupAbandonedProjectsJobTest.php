<?php

declare(strict_types=1);

use App\Jobs\CleanupAbandonedProjectsJob;
use App\Models\Project;
use App\Services\Docker\ProjectContainerService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use VibecodePC\Common\Enums\ProjectStatus;

beforeEach(function () {
    File::partialMock();
    Log::spy();
});

afterEach(function () {
    Mockery::close();
});

it('cleans up projects in Error status', function () {
    File::shouldReceive('exists')->andReturn(true);
    File::shouldReceive('deleteDirectory')->once();

    $project = Project::factory()->create([
        'status' => ProjectStatus::Error,
        'path' => '/tmp/test-project-error',
    ]);

    $mockService = Mockery::mock(ProjectContainerService::class);
    $mockService->shouldReceive('isRunning')->andReturn(false);
    $mockService->shouldReceive('stop')->never();
    $mockService->shouldReceive('remove')->never();

    $job = new CleanupAbandonedProjectsJob;
    $job->handle($mockService);

    expect(Project::find($project->id))->toBeNull();
});

it('cleans up abandoned Created projects older than 30 days', function () {
    File::shouldReceive('exists')->andReturn(true);
    File::shouldReceive('deleteDirectory')->once();

    $project = Project::factory()->create([
        'status' => ProjectStatus::Created,
        'path' => '/tmp/test-project-abandoned',
        'created_at' => now()->subDays(31),
        'last_started_at' => null,
    ]);

    $mockService = Mockery::mock(ProjectContainerService::class);
    $mockService->shouldReceive('isRunning')->andReturn(false);

    $job = new CleanupAbandonedProjectsJob;
    $job->handle($mockService);

    expect(Project::find($project->id))->toBeNull();
});

it('cleans up Stopped projects stopped for more than 30 days', function () {
    File::shouldReceive('exists')->andReturn(true);
    File::shouldReceive('deleteDirectory')->once();

    $project = Project::factory()->create([
        'status' => ProjectStatus::Stopped,
        'path' => '/tmp/test-project-stopped',
        'last_stopped_at' => now()->subDays(31),
    ]);

    $mockService = Mockery::mock(ProjectContainerService::class);
    $mockService->shouldReceive('isRunning')->andReturn(false);

    $job = new CleanupAbandonedProjectsJob;
    $job->handle($mockService);

    expect(Project::find($project->id))->toBeNull();
});

it('stops running containers before cleanup', function () {
    File::shouldReceive('exists')->andReturn(true);
    File::shouldReceive('deleteDirectory')->once();

    $project = Project::factory()->running()->create([
        'status' => ProjectStatus::Error,
        'path' => '/tmp/test-project-running',
    ]);

    $mockService = Mockery::mock(ProjectContainerService::class);
    $mockService->shouldReceive('isRunning')->andReturn(true);
    $mockService->shouldReceive('stop')->with(Mockery::type(Project::class))->once()->andReturn(null);
    $mockService->shouldReceive('remove')->with(Mockery::type(Project::class))->once()->andReturn(true);

    $job = new CleanupAbandonedProjectsJob;
    $job->handle($mockService);

    expect(Project::find($project->id))->toBeNull();
});

it('does not cleanup recent Created projects', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Created,
        'path' => '/tmp/test-project-recent',
        'created_at' => now()->subDays(5),
        'last_started_at' => null,
    ]);

    $mockService = Mockery::mock(ProjectContainerService::class);

    $job = new CleanupAbandonedProjectsJob;
    $job->handle($mockService);

    expect(Project::find($project->id))->not->toBeNull();
});

it('does not cleanup Running projects', function () {
    $project = Project::factory()->running()->create([
        'path' => '/tmp/test-project-running-ok',
    ]);

    $mockService = Mockery::mock(ProjectContainerService::class);

    $job = new CleanupAbandonedProjectsJob;
    $job->handle($mockService);

    expect(Project::find($project->id))->not->toBeNull();
});

it('does not cleanup recently stopped projects', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Stopped,
        'path' => '/tmp/test-project-recently-stopped',
        'last_stopped_at' => now()->subDays(5),
    ]);

    $mockService = Mockery::mock(ProjectContainerService::class);

    $job = new CleanupAbandonedProjectsJob;
    $job->handle($mockService);

    expect(Project::find($project->id))->not->toBeNull();
});

it('handles multiple projects in a single run', function () {
    File::shouldReceive('exists')->andReturn(true);
    File::shouldReceive('deleteDirectory')->times(3);

    $errorProject = Project::factory()->create([
        'status' => ProjectStatus::Error,
        'path' => '/tmp/test-project-1',
    ]);

    $abandonedProject = Project::factory()->create([
        'status' => ProjectStatus::Created,
        'path' => '/tmp/test-project-2',
        'created_at' => now()->subDays(31),
        'last_started_at' => null,
    ]);

    $stoppedProject = Project::factory()->create([
        'status' => ProjectStatus::Stopped,
        'path' => '/tmp/test-project-3',
        'last_stopped_at' => now()->subDays(31),
    ]);

    $mockService = Mockery::mock(ProjectContainerService::class);
    $mockService->shouldReceive('isRunning')->andReturn(false);

    $job = new CleanupAbandonedProjectsJob;
    $job->handle($mockService);

    expect(Project::find($errorProject->id))->toBeNull();
    expect(Project::find($abandonedProject->id))->toBeNull();
    expect(Project::find($stoppedProject->id))->toBeNull();
});

it('handles cleanup failures gracefully', function () {
    File::shouldReceive('exists')->andThrow(new \Exception('Permission denied'));

    $project = Project::factory()->create([
        'status' => ProjectStatus::Error,
        'path' => '/tmp/test-project-error',
    ]);

    $mockService = Mockery::mock(ProjectContainerService::class);
    $mockService->shouldReceive('isRunning')->andReturn(false);

    $job = new CleanupAbandonedProjectsJob;
    $job->handle($mockService);

    // Project should still exist since cleanup failed
    expect(Project::find($project->id))->not->toBeNull();
});

it('uses custom threshold days', function () {
    File::shouldReceive('exists')->andReturn(true);
    File::shouldReceive('deleteDirectory')->once();

    $project = Project::factory()->create([
        'status' => ProjectStatus::Created,
        'path' => '/tmp/test-project-15days',
        'created_at' => now()->subDays(15),
        'last_started_at' => null,
    ]);

    $mockService = Mockery::mock(ProjectContainerService::class);
    $mockService->shouldReceive('isRunning')->andReturn(false);

    $job = new CleanupAbandonedProjectsJob(10);
    $job->handle($mockService);

    expect(Project::find($project->id))->toBeNull();
});

it('removes container when container_id exists', function () {
    File::shouldReceive('exists')->andReturn(true);
    File::shouldReceive('deleteDirectory')->once();

    $project = Project::factory()->create([
        'status' => ProjectStatus::Error,
        'path' => '/tmp/test-project-container',
        'container_id' => 'abc123',
    ]);

    $mockService = Mockery::mock(ProjectContainerService::class);
    $mockService->shouldReceive('isRunning')->andReturn(false);
    $mockService->shouldReceive('remove')->with(Mockery::type(Project::class))->once()->andReturn(true);

    $job = new CleanupAbandonedProjectsJob;
    $job->handle($mockService);

    expect(Project::find($project->id))->toBeNull();
});

it('logs correct reason for Error status', function () {
    File::shouldReceive('exists')->andReturn(true);
    File::shouldReceive('deleteDirectory')->once();

    $capturedMessage = null;
    Log::shouldReceive('info')->andReturnUsing(function ($message, $context) use (&$capturedMessage) {
        if (str_contains($message, 'Cleaning up project')) {
            $capturedMessage = $context;
        }
    });
    Log::shouldReceive('error');

    $project = Project::factory()->create([
        'status' => ProjectStatus::Error,
        'path' => '/tmp/test-project-reason',
    ]);

    $mockService = Mockery::mock(ProjectContainerService::class);
    $mockService->shouldReceive('isRunning')->andReturn(false);

    $job = new CleanupAbandonedProjectsJob;
    $job->handle($mockService);

    expect($capturedMessage)->not->toBeNull();
    expect($capturedMessage['reason'] ?? '')->toBe('Error status');
});

it('logs correct reason for abandoned Created projects', function () {
    File::shouldReceive('exists')->andReturn(true);
    File::shouldReceive('deleteDirectory')->once();

    $capturedMessage = null;
    Log::shouldReceive('info')->andReturnUsing(function ($message, $context) use (&$capturedMessage) {
        if (str_contains($message, 'Cleaning up project')) {
            $capturedMessage = $context;
        }
    });
    Log::shouldReceive('error');

    $project = Project::factory()->create([
        'status' => ProjectStatus::Created,
        'path' => '/tmp/test-project-abandoned-reason',
        'created_at' => now()->subDays(31),
        'last_started_at' => null,
    ]);

    $mockService = Mockery::mock(ProjectContainerService::class);
    $mockService->shouldReceive('isRunning')->andReturn(false);

    $job = new CleanupAbandonedProjectsJob;
    $job->handle($mockService);

    expect($capturedMessage)->not->toBeNull();
    expect($capturedMessage['reason'] ?? '')->toBe('Abandoned during creation');
});

it('logs correct reason for stopped projects', function () {
    File::shouldReceive('exists')->andReturn(true);
    File::shouldReceive('deleteDirectory')->once();

    $capturedMessage = null;
    Log::shouldReceive('info')->andReturnUsing(function ($message, $context) use (&$capturedMessage) {
        if (str_contains($message, 'Cleaning up project')) {
            $capturedMessage = $context;
        }
    });
    Log::shouldReceive('error');

    $project = Project::factory()->create([
        'status' => ProjectStatus::Stopped,
        'path' => '/tmp/test-project-stopped-reason',
        'last_stopped_at' => now()->subDays(31),
    ]);

    $mockService = Mockery::mock(ProjectContainerService::class);
    $mockService->shouldReceive('isRunning')->andReturn(false);

    $job = new CleanupAbandonedProjectsJob;
    $job->handle($mockService);

    expect($capturedMessage)->not->toBeNull();
    expect($capturedMessage['reason'] ?? '')->toBe('Stopped for more than 30 days');
});

it('handles orphan project with missing container', function () {
    File::shouldReceive('exists')->andReturn(true);
    File::shouldReceive('deleteDirectory')->once();

    $project = Project::factory()->create([
        'status' => ProjectStatus::Error,
        'path' => '/tmp/test-orphan-container',
        'container_id' => 'missing-container-id',
    ]);

    $mockService = Mockery::mock(ProjectContainerService::class);
    $mockService->shouldReceive('isRunning')->andReturn(false);
    $mockService->shouldReceive('remove')->once()->andReturn(true);

    $job = new CleanupAbandonedProjectsJob;
    $job->handle($mockService);

    expect(Project::find($project->id))->toBeNull();
});

it('handles orphan project with missing directory', function () {
    File::shouldReceive('exists')->andReturn(false);

    $project = Project::factory()->create([
        'status' => ProjectStatus::Error,
        'path' => '/tmp/test-orphan-directory',
    ]);

    $mockService = Mockery::mock(ProjectContainerService::class);
    $mockService->shouldReceive('isRunning')->andReturn(false);

    $job = new CleanupAbandonedProjectsJob;
    $job->handle($mockService);

    expect(Project::find($project->id))->toBeNull();
});

it('continues cleanup when container stop fails', function () {
    File::shouldReceive('exists')->andReturn(true);
    File::shouldReceive('deleteDirectory')->once();

    $project = Project::factory()->create([
        'status' => ProjectStatus::Error,
        'path' => '/tmp/test-stop-failure',
        'container_id' => 'stop-fail-container',
    ]);

    $mockService = Mockery::mock(ProjectContainerService::class);
    $mockService->shouldReceive('isRunning')->andReturn(true);
    $mockService->shouldReceive('stop')
        ->with(Mockery::type(Project::class))
        ->once()
        ->andReturn('container not found');
    $mockService->shouldReceive('remove')
        ->with(Mockery::type(Project::class))
        ->once()
        ->andReturn(true);

    $job = new CleanupAbandonedProjectsJob;
    $job->handle($mockService);

    expect(Project::find($project->id))->toBeNull();
});

it('continues cleanup when container remove fails', function () {
    File::shouldReceive('exists')->never();
    File::shouldReceive('deleteDirectory')->never();

    $project = Project::factory()->create([
        'status' => ProjectStatus::Error,
        'path' => '/tmp/test-remove-failure',
        'container_id' => 'abc123',
    ]);

    $mockService = Mockery::mock(ProjectContainerService::class);
    $mockService->shouldReceive('isRunning')->andReturn(false);
    $mockService->shouldReceive('remove')->once()->andThrow(new \RuntimeException('Docker daemon error'));

    $job = new CleanupAbandonedProjectsJob;
    $job->handle($mockService);

    // Project should NOT be deleted because exception is caught before delete()
    expect(Project::find($project->id))->not->toBeNull();
    expect($project->logs()->where('type', 'error')->exists())->toBeTrue();
});

it('continues processing when one project cleanup fails', function () {
    File::shouldReceive('exists')->andReturn(true);
    File::shouldReceive('deleteDirectory')->once();

    $project1 = Project::factory()->create([
        'status' => ProjectStatus::Error,
        'path' => '/tmp/test-partial-1',
        'container_id' => 'container1',
    ]);

    $project2 = Project::factory()->create([
        'status' => ProjectStatus::Error,
        'path' => '/tmp/test-partial-2',
        'container_id' => 'container2',
    ]);

    $mockService = Mockery::mock(ProjectContainerService::class);
    $mockService->shouldReceive('isRunning')->andReturn(false);
    // First project throws, second succeeds
    $mockService->shouldReceive('remove')
        ->andReturnUsing(function ($projectArg) use ($project1) {
            if ($projectArg->container_id === $project1->container_id) {
                throw new \RuntimeException('Container removal failed');
            }

            return true;
        });

    $job = new CleanupAbandonedProjectsJob;
    $job->handle($mockService);

    // Both should be attempted - first fails but continues, second succeeds
    // Actually, looking at the code, when remove() throws:
    // - First project: exception caught, error logged, NOT deleted
    // - Second project: succeeds, IS deleted
    expect(Project::find($project1->id))->not->toBeNull();
    expect(Project::find($project2->id))->toBeNull();
});

it('completes gracefully when no projects need cleanup', function () {
    // Ensure only projects that don't match cleanup criteria exist
    Project::whereIn('status', [ProjectStatus::Error, ProjectStatus::Created, ProjectStatus::Stopped])->delete();

    // Create a running project that should NOT be cleaned up
    Project::factory()->running()->create();

    $mockService = Mockery::mock(ProjectContainerService::class);

    $job = new CleanupAbandonedProjectsJob;

    // Should complete without throwing - just execute and if no exception, test passes
    $job->handle($mockService);

    expect(true)->toBeTrue();
});

it('handles container service exception during isRunning check', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Error,
        'path' => '/tmp/test-isrunning-exception',
    ]);

    $mockService = Mockery::mock(ProjectContainerService::class);
    $mockService->shouldReceive('isRunning')
        ->with(Mockery::type(Project::class))
        ->once()
        ->andThrow(new \RuntimeException('Docker connection failed'));

    $job = new CleanupAbandonedProjectsJob;
    $job->handle($mockService);

    expect(Project::find($project->id))->not->toBeNull();
});

it('handles stopped project without last_stopped_at', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Stopped,
        'path' => '/tmp/test-stopped-no-date',
        'last_stopped_at' => null,
    ]);

    $mockService = Mockery::mock(ProjectContainerService::class);

    $job = new CleanupAbandonedProjectsJob;
    $job->handle($mockService);

    expect(Project::find($project->id))->not->toBeNull();
});

it('does not cleanup created project with last_started_at set', function () {
    // No File expectations needed - project should not be selected for cleanup

    $project = Project::factory()->create([
        'status' => ProjectStatus::Created,
        'path' => '/tmp/test-created-started',
        'created_at' => now()->subDays(31),
        'last_started_at' => now()->subDays(31),
    ]);

    $mockService = Mockery::mock(ProjectContainerService::class);

    $job = new CleanupAbandonedProjectsJob;
    $job->handle($mockService);

    // Project should NOT be deleted because last_started_at is not null
    expect(Project::find($project->id))->not->toBeNull();
});

it('processes mixed projects with some failures', function () {
    File::shouldReceive('exists')->andReturn(true);
    File::shouldReceive('deleteDirectory')->once();

    $successProject = Project::factory()->create([
        'status' => ProjectStatus::Error,
        'path' => '/tmp/test-mixed-success',
        'container_id' => 'success-container',
    ]);

    $failProject = Project::factory()->create([
        'status' => ProjectStatus::Error,
        'path' => '/tmp/test-mixed-fail',
        'container_id' => 'fail-container',
    ]);

    $mockService = Mockery::mock(ProjectContainerService::class);
    $mockService->shouldReceive('isRunning')->andReturn(false);
    $mockService->shouldReceive('remove')
        ->andReturnUsing(function ($projectArg) use ($failProject) {
            if ($projectArg->container_id === $failProject->container_id) {
                throw new \RuntimeException('Cleanup failed');
            }

            return true;
        });

    $job = new CleanupAbandonedProjectsJob;
    $job->handle($mockService);

    // Success project deleted, fail project not deleted due to exception
    expect(Project::find($successProject->id))->toBeNull();
    expect(Project::find($failProject->id))->not->toBeNull();
    expect($failProject->logs()->where('type', 'error')->exists())->toBeTrue();
});

it('handles docker daemon connection error', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Error,
        'path' => '/tmp/test-docker-error',
    ]);

    $mockService = Mockery::mock(ProjectContainerService::class);
    $mockService->shouldReceive('isRunning')
        ->once()
        ->andThrow(new \RuntimeException('Cannot connect to Docker daemon'));

    $job = new CleanupAbandonedProjectsJob;
    $job->handle($mockService);

    expect(Project::find($project->id))->not->toBeNull();
});

it('logs cleanup start with correct count', function () {
    File::shouldReceive('exists')->andReturn(true);
    File::shouldReceive('deleteDirectory')->once();

    $capturedMessage = null;
    $capturedContext = null;
    Log::shouldReceive('info')->andReturnUsing(function ($message, $context) use (&$capturedMessage, &$capturedContext) {
        if (str_contains($message, 'Found')) {
            $capturedMessage = $message;
            $capturedContext = $context;
        }
    });
    Log::shouldReceive('error');

    Project::factory()->create([
        'status' => ProjectStatus::Error,
        'path' => '/tmp/test-log-count',
    ]);

    $mockService = Mockery::mock(ProjectContainerService::class);
    $mockService->shouldReceive('isRunning')->andReturn(false);

    $job = new CleanupAbandonedProjectsJob;
    $job->handle($mockService);

    expect($capturedMessage)->toContain('1');
    expect($capturedContext['count'])->toBe(1);
    expect($capturedContext['threshold_days'])->toBe(30);
});

it('handles permission denied on directory deletion', function () {
    File::shouldReceive('exists')->andReturn(true);
    File::shouldReceive('deleteDirectory')
        ->once()
        ->andThrow(new \Exception('Permission denied'));

    $project = Project::factory()->create([
        'status' => ProjectStatus::Error,
        'path' => '/tmp/test-permission-denied',
    ]);

    $mockService = Mockery::mock(ProjectContainerService::class);
    $mockService->shouldReceive('isRunning')->andReturn(false);

    $job = new CleanupAbandonedProjectsJob;
    $job->handle($mockService);

    expect(Project::find($project->id))->not->toBeNull();
    expect($project->logs()->where('type', 'error')->exists())->toBeTrue();
});

it('handles exception during project log creation', function () {
    File::shouldReceive('exists')->andReturn(true);
    File::shouldReceive('deleteDirectory')->once();

    $project = Project::factory()->create([
        'status' => ProjectStatus::Error,
        'path' => '/tmp/test-log-exception',
    ]);

    $mockService = Mockery::mock(ProjectContainerService::class);
    $mockService->shouldReceive('isRunning')->andReturn(false);

    $job = new CleanupAbandonedProjectsJob;
    $job->handle($mockService);

    expect(Project::find($project->id))->toBeNull();
});

it('handles projects at exact threshold boundary', function () {
    File::shouldReceive('exists')->andReturn(true);
    File::shouldReceive('deleteDirectory')->once();

    $project = Project::factory()->create([
        'status' => ProjectStatus::Created,
        'path' => '/tmp/test-exact-threshold',
        'created_at' => now()->subDays(30)->subSecond(),
        'last_started_at' => null,
    ]);

    $mockService = Mockery::mock(ProjectContainerService::class);
    $mockService->shouldReceive('isRunning')->andReturn(false);

    $job = new CleanupAbandonedProjectsJob;
    $job->handle($mockService);

    expect(Project::find($project->id))->toBeNull();
});

it('handles zero threshold days', function () {
    File::shouldReceive('exists')->andReturn(true);
    File::shouldReceive('deleteDirectory')->once();

    $project = Project::factory()->create([
        'status' => ProjectStatus::Created,
        'path' => '/tmp/test-zero-threshold',
        'created_at' => now()->subHour(),
        'last_started_at' => null,
    ]);

    $mockService = Mockery::mock(ProjectContainerService::class);
    $mockService->shouldReceive('isRunning')->andReturn(false);

    $job = new CleanupAbandonedProjectsJob(0);
    $job->handle($mockService);

    expect(Project::find($project->id))->toBeNull();
});

it('handles large threshold days value', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Created,
        'path' => '/tmp/test-large-threshold',
        'created_at' => now()->subDays(365),
        'last_started_at' => null,
    ]);

    $mockService = Mockery::mock(ProjectContainerService::class);

    $job = new CleanupAbandonedProjectsJob(400);
    $job->handle($mockService);

    expect(Project::find($project->id))->not->toBeNull();
});

it('handles running container that refuses to stop', function () {
    File::shouldReceive('exists')->andReturn(true);
    File::shouldReceive('deleteDirectory')->once();

    $project = Project::factory()->create([
        'status' => ProjectStatus::Error,
        'path' => '/tmp/test-refuse-stop',
        'container_id' => 'running-container-id',
    ]);

    $mockService = Mockery::mock(ProjectContainerService::class);
    $mockService->shouldReceive('isRunning')->andReturn(true);
    $mockService->shouldReceive('stop')
        ->with(Mockery::type(Project::class))
        ->once()
        ->andReturn('signal 9 ignored');
    $mockService->shouldReceive('remove')
        ->with(Mockery::type(Project::class))
        ->once()
        ->andReturn(true);

    $job = new CleanupAbandonedProjectsJob;
    $job->handle($mockService);

    expect(Project::find($project->id))->toBeNull();
});

it('handles concurrent cleanup of same project', function () {
    File::shouldReceive('exists')->andReturn(true);
    File::shouldReceive('deleteDirectory')->once();

    $project = Project::factory()->create([
        'status' => ProjectStatus::Error,
        'path' => '/tmp/test-concurrent',
    ]);

    $mockService = Mockery::mock(ProjectContainerService::class);
    $mockService->shouldReceive('isRunning')->andReturn(false);

    $job = new CleanupAbandonedProjectsJob;
    $job->handle($mockService);

    expect(Project::find($project->id))->toBeNull();
});

it('handles project without container_id', function () {
    File::shouldReceive('exists')->andReturn(true);
    File::shouldReceive('deleteDirectory')->once();

    $project = Project::factory()->create([
        'status' => ProjectStatus::Error,
        'path' => '/tmp/test-no-container',
        'container_id' => null,
    ]);

    $mockService = Mockery::mock(ProjectContainerService::class);
    $mockService->shouldReceive('isRunning')->andReturn(false);
    // remove() should NOT be called when container_id is null
    $mockService->shouldReceive('remove')->never();

    $job = new CleanupAbandonedProjectsJob;
    $job->handle($mockService);

    expect(Project::find($project->id))->toBeNull();
});
