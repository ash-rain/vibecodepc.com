<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Project;
use App\Models\ProjectLog;
use App\Services\Projects\ProjectScaffoldService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use VibecodePC\Common\Enums\ProjectFramework;
use VibecodePC\Common\Enums\ProjectStatus;

class ScaffoldProjectJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 540;

    public function __construct(
        public Project $project,
        public ProjectFramework $framework,
    ) {}

    public function handle(ProjectScaffoldService $scaffoldService): void
    {
        $scaffoldService->runScaffold($this->project, $this->framework);
    }

    public function failed(\Throwable $exception): void
    {
        $this->project->update(['status' => ProjectStatus::Error]);

        ProjectLog::create([
            'project_id' => $this->project->id,
            'type' => 'error',
            'message' => "Scaffolding failed: {$exception->getMessage()}",
        ]);
    }
}
