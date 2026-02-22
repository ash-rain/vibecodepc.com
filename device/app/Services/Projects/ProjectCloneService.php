<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Jobs\CloneProjectJob;
use App\Models\Project;
use App\Models\ProjectLog;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use VibecodePC\Common\Enums\ProjectFramework;
use VibecodePC\Common\Enums\ProjectStatus;

class ProjectCloneService
{
    public function __construct(
        private readonly string $basePath,
        private readonly PortAllocatorService $portAllocator,
        private readonly ProjectScaffoldService $scaffoldService,
    ) {}

    public function clone(string $name, string $cloneUrl): Project
    {
        $slug = Str::slug($name);
        $path = "{$this->basePath}/{$slug}";
        $port = $this->portAllocator->allocate(ProjectFramework::Custom);

        // Strip token from clone URL before storing
        $sanitizedUrl = preg_replace('#://[^@]+@#', '://', $cloneUrl);

        $project = Project::create([
            'name' => $name,
            'slug' => $slug,
            'framework' => ProjectFramework::Custom,
            'status' => ProjectStatus::Cloning,
            'path' => $path,
            'port' => $port,
            'clone_url' => $sanitizedUrl,
        ]);

        $this->log($project, 'clone', 'Cloning repository...');

        CloneProjectJob::dispatch($project, $cloneUrl);

        return $project;
    }

    public function runClone(Project $project, string $cloneUrl): void
    {
        File::ensureDirectoryExists(dirname($project->path));

        $result = Process::timeout(120)->run(sprintf(
            'git clone %s %s',
            escapeshellarg($cloneUrl),
            escapeshellarg($project->path),
        ));

        if (! $result->successful()) {
            $project->update(['status' => ProjectStatus::Error]);
            $this->log($project, 'error', "Clone failed: {$result->errorOutput()}");

            return;
        }

        $framework = $this->detectFramework($project->path);
        $port = $this->portAllocator->allocate($framework);
        $project->update(['framework' => $framework, 'port' => $port]);

        $this->log($project, 'clone', "Cloned repository (detected: {$framework->label()}).");

        $this->installDependencies($project);
        $this->generateDockerCompose($project);
        $this->scaffoldService->injectAiConfigs($project);

        $project->update(['status' => ProjectStatus::Created]);
        $this->log($project, 'clone', 'Project cloned successfully.');
    }

    public function detectFramework(string $path): ProjectFramework
    {
        if ($this->hasComposerDependency($path, 'laravel/framework')) {
            return ProjectFramework::Laravel;
        }

        if ($this->hasPackageJsonDependency($path, 'next')) {
            return ProjectFramework::NextJs;
        }

        if ($this->hasPackageJsonDependency($path, 'astro')) {
            return ProjectFramework::Astro;
        }

        if ($this->hasRequirementsTxtDependency($path, 'fastapi')) {
            return ProjectFramework::FastApi;
        }

        if (File::exists("{$path}/index.html")) {
            return ProjectFramework::StaticHtml;
        }

        return ProjectFramework::Custom;
    }

    private function installDependencies(Project $project): void
    {
        $commands = match ($project->framework) {
            ProjectFramework::Laravel => $this->laravelInstallCommands($project),
            ProjectFramework::NextJs, ProjectFramework::Astro => $this->nodeInstallCommands($project),
            default => [],
        };

        foreach ($commands as $label => $command) {
            $result = Process::path($project->path)->timeout(300)->run($command);

            if ($result->successful()) {
                $this->log($project, 'clone', "{$label} completed.");
            } else {
                $this->log($project, 'warning', "{$label} failed: {$result->errorOutput()}");
            }
        }
    }

    /** @return array<string, string> */
    private function laravelInstallCommands(Project $project): array
    {
        $commands = [
            'composer install' => 'composer install --no-interaction --no-progress',
        ];

        if (! File::exists("{$project->path}/.env")) {
            $commands['env setup'] = 'cp .env.example .env 2>/dev/null; php artisan key:generate --no-interaction';
        }

        return $commands;
    }

