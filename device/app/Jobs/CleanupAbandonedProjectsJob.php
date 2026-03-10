<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Project;
use App\Models\ProjectLog;
use App\Services\Docker\ProjectContainerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use VibecodePC\Common\Enums\ProjectStatus;

class CleanupAbandonedProjectsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public int $daysThreshold = 30,
    ) {}

    public function handle(ProjectContainerService $containerService): void
    {
        $cutoffDate = now()->subDays($this->daysThreshold);

        $projectsToCleanup = Project::query()
            ->where(function ($query) use ($cutoffDate) {
                $query->where('status', ProjectStatus::Error)
                    ->orWhere(function ($q) use ($cutoffDate) {
                        $q->where('status', ProjectStatus::Created)
                            ->whereNull('last_started_at')
                            ->where('created_at', '<', $cutoffDate);
                    })
                    ->orWhere(function ($q) use ($cutoffDate) {
                        $q->where('status', ProjectStatus::Stopped)
                            ->where(function ($q2) use ($cutoffDate) {
                                $q2->whereNotNull('last_stopped_at')
                                    ->where('last_stopped_at', '<', $cutoffDate);
                            });
                    });
            })
            ->get();

        if ($projectsToCleanup->isEmpty()) {
            Log::info('No abandoned or errored projects found for cleanup');

            return;
        }

        Log::info("Found {$projectsToCleanup->count()} projects to cleanup", [
            'count' => $projectsToCleanup->count(),
            'threshold_days' => $this->daysThreshold,
        ]);

        foreach ($projectsToCleanup as $project) {
            $this->cleanupProject($project, $containerService);
        }
    }

    private function cleanupProject(Project $project, ProjectContainerService $containerService): void
    {
        $reason = $this->getCleanupReason($project);

        Log::info('Cleaning up project', [
            'project_id' => $project->id,
            'project_name' => $project->name,
            'reason' => $reason,
        ]);

        try {
            if ($containerService->isRunning($project)) {
                $error = $containerService->stop($project);
                if ($error !== null) {
                    Log::warning('Failed to stop container during cleanup', [
                        'project_id' => $project->id,
                        'error' => $error,
                    ]);
                }
            }

            if ($project->container_id) {
                $containerService->remove($project);
            }

            if (File::exists($project->path)) {
                File::deleteDirectory($project->path);
            }

            ProjectLog::create([
                'project_id' => $project->id,
                'type' => 'cleanup',
                'message' => "Project automatically cleaned up. Reason: {$reason}",
            ]);

            $project->delete();

            Log::info('Project cleanup completed successfully', [
                'project_id' => $project->id,
                'project_name' => $project->name,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to cleanup project', [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            ProjectLog::create([
                'project_id' => $project->id,
                'type' => 'error',
                'message' => "Cleanup failed: {$e->getMessage()}",
            ]);
        }
    }

    private function getCleanupReason(Project $project): string
    {
        if ($project->status === ProjectStatus::Error) {
            return 'Error status';
        }

        if ($project->status === ProjectStatus::Created && $project->last_started_at === null) {
            return 'Abandoned during creation';
        }

        if ($project->status === ProjectStatus::Stopped && $project->last_stopped_at !== null) {
            return 'Stopped for more than '.$this->daysThreshold.' days';
        }

        return 'Unknown';
    }
}
