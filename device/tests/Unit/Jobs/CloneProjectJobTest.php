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
