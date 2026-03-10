<?php

declare(strict_types=1);

use App\Jobs\CloneProjectJob;
use App\Models\Project;
use App\Services\Projects\ProjectCloneService;
use Illuminate\Support\Facades\Queue;
use VibecodePC\Common\Enums\ProjectFramework;
use VibecodePC\Common\Enums\ProjectStatus;

it('is dispatched when cloning a project', function () {
    Queue::fake();

    $cloneService = app(ProjectCloneService::class);
    $project = $cloneService->clone('test-clone', 'https://github.com/user/repo.git');

    expect($project->status)->toBe(ProjectStatus::Cloning);

    Queue::assertPushed(CloneProjectJob::class, function (CloneProjectJob $job) use ($project) {
        return $job->project->id === $project->id
            && $job->cloneUrl === 'https://github.com/user/repo.git';
    });
});

it('calls runClone on the service', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Cloning,
        'framework' => ProjectFramework::Custom,
    ]);

    $mockService = Mockery::mock(ProjectCloneService::class);
    $mockService->shouldReceive('runClone')
        ->once()
        ->with($project, 'https://github.com/user/repo.git');

    $job = new CloneProjectJob($project, 'https://github.com/user/repo.git');
    $job->handle($mockService);
});

it('sets status to Error on failure', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Cloning,
        'framework' => ProjectFramework::Custom,
    ]);

    $job = new CloneProjectJob($project, 'https://github.com/user/repo.git');
    $job->failed(new \RuntimeException('Git clone failed'));

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
    expect($project->logs()->where('type', 'error')->exists())->toBeTrue();
});

it('works independently of tunnel configuration', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Cloning,
        'framework' => ProjectFramework::Custom,
    ]);

    // No tunnel config exists - job should still work
    $mockService = Mockery::mock(ProjectCloneService::class);
    $mockService->shouldReceive('runClone')
        ->once()
        ->with($project, 'https://github.com/user/repo.git');

    $job = new CloneProjectJob($project, 'https://github.com/user/repo.git');
    $job->handle($mockService);
});

it('has correct retry configuration', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Cloning,
        'framework' => ProjectFramework::Custom,
    ]);

    $job = new CloneProjectJob($project, 'https://github.com/user/repo.git');

    expect($job->tries)->toBe(1);
    expect($job->timeout)->toBe(540);
});

it('handles clone failure from service exception', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Cloning,
        'framework' => ProjectFramework::Custom,
    ]);

    $mockService = Mockery::mock(ProjectCloneService::class);
    $mockService->shouldReceive('runClone')
        ->once()
        ->with($project, 'https://github.com/user/repo.git')
        ->andThrow(new \RuntimeException('Git clone failed: repository not found'));

    $job = new CloneProjectJob($project, 'https://github.com/user/repo.git');

    try {
        $job->handle($mockService);
    } catch (\RuntimeException $e) {
        // Exception should bubble up and trigger failed() method via Laravel's queue system
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
    expect($project->logs()->where('type', 'error')->exists())->toBeTrue();
    expect($project->logs()->where('type', 'error')->first()->message)
        ->toContain('Cloning failed: Git clone failed: repository not found');
});

it('handles network timeout during clone', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Cloning,
        'framework' => ProjectFramework::Custom,
    ]);

    $mockService = Mockery::mock(ProjectCloneService::class);
    $mockService->shouldReceive('runClone')
        ->once()
        ->andThrow(new \RuntimeException('Connection timed out after 120 seconds'));

    $job = new CloneProjectJob($project, 'https://github.com/user/repo.git');

    try {
        $job->handle($mockService);
    } catch (\RuntimeException $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
    expect($project->logs()->where('type', 'error')->exists())->toBeTrue();
    expect($project->logs()->where('type', 'error')->first()->message)
        ->toContain('Connection timed out');
});

it('handles authentication failure during clone', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Cloning,
        'framework' => ProjectFramework::Custom,
    ]);

    $mockService = Mockery::mock(ProjectCloneService::class);
    $mockService->shouldReceive('runClone')
        ->once()
        ->andThrow(new \RuntimeException('Authentication failed: invalid credentials'));

    $job = new CloneProjectJob($project, 'https://github.com/user/private-repo.git');

    try {
        $job->handle($mockService);
    } catch (\RuntimeException $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
    expect($project->logs()->where('type', 'error')->exists())->toBeTrue();
});