    /** @return array<string, string> */
    private function nodeInstallCommands(Project $project): array
    {
        return [
            'npm install' => 'npm install',
        ];
    }

    private function generateDockerCompose(Project $project): void
    {
        $compose = match ($project->framework) {
            ProjectFramework::Laravel => $this->laravelCompose($project),
            ProjectFramework::NextJs => $this->nextJsCompose($project),
            ProjectFramework::Astro => $this->astroCompose($project),
            ProjectFramework::FastApi => $this->fastApiCompose($project),
            ProjectFramework::StaticHtml => $this->staticHtmlCompose($project),
            ProjectFramework::Custom => $this->customCompose($project),
        };

        File::put("{$project->path}/docker-compose.yml", $compose);
        $this->log($project, 'clone', 'Generated docker-compose.yml');
    }

    private function laravelCompose(Project $project): string
    {
        return <<<YAML
services:
  app:
    image: serversideup/php:8.4-fpm-nginx
    working_dir: /var/www/html
    volumes:
      - .:/var/www/html
    ports:
      - "{$project->port}:8080"
    environment:
      - APP_ENV=local
      - APP_DEBUG=true
      - AUTORUN_ENABLED=true
      - AUTORUN_LARAVEL_MIGRATION=true
YAML;
    }

    private function nextJsCompose(Project $project): string
    {
        return <<<YAML
services:
  app:
    image: node:22-slim
    working_dir: /app
    volumes:
      - .:/app
    ports:
      - "{$project->port}:3000"
    command: >
      sh -c "npm install && npm run dev"
YAML;
    }

    private function astroCompose(Project $project): string
    {
        return <<<YAML
services:
  app:
    image: node:22-slim
    working_dir: /app
    volumes:
      - .:/app
    ports:
      - "{$project->port}:4321"
    command: >
      sh -c "npm install && npm run dev -- --host"
YAML;
    }

    private function fastApiCompose(Project $project): string
    {
        return <<<YAML
services:
  app:
    image: python:3.12-slim
    working_dir: /app
    volumes:
      - .:/app
    ports:
      - "{$project->port}:8000"
    command: >
      sh -c "pip install -r requirements.txt && uvicorn main:app --host 0.0.0.0 --port 8000 --reload"
YAML;
    }

    private function staticHtmlCompose(Project $project): string
    {
        return <<<YAML
services:
  app:
    image: nginx:alpine
    volumes:
      - .:/usr/share/nginx/html:ro
    ports:
      - "{$project->port}:80"
YAML;
    }

    private function customCompose(Project $project): string
    {
        return <<<YAML
services:
  app:
    image: ubuntu:24.04
    working_dir: /app
    volumes:
      - .:/app
    ports:
      - "{$project->port}:8080"
    command: sleep infinity
YAML;
    }

    private function hasComposerDependency(string $path, string $package): bool
    {
        $composerPath = "{$path}/composer.json";

        if (! File::exists($composerPath)) {
            return false;
        }

        $composer = json_decode(File::get($composerPath), true);

        return isset($composer['require'][$package]);
    }

    private function hasPackageJsonDependency(string $path, string $package): bool
    {
        $packagePath = "{$path}/package.json";

        if (! File::exists($packagePath)) {
            return false;
        }

        $packageJson = json_decode(File::get($packagePath), true);

        return isset($packageJson['dependencies'][$package])
            || isset($packageJson['devDependencies'][$package]);
    }

    private function hasRequirementsTxtDependency(string $path, string $package): bool
    {
        $requirementsPath = "{$path}/requirements.txt";

        if (! File::exists($requirementsPath)) {
            return false;
        }

        $contents = strtolower(File::get($requirementsPath));

        return str_contains($contents, strtolower($package));
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
