<?php

declare(strict_types=1);

namespace App\Services\Docker;

use App\Models\Project;
use App\Models\ProjectLog;
use Illuminate\Support\Facades\Process;
use VibecodePC\Common\Enums\ProjectStatus;

class ProjectContainerService
{
    public function start(Project $project): bool
    {
        $result = Process::path($project->path)
            ->timeout(120)
            ->run('docker compose up -d');

        $this->log($project, 'docker', "Start: {$result->output()}");

        if ($result->successful()) {
            $project->update([
                'status' => ProjectStatus::Running,
                'container_id' => $this->getContainerId($project),
                'last_started_at' => now(),
            ]);

            return true;
        }

        $project->update(['status' => ProjectStatus::Error]);

        return false;
    }

    public function stop(Project $project): bool
    {
        $result = Process::path($project->path)
            ->timeout(60)
            ->run('docker compose down');

        $this->log($project, 'docker', "Stop: {$result->output()}");

        if ($result->successful()) {
            $project->update([
                'status' => ProjectStatus::Stopped,
                'container_id' => null,
                'last_stopped_at' => now(),
            ]);

            return true;
        }

        return false;
    }

    public function restart(Project $project): bool
    {
        $this->stop($project);

        return $this->start($project);
    }

    public function isRunning(Project $project): bool
    {
        $result = Process::path($project->path)
            ->run('docker compose ps --format json');

        if (! $result->successful()) {
            return false;
        }

        return str_contains($result->output(), '"running"');
    }

    /**
     * @return array<int, string>
     */
    public function getLogs(Project $project, int $lines = 50): array
    {
        $result = Process::path($project->path)
            ->run(sprintf('docker compose logs --tail=%d --no-color', $lines));

        if (! $result->successful()) {
            return [];
        }

        return array_filter(explode("\n", trim($result->output())));
    }

    /**
     * @return array{cpu: string, memory: string}|null
     */
    public function getResourceUsage(Project $project): ?array
    {
        if (! $project->container_id) {
            return null;
        }

        $result = Process::run(
            sprintf('docker stats %s --no-stream --format "{{.CPUPerc}}|{{.MemUsage}}"', escapeshellarg($project->container_id)),
        );

        if (! $result->successful()) {
            return null;
        }

        $parts = explode('|', trim($result->output()));

        return [
            'cpu' => $parts[0] ?? '0%',
            'memory' => $parts[1] ?? '0B',
        ];
    }

    public function remove(Project $project): bool
    {
        $result = Process::path($project->path)
            ->timeout(60)
            ->run('docker compose down -v --rmi local');

        $this->log($project, 'docker', "Remove: {$result->output()}");

        return $result->successful();
    }

    private function getContainerId(Project $project): ?string
    {
        $result = Process::path($project->path)
            ->run('docker compose ps -q');

        if (! $result->successful()) {
            return null;
        }

        $ids = array_filter(explode("\n", trim($result->output())));

        return $ids[0] ?? null;
    }

    private function log(Project $project, string $type, string $message): void
    {
        ProjectLog::create([
            'project_id' => $project->id,
            'type' => $type,
            'message' => $message,
        ]);
    }
}
