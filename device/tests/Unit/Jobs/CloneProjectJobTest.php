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

it('handles null project gracefully after deletion', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Cloning,
        'framework' => ProjectFramework::Custom,
    ]);

    $job = new CloneProjectJob($project, 'https://github.com/user/repo.git');

    // Delete project after job is created but before execution
    $project->delete();

    $mockService = Mockery::mock(ProjectCloneService::class);
    $mockService->shouldReceive('runClone')
        ->once()
        ->andThrow(new \RuntimeException('Project not found'));

    try {
        $job->handle($mockService);
    } catch (\RuntimeException $e) {
        $job->failed($e);
    }

    // Job should handle gracefully without crashing
    expect(true)->toBeTrue();
});

it('handles permission denied errors during clone', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Cloning,
        'framework' => ProjectFramework::Custom,
    ]);

    $mockService = Mockery::mock(ProjectCloneService::class);
    $mockService->shouldReceive('runClone')
        ->once()
        ->with($project, 'https://github.com/user/repo.git')
        ->andThrow(new \RuntimeException('Permission denied: cannot create directory'));

    $job = new CloneProjectJob($project, 'https://github.com/user/repo.git');

    try {
        $job->handle($mockService);
    } catch (\RuntimeException $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
    expect($project->logs()->where('type', 'error')->first()->message)
        ->toContain('Permission denied');
});

it('handles empty repository clone', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Cloning,
        'framework' => ProjectFramework::Custom,
    ]);

    $mockService = Mockery::mock(ProjectCloneService::class);
    $mockService->shouldReceive('runClone')
        ->once()
        ->andThrow(new \RuntimeException('warning: You appear to have cloned an empty repository'));

    $job = new CloneProjectJob($project, 'https://github.com/user/empty-repo.git');

    try {
        $job->handle($mockService);
    } catch (\RuntimeException $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
});

it('handles repository with invalid references', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Cloning,
        'framework' => ProjectFramework::Custom,
    ]);

    $mockService = Mockery::mock(ProjectCloneService::class);
    $mockService->shouldReceive('runClone')
        ->once()
        ->andThrow(new \RuntimeException('Server does not allow request for unadvertised object'));

    $job = new CloneProjectJob($project, 'https://github.com/user/broken-ref-repo.git');

    try {
        $job->handle($mockService);
    } catch (\RuntimeException $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
});

it('handles repository that requires different protocol', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Cloning,
        'framework' => ProjectFramework::Custom,
    ]);

    $mockService = Mockery::mock(ProjectCloneService::class);
    $mockService->shouldReceive('runClone')
        ->once()
        ->andThrow(new \RuntimeException('The unauthenticated git protocol on port 9418 is no longer supported'));

    $job = new CloneProjectJob($project, 'git://github.com/user/old-repo.git');

    try {
        $job->handle($mockService);
    } catch (\RuntimeException $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
    expect($project->logs()->where('type', 'error')->first()->message)
        ->toContain('unauthenticated git protocol');
});

it('handles partial clone with submodule failures', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Cloning,
        'framework' => ProjectFramework::Custom,
    ]);

    $mockService = Mockery::mock(ProjectCloneService::class);
    $mockService->shouldReceive('runClone')
        ->once()
        ->andThrow(new \RuntimeException('Clone succeeded but submodule update failed: repository not found'));

    $job = new CloneProjectJob($project, 'https://github.com/user/repo-with-submodules.git');

    try {
        $job->handle($mockService);
    } catch (\RuntimeException $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
    expect($project->logs()->where('type', 'error')->first()->message)
        ->toContain('submodule update failed');
});

it('preserves original exception message in error log', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Cloning,
        'framework' => ProjectFramework::Laravel,
    ]);

    $originalMessage = 'fatal: unable to access: Failed to connect to github.com port 443: Connection refused';
    $exception = new \RuntimeException($originalMessage);

    $job = new CloneProjectJob($project, 'https://github.com/user/repo.git');
    $job->failed($exception);

    $log = $project->logs()->where('type', 'error')->first();
    expect($log)->not->toBeNull();
    expect($log->message)->toContain($originalMessage);
});

it('can be serialized and deserialized', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Cloning,
        'framework' => ProjectFramework::Custom,
    ]);

    $job = new CloneProjectJob($project, 'https://github.com/user/repo.git');

    // Serialize the job
    $serialized = serialize($job);
    expect($serialized)->not->toBeNull();

    // Deserialize the job
    $unserialized = unserialize($serialized);
    expect($unserialized)->toBeInstanceOf(CloneProjectJob::class);
    expect($unserialized->cloneUrl)->toBe('https://github.com/user/repo.git');
});

it('handles rapid successive failure calls', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Cloning,
        'framework' => ProjectFramework::Custom,
    ]);

    $job = new CloneProjectJob($project, 'https://github.com/user/repo.git');

    $exception1 = new \RuntimeException('First failure');
    $exception2 = new \RuntimeException('Second failure');

    $job->failed($exception1);
    $job->failed($exception2);

    // Should have both error logs
    $logs = $project->logs()->where('type', 'error')->get();
    expect($logs)->toHaveCount(2);
    expect($logs->pluck('message'))->toContain('Cloning failed: First failure');
    expect($logs->pluck('message'))->toContain('Cloning failed: Second failure');
});

it('handles interrupted clone with incomplete files', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Cloning,
        'framework' => ProjectFramework::Custom,
    ]);

    $mockService = Mockery::mock(ProjectCloneService::class);
    $mockService->shouldReceive('runClone')
        ->once()
        ->andThrow(new \RuntimeException('error: RPC failed; curl 18 transfer closed with outstanding read data remaining'));

    $job = new CloneProjectJob($project, 'https://github.com/user/large-repo.git');

    try {
        $job->handle($mockService);
    } catch (\RuntimeException $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
    expect($project->logs()->where('type', 'error')->first()->message)
        ->toContain('RPC failed');
});

it('handles rate limit errors from git provider', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Cloning,
        'framework' => ProjectFramework::Custom,
    ]);

    $mockService = Mockery::mock(ProjectCloneService::class);
    $mockService->shouldReceive('runClone')
        ->once()
        ->andThrow(new \RuntimeException('error: 429 Too Many Requests - rate limit exceeded'));

    $job = new CloneProjectJob($project, 'https://github.com/user/repo.git');

    try {
        $job->handle($mockService);
    } catch (\RuntimeException $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
    expect($project->logs()->where('type', 'error')->first()->message)
        ->toContain('429');
});

it('handles repository not found at specific path', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Cloning,
        'framework' => ProjectFramework::Custom,
    ]);

    $mockService = Mockery::mock(ProjectCloneService::class);
    $mockService->shouldReceive('runClone')
        ->once()
        ->andThrow(new \RuntimeException('remote: Repository not found. fatal: repository not found'));

    $job = new CloneProjectJob($project, 'https://github.com/user/nonexistent-repo.git');

    try {
        $job->handle($mockService);
    } catch (\RuntimeException $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
});

it('handles DNS resolution failures', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Cloning,
        'framework' => ProjectFramework::Custom,
    ]);

    $mockService = Mockery::mock(ProjectCloneService::class);
    $mockService->shouldReceive('runClone')
        ->once()
        ->andThrow(new \RuntimeException('Could not resolve host: github.com'));

    $job = new CloneProjectJob($project, 'https://github.com/user/repo.git');

    try {
        $job->handle($mockService);
    } catch (\RuntimeException $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
    expect($project->logs()->where('type', 'error')->first()->message)
        ->toContain('Could not resolve host');
});
