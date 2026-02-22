<?php

declare(strict_types=1);

namespace App\Services\Projects;

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

        File::ensureDirectoryExists($this->basePath);

        $result = Process::timeout(120)->run(sprintf(
            'git clone %s %s',
            escapeshellarg($cloneUrl),
            escapeshellarg($path),
        ));

        $framework = $result->successful()
            ? $this->detectFramework($path)
            : ProjectFramework::Custom;

        $port = $this->portAllocator->allocate($framework);

        // Strip token from clone URL before storing
        $sanitizedUrl = preg_replace('#://[^@]+@#', '://', $cloneUrl);

        $project = Project::create([
            'name' => $name,
            'slug' => $slug,
            'framework' => $framework,
            'status' => $result->successful() ? ProjectStatus::Created : ProjectStatus::Error,
            'path' => $path,
            'port' => $port,
            'clone_url' => $sanitizedUrl,
        ]);

        if (! $result->successful()) {
            $this->log($project, 'error', "Clone failed: {$result->errorOutput()}");

            return $project;
        }

        $this->log($project, 'clone', "Cloned repository (detected: {$framework->label()}).");

        $this->scaffoldService->generateDockerCompose($project);
        $this->scaffoldService->injectAiConfigs($project);

        $this->log($project, 'clone', 'Project cloned successfully.');

        return $project->fresh();
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
