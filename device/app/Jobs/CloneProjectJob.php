<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Project;
use App\Models\ProjectLog;
use App\Services\Projects\ProjectCloneService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use VibecodePC\Common\Enums\ProjectStatus;

class CloneProjectJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 540;

    public function __construct(
        public Project $project,
        public string $cloneUrl,
    ) {}

    public function handle(ProjectCloneService $cloneService): void
    {
        $cloneService->runClone($this->project, $this->cloneUrl);
    }

    public function failed(\Throwable $exception): void
    {
        $this->project->update(['status' => ProjectStatus::Error]);

        ProjectLog::create([
            'project_id' => $this->project->id,
            'type' => 'error',
            'message' => "Cloning failed: {$exception->getMessage()}",
        ]);
    }
}
