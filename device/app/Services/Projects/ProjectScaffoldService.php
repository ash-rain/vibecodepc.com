<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Models\AiProviderConfig;
use App\Models\Project;
use App\Models\ProjectLog;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use VibecodePC\Common\Enums\ProjectFramework;
use VibecodePC\Common\Enums\ProjectStatus;

class ProjectScaffoldService
{
    public function __construct(
        private readonly string $basePath,
        private readonly PortAllocatorService $portAllocator,
    ) {}

    public function scaffold(string $name, ProjectFramework $framework): Project
    {
        $slug = Str::slug($name);
        $path = "{$this->basePath}/{$slug}";
        $port = $this->portAllocator->allocate($framework);

        File::ensureDirectoryExists($path);

        $project = Project::create([
            'name' => $name,
            'slug' => $slug,
            'framework' => $framework,
            'status' => ProjectStatus::Created,
            'path' => $path,
            'port' => $port,
        ]);

        $this->log($project, 'scaffold', "Scaffolding {$framework->label()} project...");

        $scaffolded = match ($framework) {
            ProjectFramework::Laravel => $this->scaffoldLaravel($project),
            ProjectFramework::NextJs => $this->scaffoldNextJs($project),
            ProjectFramework::Astro => $this->scaffoldAstro($project),
            ProjectFramework::FastApi => $this->scaffoldFastApi($project),
            ProjectFramework::StaticHtml => $this->scaffoldStaticHtml($project),
            ProjectFramework::Custom => $this->scaffoldCustom($project),
        };

        if ($scaffolded) {
            $this->generateDockerCompose($project);
            $this->injectAiConfigs($project);
            $this->log($project, 'scaffold', 'Project scaffolded successfully.');
        } else {
            $project->update(['status' => ProjectStatus::Error]);
            $this->log($project, 'error', 'Scaffolding failed.');
        }

        return $project->fresh();
    }

    private function scaffoldLaravel(Project $project): bool
    {
        return $this->runCommand($project, sprintf(
            'composer create-project laravel/laravel %s --no-interaction',
            escapeshellarg($project->path),
        ));
    }

    private function scaffoldNextJs(Project $project): bool
    {
        return $this->runCommand($project, sprintf(
            'npx create-next-app@latest %s --ts --tailwind --eslint --app --no-src-dir --import-alias "@/*" --no-turbopack --use-npm',
            escapeshellarg($project->path),
        ));
    }

    private function scaffoldAstro(Project $project): bool
    {
        return $this->runCommand($project, sprintf(
            'npm create astro@latest -- %s --template basics --install --no-git --typescript strict',
            escapeshellarg($project->path),
        ));
    }

    private function scaffoldFastApi(Project $project): bool
    {
        File::ensureDirectoryExists($project->path);

        $mainPy = <<<'PYTHON'
from fastapi import FastAPI

app = FastAPI()


@app.get("/")
def read_root():
    return {"message": "Hello from VibeCodePC!"}
PYTHON;

        $requirements = "fastapi[standard]>=0.115.0\nuvicorn[standard]>=0.34.0\n";

        File::put("{$project->path}/main.py", $mainPy);
        File::put("{$project->path}/requirements.txt", $requirements);

        return true;
    }

    private function scaffoldStaticHtml(Project $project): bool
    {
        File::ensureDirectoryExists($project->path);

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$project->name}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-950 text-white min-h-screen flex items-center justify-center">
    <div class="text-center">
        <h1 class="text-4xl font-bold mb-4">{$project->name}</h1>
        <p class="text-gray-400">Your static site is ready.</p>
    </div>
</body>
</html>
HTML;

        File::put("{$project->path}/index.html", $html);

        return true;
    }

    private function scaffoldCustom(Project $project): bool
    {
        File::ensureDirectoryExists($project->path);
        File::put("{$project->path}/.gitkeep", '');

        return true;
    }

    public function generateDockerCompose(Project $project): void
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
        $this->log($project, 'scaffold', 'Generated docker-compose.yml');
    }

    public function injectAiConfigs(Project $project): void
    {
        $providers = AiProviderConfig::whereNotNull('validated_at')->get();

        if ($providers->isEmpty()) {
            return;
        }

        $envVars = [];

        foreach ($providers as $provider) {
            $key = strtoupper($provider->provider->value).'_API_KEY';
            $envVars[$key] = $provider->getDecryptedKey();
        }

        $project->update(['env_vars' => $envVars]);
        $this->log($project, 'scaffold', sprintf('Injected %d AI provider config(s).', count($envVars)));
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
    command: npm run dev
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
    command: npm run dev -- --host
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

    private function runCommand(Project $project, string $command): bool
    {
        $result = Process::timeout(300)->run("bash -lc {$this->shellEscape($command)}");

        if (! $result->successful()) {
            $this->log($project, 'error', "Command failed: {$command}\n{$result->errorOutput()}\n{$result->output()}");
        }

        return $result->successful();
    }

    private function shellEscape(string $command): string
    {
        return escapeshellarg($command);
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
