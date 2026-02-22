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
