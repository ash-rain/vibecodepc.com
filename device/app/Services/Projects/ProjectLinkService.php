<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\ProjectLog;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use VibecodePC\Common\Enums\ProjectStatus;

class ProjectLinkService
{
    public function __construct(
        private readonly string $basePath,
        private readonly PortAllocatorService $portAllocator,
        private readonly ProjectCloneService $cloneService,
    ) {}

    public function link(string $name, string $folderPath): Project
    {
        $slug = Str::slug($name);
        $symlinkPath = "{$this->basePath}/{$slug}";

        File::ensureDirectoryExists($this->basePath);

        if (File::exists($symlinkPath)) {
            throw new \RuntimeException("A project already exists at {$symlinkPath}.");
        }

        symlink($folderPath, $symlinkPath);

        $framework = $this->cloneService->detectFramework($folderPath);
        $port = $this->portAllocator->allocate($framework);

        $project = Project::create([
            'name' => $name,
            'slug' => $slug,
            'framework' => $framework,
            'status' => ProjectStatus::Created,
            'path' => $symlinkPath,
            'port' => $port,
        ]);

        ProjectLog::create([
            'project_id' => $project->id,
            'type' => 'link',
            'message' => "Linked existing folder: {$folderPath}",
        ]);

        return $project;
    }
}
