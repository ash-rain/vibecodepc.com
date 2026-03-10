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
