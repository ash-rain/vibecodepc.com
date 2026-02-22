<?php

declare(strict_types=1);

namespace App\Services\Docker;

use App\Models\Project;
use App\Models\ProjectLog;
use Illuminate\Support\Facades\Process;
use VibecodePC\Common\Enums\ProjectStatus;

class ProjectContainerService
{
    public function __construct(
        private ?string $hostProjectsPath = null,
        private ?string $containerProjectsPath = null,
    ) {}

    /**
     * Start a project container.
     *
     * @return string|null null on success, error message on failure
     */
    public function start(Project $project): ?string
    {
        $result = Process::path($project->path)
            ->timeout(120)
            ->run($this->composeCommand($project, 'up -d'));

        if ($result->successful()) {
            $this->log($project, 'docker', "Start: {$result->output()}");

            $project->update([
                'status' => ProjectStatus::Running,
                'container_id' => $this->getContainerId($project),
                'last_started_at' => now(),
            ]);

            return null;
        }

        $error = trim($result->errorOutput() ?: $result->output());
        $this->log($project, 'docker', "Start failed: {$error}");

        $project->update(['status' => ProjectStatus::Error]);

        return $error ?: 'Failed to start container (no output).';
    }

    /**
     * Stop a project container.
     *
     * @return string|null null on success, error message on failure
     */
    public function stop(Project $project): ?string
    {
        $result = Process::path($project->path)
            ->timeout(60)
            ->run($this->composeCommand($project, 'down'));

        if ($result->successful()) {
            $this->log($project, 'docker', "Stop: {$result->output()}");

            $project->update([
                'status' => ProjectStatus::Stopped,
                'container_id' => null,
                'last_stopped_at' => now(),
            ]);

            return null;
        }

        $error = trim($result->errorOutput() ?: $result->output());
        $this->log($project, 'docker', "Stop failed: {$error}");

        return $error ?: 'Failed to stop container (no output).';
    }

    /**
     * Restart a project container.
     *
     * @return string|null null on success, error message on failure
     */
    public function restart(Project $project): ?string
    {
        $stopError = $this->stop($project);

        if ($stopError !== null) {
            return "Stop failed: {$stopError}";
        }

        return $this->start($project);
    }

    public function isRunning(Project $project): bool
    {
        $result = Process::path($project->path)
            ->run($this->composeCommand($project, 'ps --format json'));

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
            ->run($this->composeCommand($project, sprintf('logs --tail=%d --no-color', $lines)));

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

    /**
     * @return array{success: bool, output: string}
     */
    public function execCommand(Project $project, string $command): array
    {
        if (! $project->container_id) {
            return ['success' => false, 'output' => 'No running container found.'];
        }

        $command = trim($command);

        if ($command === '') {
            return ['success' => false, 'output' => 'Command cannot be empty.'];
        }

        $result = Process::path($project->path)
            ->timeout(30)
            ->run($this->composeCommand($project, sprintf('exec -T app %s', $command)));

        $output = trim($result->output() ?: $result->errorOutput());

        $this->log($project, 'docker', "Exec [{$command}]: {$output}");

        return [
            'success' => $result->successful(),
            'output' => $output,
        ];
    }

    public function remove(Project $project): bool
    {
        $result = Process::path($project->path)
            ->timeout(60)
            ->run($this->composeCommand($project, 'down -v --rmi local'));

        $this->log($project, 'docker', "Remove: {$result->output()}");

        return $result->successful();
    }

    /**
     * Build the `docker compose` command prefix with path translation when running
     * inside a dev container that talks to the host Docker daemon via socket mount.
     */
    public function composeCommand(Project $project, string $subcommand): string
    {
        $hostPath = $this->translateProjectPath($project->path);

        if ($hostPath !== null) {
            $composeFile = $project->path.'/docker-compose.yml';

            return sprintf(
                'docker compose -f %s --project-directory %s %s',
                escapeshellarg($composeFile),
                escapeshellarg($hostPath),
                $subcommand,
            );
        }

        return "docker compose {$subcommand}";
    }

    /**
     * Translate a container project path to the equivalent host path.
     *
     * Returns null when path translation is not active (production / no host path set).
     */
    public function translateProjectPath(string $containerPath): ?string
    {
        if ($this->hostProjectsPath === null || $this->containerProjectsPath === null) {
            return null;
        }

        if (! str_starts_with($containerPath, $this->containerProjectsPath)) {
            return null;
        }

        $relativePath = substr($containerPath, strlen($this->containerProjectsPath));

        return $this->hostProjectsPath.$relativePath;
    }

    private function getContainerId(Project $project): ?string
    {
        $result = Process::path($project->path)
            ->run($this->composeCommand($project, 'ps -q'));

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
