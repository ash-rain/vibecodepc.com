<?php

declare(strict_types=1);

use App\Jobs\ScaffoldProjectJob;
use App\Models\Project;
use App\Services\Projects\ProjectScaffoldService;
use Illuminate\Support\Facades\Queue;
use VibecodePC\Common\Enums\ProjectFramework;
use VibecodePC\Common\Enums\ProjectStatus;

it('is dispatched when scaffolding a project', function () {
    Queue::fake();

    $scaffoldService = app(ProjectScaffoldService::class);
    $project = $scaffoldService->scaffold('test-scaffold', ProjectFramework::StaticHtml);

    expect($project->status)->toBe(ProjectStatus::Scaffolding);

    Queue::assertPushed(ScaffoldProjectJob::class, function (ScaffoldProjectJob $job) use ($project) {
        return $job->project->id === $project->id
            && $job->framework === ProjectFramework::StaticHtml;
    });
});

it('sets status to Created on success', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Scaffolding,
        'framework' => ProjectFramework::StaticHtml,
    ]);

    $mockService = Mockery::mock(ProjectScaffoldService::class);
    $mockService->shouldReceive('runScaffold')
        ->once()
        ->with($project, ProjectFramework::StaticHtml);

    $job = new ScaffoldProjectJob($project, ProjectFramework::StaticHtml);
    $job->handle($mockService);
});

it('sets status to Error on failure', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Scaffolding,
        'framework' => ProjectFramework::Laravel,
    ]);

    $job = new ScaffoldProjectJob($project, ProjectFramework::Laravel);
    $job->failed(new \RuntimeException('Something went wrong'));

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
    expect($project->logs()->where('type', 'error')->exists())->toBeTrue();
});

it('handles scaffolding failure from service exception', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Scaffolding,
        'framework' => ProjectFramework::Laravel,
    ]);

    $mockService = Mockery::mock(ProjectScaffoldService::class);
    $mockService->shouldReceive('runScaffold')
        ->once()
        ->with($project, ProjectFramework::Laravel)
        ->andThrow(new \RuntimeException('Composer create-project failed'));

    $job = new ScaffoldProjectJob($project, ProjectFramework::Laravel);

    try {
        $job->handle($mockService);
    } catch (\RuntimeException $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
    expect($project->logs()->where('type', 'error')->exists())->toBeTrue();
    expect($project->logs()->where('type', 'error')->first()->message)
        ->toContain('Scaffolding failed: Composer create-project failed');
});

it('handles network timeout during scaffolding', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Scaffolding,
        'framework' => ProjectFramework::NextJs,
    ]);

    $mockService = Mockery::mock(ProjectScaffoldService::class);
    $mockService->shouldReceive('runScaffold')
        ->once()
        ->andThrow(new \RuntimeException('npm install timed out after 300 seconds'));

    $job = new ScaffoldProjectJob($project, ProjectFramework::NextJs);

    try {
        $job->handle($mockService);
    } catch (\RuntimeException $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
    expect($project->logs()->where('type', 'error')->exists())->toBeTrue();
    expect($project->logs()->where('type', 'error')->first()->message)
        ->toContain('npm install timed out');
});

it('handles package download failure during scaffolding', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Scaffolding,
        'framework' => ProjectFramework::Astro,
    ]);

    $mockService = Mockery::mock(ProjectScaffoldService::class);
    $mockService->shouldReceive('runScaffold')
        ->once()
        ->andThrow(new \RuntimeException('npm ERR! code E404 package not found'));

    $job = new ScaffoldProjectJob($project, ProjectFramework::Astro);

    try {
        $job->handle($mockService);
    } catch (\RuntimeException $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
    expect($project->logs()->where('type', 'error')->first()->message)
        ->toContain('package not found');
});

it('handles permission denied errors during scaffolding', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Scaffolding,
        'framework' => ProjectFramework::Laravel,
    ]);

    $mockService = Mockery::mock(ProjectScaffoldService::class);
    $mockService->shouldReceive('runScaffold')
        ->once()
        ->andThrow(new \RuntimeException('Permission denied: cannot create directory'));

    $job = new ScaffoldProjectJob($project, ProjectFramework::Laravel);

    try {
        $job->handle($mockService);
    } catch (\RuntimeException $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
    expect($project->logs()->where('type', 'error')->first()->message)
        ->toContain('Permission denied');
});

it('handles disk full error during scaffolding', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Scaffolding,
        'framework' => ProjectFramework::Laravel,
    ]);

    $mockService = Mockery::mock(ProjectScaffoldService::class);
    $mockService->shouldReceive('runScaffold')
        ->once()
        ->andThrow(new \RuntimeException('No space left on device'));

    $job = new ScaffoldProjectJob($project, ProjectFramework::Laravel);

    try {
        $job->handle($mockService);
    } catch (\RuntimeException $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
    expect($project->logs()->where('type', 'error')->first()->message)
        ->toContain('No space left on device');
});