it('handles partial failure when project directory exists', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Cloning,
        'framework' => ProjectFramework::Custom,
    ]);

    $mockService = Mockery::mock(ProjectCloneService::class);
    $mockService->shouldReceive('runClone')
        ->once()
        ->andThrow(new \RuntimeException('fatal: destination path already exists and is not an empty directory'));

    $job = new CloneProjectJob($project, 'https://github.com/user/repo.git');

    try {
        $job->handle($mockService);
    } catch (\RuntimeException $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
    expect($project->logs()->where('type', 'error')->first()->message)
        ->toContain('destination path already exists');
});

it('handles disk full error during clone', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Cloning,
        'framework' => ProjectFramework::Custom,
    ]);

    $mockService = Mockery::mock(ProjectCloneService::class);
    $mockService->shouldReceive('runClone')
        ->once()
        ->andThrow(new \RuntimeException('fatal: write error: No space left on device'));

    $job = new CloneProjectJob($project, 'https://github.com/user/large-repo.git');

    try {
        $job->handle($mockService);
    } catch (\RuntimeException $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
    expect($project->logs()->where('type', 'error')->first()->message)
        ->toContain('No space left on device');
});

it('handles invalid repository URL format', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Cloning,
        'framework' => ProjectFramework::Custom,
    ]);

    $mockService = Mockery::mock(ProjectCloneService::class);
    $mockService->shouldReceive('runClone')
        ->once()
        ->andThrow(new \InvalidArgumentException('Invalid repository URL format'));

    $job = new CloneProjectJob($project, 'not-a-valid-url');

    try {
        $job->handle($mockService);
    } catch (\InvalidArgumentException $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
    expect($project->logs()->where('type', 'error')->exists())->toBeTrue();
});

it('handles generic exception during clone', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Cloning,
        'framework' => ProjectFramework::Custom,
    ]);

    $mockService = Mockery::mock(ProjectCloneService::class);
    $mockService->shouldReceive('runClone')
        ->once()
        ->andThrow(new \Exception('Unexpected system error'));

    $job = new CloneProjectJob($project, 'https://github.com/user/repo.git');

    try {
        $job->handle($mockService);
    } catch (\Exception $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
    expect($project->logs()->where('type', 'error')->first()->message)
        ->toContain('Unexpected system error');
});

it('creates error log with correct project association', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Cloning,
        'framework' => ProjectFramework::Laravel,
    ]);

    $exception = new \RuntimeException('Clone operation failed');

    $job = new CloneProjectJob($project, 'https://github.com/user/repo.git');
    $job->failed($exception);

    $log = $project->logs()->where('type', 'error')->first();
    expect($log)->not->toBeNull();
    expect($log->project_id)->toBe($project->id);
    expect($log->message)->toContain('Cloning failed: Clone operation failed');
});

it('handles concurrent clone attempts gracefully', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Cloning,
        'framework' => ProjectFramework::Custom,
    ]);

    $mockService = Mockery::mock(ProjectCloneService::class);
    $mockService->shouldReceive('runClone')
        ->once()
        ->andThrow(new \RuntimeException('Lock file exists, another clone in progress'));

    $job = new CloneProjectJob($project, 'https://github.com/user/repo.git');

    try {
        $job->handle($mockService);
    } catch (\RuntimeException $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
});

it('handles SSL certificate verification failure', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Cloning,
        'framework' => ProjectFramework::Custom,
    ]);

    $mockService = Mockery::mock(ProjectCloneService::class);
    $mockService->shouldReceive('runClone')
        ->once()
        ->andThrow(new \RuntimeException('SSL certificate problem: unable to get local issuer certificate'));

    $job = new CloneProjectJob($project, 'https://github.com/user/repo.git');

    try {
        $job->handle($mockService);
    } catch (\RuntimeException $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
    expect($project->logs()->where('type', 'error')->first()->message)
        ->toContain('SSL certificate problem');
});

it('handles large repository clone with timeout', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Cloning,
        'framework' => ProjectFramework::Custom,
    ]);

    $mockService = Mockery::mock(ProjectCloneService::class);
    $mockService->shouldReceive('runClone')
        ->once()
        ->andThrow(new \RuntimeException('Process exceeded timeout of 540 seconds'));

    $job = new CloneProjectJob($project, 'https://github.com/user/huge-repo.git');

    expect($job->timeout)->toBe(540);

    try {
        $job->handle($mockService);
    } catch (\RuntimeException $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
});