it('handles invalid framework gracefully', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Scaffolding,
        'framework' => ProjectFramework::Laravel,
    ]);

    $mockService = Mockery::mock(ProjectScaffoldService::class);
    $mockService->shouldReceive('runScaffold')
        ->once()
        ->andThrow(new \InvalidArgumentException('Unsupported framework type'));

    $job = new ScaffoldProjectJob($project, ProjectFramework::Laravel);

    try {
        $job->handle($mockService);
    } catch (\InvalidArgumentException $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
    expect($project->logs()->where('type', 'error')->exists())->toBeTrue();
});

it('handles template download failure', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Scaffolding,
        'framework' => ProjectFramework::Astro,
    ]);

    $mockService = Mockery::mock(ProjectScaffoldService::class);
    $mockService->shouldReceive('runScaffold')
        ->once()
        ->andThrow(new \RuntimeException('Failed to download template from registry'));

    $job = new ScaffoldProjectJob($project, ProjectFramework::Astro);

    try {
        $job->handle($mockService);
    } catch (\RuntimeException $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
    expect($project->logs()->where('type', 'error')->first()->message)
        ->toContain('Failed to download template');
});

it('handles corrupted template package', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Scaffolding,
        'framework' => ProjectFramework::NextJs,
    ]);

    $mockService = Mockery::mock(ProjectScaffoldService::class);
    $mockService->shouldReceive('runScaffold')
        ->once()
        ->andThrow(new \RuntimeException('Template package is corrupted: invalid checksum'));

    $job = new ScaffoldProjectJob($project, ProjectFramework::NextJs);

    try {
        $job->handle($mockService);
    } catch (\RuntimeException $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
    expect($project->logs()->where('type', 'error')->first()->message)
        ->toContain('corrupted');
});

it('handles missing template files after extraction', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Scaffolding,
        'framework' => ProjectFramework::Astro,
    ]);

    $mockService = Mockery::mock(ProjectScaffoldService::class);
    $mockService->shouldReceive('runScaffold')
        ->once()
        ->andThrow(new \RuntimeException('Template missing required files: package.json not found'));

    $job = new ScaffoldProjectJob($project, ProjectFramework::Astro);

    try {
        $job->handle($mockService);
    } catch (\RuntimeException $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
    expect($project->logs()->where('type', 'error')->first()->message)
        ->toContain('package.json not found');
});

it('handles template version mismatch', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Scaffolding,
        'framework' => ProjectFramework::Laravel,
    ]);

    $mockService = Mockery::mock(ProjectScaffoldService::class);
    $mockService->shouldReceive('runScaffold')
        ->once()
        ->andThrow(new \RuntimeException('Template requires PHP 8.4, system has PHP 8.2'));

    $job = new ScaffoldProjectJob($project, ProjectFramework::Laravel);

    try {
        $job->handle($mockService);
    } catch (\RuntimeException $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
    expect($project->logs()->where('type', 'error')->first()->message)
        ->toContain('Template requires');
});

it('performs cleanup when scaffolding fails after partial completion', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Scaffolding,
        'framework' => ProjectFramework::Laravel,
    ]);

    $mockService = Mockery::mock(ProjectScaffoldService::class);
    $mockService->shouldReceive('runScaffold')
        ->once()
        ->andThrow(new \RuntimeException('Scaffolding failed mid-process'));

    $job = new ScaffoldProjectJob($project, ProjectFramework::Laravel);

    try {
        $job->handle($mockService);
    } catch (\RuntimeException $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
    expect($project->logs()->where('type', 'error')->exists())->toBeTrue();
});

it('handles concurrent scaffolding attempts gracefully', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Scaffolding,
        'framework' => ProjectFramework::Laravel,
    ]);

    $mockService = Mockery::mock(ProjectScaffoldService::class);
    $mockService->shouldReceive('runScaffold')
        ->once()
        ->andThrow(new \RuntimeException('Project directory already locked for scaffolding'));

    $job = new ScaffoldProjectJob($project, ProjectFramework::Laravel);

    try {
        $job->handle($mockService);
    } catch (\RuntimeException $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
});

it('handles process termination during scaffolding', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Scaffolding,
        'framework' => ProjectFramework::NextJs,
    ]);

    $mockService = Mockery::mock(ProjectScaffoldService::class);
    $mockService->shouldReceive('runScaffold')
        ->once()
        ->andThrow(new \RuntimeException('Process killed: SIGTERM received'));

    $job = new ScaffoldProjectJob($project, ProjectFramework::NextJs);

    try {
        $job->handle($mockService);
    } catch (\RuntimeException $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
    expect($project->logs()->where('type', 'error')->first()->message)
        ->toContain('SIGTERM');
});

it('preserves original exception message in error log', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Scaffolding,
        'framework' => ProjectFramework::Laravel,
    ]);

    $originalMessage = 'Could not find package laravel/laravel with stability stable';
    $exception = new \RuntimeException($originalMessage);

    $job = new ScaffoldProjectJob($project, ProjectFramework::Laravel);
    $job->failed($exception);

    $log = $project->logs()->where('type', 'error')->first();
    expect($log)->not->toBeNull();
    expect($log->project_id)->toBe($project->id);
    expect($log->message)->toContain($originalMessage);
});

it('can be serialized and deserialized', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Scaffolding,
        'framework' => ProjectFramework::Laravel,
    ]);

    $job = new ScaffoldProjectJob($project, ProjectFramework::Laravel);

    $serialized = serialize($job);
    expect($serialized)->not->toBeNull();

    $unserialized = unserialize($serialized);
    expect($unserialized)->toBeInstanceOf(ScaffoldProjectJob::class);
    expect($unserialized->framework)->toBe(ProjectFramework::Laravel);
});

it('handles rapid successive failure calls', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Scaffolding,
        'framework' => ProjectFramework::Laravel,
    ]);

    $job = new ScaffoldProjectJob($project, ProjectFramework::Laravel);

    $exception1 = new \RuntimeException('First scaffolding failure');
    $exception2 = new \RuntimeException('Second scaffolding failure');

    $job->failed($exception1);
    $job->failed($exception2);

    $logs = $project->logs()->where('type', 'error')->get();
    expect($logs)->toHaveCount(2);
    expect($logs->pluck('message'))->toContain('Scaffolding failed: First scaffolding failure');
    expect($logs->pluck('message'))->toContain('Scaffolding failed: Second scaffolding failure');
});

it('handles generic exception during scaffolding', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Scaffolding,
        'framework' => ProjectFramework::FastApi,
    ]);

    $mockService = Mockery::mock(ProjectScaffoldService::class);
    $mockService->shouldReceive('runScaffold')
        ->once()
        ->andThrow(new \Exception('Unexpected system error during scaffolding'));

    $job = new ScaffoldProjectJob($project, ProjectFramework::FastApi);

    try {
        $job->handle($mockService);
    } catch (\Exception $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
    expect($project->logs()->where('type', 'error')->first()->message)
        ->toContain('Unexpected system error');
});

it('handles missing project directory during scaffolding', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Scaffolding,
        'framework' => ProjectFramework::StaticHtml,
    ]);

    $mockService = Mockery::mock(ProjectScaffoldService::class);
    $mockService->shouldReceive('runScaffold')
        ->once()
        ->andThrow(new \RuntimeException('Project directory does not exist'));

    $job = new ScaffoldProjectJob($project, ProjectFramework::StaticHtml);

    try {
        $job->handle($mockService);
    } catch (\RuntimeException $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
    expect($project->logs()->where('type', 'error')->first()->message)
        ->toContain('Project directory does not exist');
});

it('handles static html scaffolding failure', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Scaffolding,
        'framework' => ProjectFramework::StaticHtml,
    ]);

    $mockService = Mockery::mock(ProjectScaffoldService::class);
    $mockService->shouldReceive('runScaffold')
        ->once()
        ->andThrow(new \RuntimeException('Failed to write index.html'));

    $job = new ScaffoldProjectJob($project, ProjectFramework::StaticHtml);

    try {
        $job->handle($mockService);
    } catch (\RuntimeException $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
});

it('handles custom framework scaffolding failure', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Scaffolding,
        'framework' => ProjectFramework::Custom,
    ]);

    $mockService = Mockery::mock(ProjectScaffoldService::class);
    $mockService->shouldReceive('runScaffold')
        ->once()
        ->andThrow(new \RuntimeException('Custom scaffolding failed'));

    $job = new ScaffoldProjectJob($project, ProjectFramework::Custom);

    try {
        $job->handle($mockService);
    } catch (\RuntimeException $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
});

it('has correct retry configuration', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Scaffolding,
        'framework' => ProjectFramework::Laravel,
    ]);

    $job = new ScaffoldProjectJob($project, ProjectFramework::Laravel);

    expect($job->tries)->toBe(1);
    expect($job->timeout)->toBe(540);
});

it('works independently of tunnel configuration', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Scaffolding,
        'framework' => ProjectFramework::Laravel,
    ]);

    $mockService = Mockery::mock(ProjectScaffoldService::class);
    $mockService->shouldReceive('runScaffold')
        ->once()
        ->with($project, ProjectFramework::Laravel);

    $job = new ScaffoldProjectJob($project, ProjectFramework::Laravel);
    $job->handle($mockService);
});

it('handles FastApi scaffolding failure', function () {
    $project = Project::factory()->create([
        'status' => ProjectStatus::Scaffolding,
        'framework' => ProjectFramework::FastApi,
    ]);

    $mockService = Mockery::mock(ProjectScaffoldService::class);
    $mockService->shouldReceive('runScaffold')
        ->once()
        ->andThrow(new \RuntimeException('Failed to generate FastAPI files'));

    $job = new ScaffoldProjectJob($project, ProjectFramework::FastApi);

    try {
        $job->handle($mockService);
    } catch (\RuntimeException $e) {
        $job->failed($e);
    }

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
    expect($project->logs()->where('type', 'error')->first()->message)
        ->toContain('Failed to generate FastAPI files');
});
