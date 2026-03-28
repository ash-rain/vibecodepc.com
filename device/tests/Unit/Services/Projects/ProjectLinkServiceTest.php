<?php

declare(strict_types=1);

use App\Models\ProjectLog;
use App\Services\Projects\ProjectLinkService;
use Illuminate\Support\Facades\File;
use VibecodePC\Common\Enums\ProjectFramework;
use VibecodePC\Common\Enums\ProjectStatus;

beforeEach(function () {
    File::ensureDirectoryExists(config('vibecodepc.projects.base_path'));
});

afterEach(function () {
    $basePath = config('vibecodepc.projects.base_path');
    if (File::isDirectory($basePath)) {
        File::deleteDirectory($basePath);
    }
});

describe('link()', function () {
    it('creates symlink and project successfully', function () {
        $folderPath = sys_get_temp_dir().'/link-test-'.uniqid();
        File::ensureDirectoryExists($folderPath);
        File::put("{$folderPath}/index.html", '<html></html>');

        $service = app(ProjectLinkService::class);
        $project = $service->link('My Linked Project', $folderPath);

        expect($project->name)->toBe('My Linked Project');
        expect($project->slug)->toBe('my-linked-project');
        expect($project->framework)->toBe(ProjectFramework::StaticHtml);
        expect($project->status)->toBe(ProjectStatus::Created);
        expect($project->path)->toContain('my-linked-project');
        expect(File::isDirectory($project->path))->toBeTrue();
        expect(is_link($project->path))->toBeTrue();

        File::deleteDirectory($folderPath);
    });

    it('throws exception when symlink already exists', function () {
        $folderPath = sys_get_temp_dir().'/link-test-'.uniqid();
        $basePath = config('vibecodepc.projects.base_path');
        $symlinkPath = "{$basePath}/duplicate-project";

        File::ensureDirectoryExists($folderPath);
        File::ensureDirectoryExists($basePath);
        symlink($folderPath, $symlinkPath);

        $service = app(ProjectLinkService::class);

        expect(fn () => $service->link('Duplicate Project', $folderPath))
            ->toThrow(\RuntimeException::class, "A project already exists at {$symlinkPath}.");

        File::deleteDirectory($folderPath);
    });

    it('detects Laravel framework from composer.json', function () {
        $folderPath = sys_get_temp_dir().'/link-test-'.uniqid();
        File::ensureDirectoryExists($folderPath);
        File::put("{$folderPath}/composer.json", json_encode([
            'require' => ['laravel/framework' => '^12.0'],
        ]));

        $service = app(ProjectLinkService::class);
        $project = $service->link('Laravel Project', $folderPath);

        expect($project->framework)->toBe(ProjectFramework::Laravel);
        expect($project->port)->toBe(ProjectFramework::Laravel->defaultPort());

        File::deleteDirectory($folderPath);
    });

    it('detects Next.js framework from package.json', function () {
        $folderPath = sys_get_temp_dir().'/link-test-'.uniqid();
        File::ensureDirectoryExists($folderPath);
        File::put("{$folderPath}/package.json", json_encode([
            'dependencies' => ['next' => '^14.0', 'react' => '^18.0'],
        ]));

        $service = app(ProjectLinkService::class);
        $project = $service->link('Next.js Project', $folderPath);

        expect($project->framework)->toBe(ProjectFramework::NextJs);
        expect($project->port)->toBe(ProjectFramework::NextJs->defaultPort());

        File::deleteDirectory($folderPath);
    });

    it('detects Astro framework from package.json', function () {
        $folderPath = sys_get_temp_dir().'/link-test-'.uniqid();
        File::ensureDirectoryExists($folderPath);
        File::put("{$folderPath}/package.json", json_encode([
            'dependencies' => ['astro' => '^4.0'],
        ]));

        $service = app(ProjectLinkService::class);
        $project = $service->link('Astro Project', $folderPath);

        expect($project->framework)->toBe(ProjectFramework::Astro);
        expect($project->port)->toBe(ProjectFramework::Astro->defaultPort());

        File::deleteDirectory($folderPath);
    });

    it('detects FastAPI framework from requirements.txt', function () {
        $folderPath = sys_get_temp_dir().'/link-test-'.uniqid();
        File::ensureDirectoryExists($folderPath);
        File::put("{$folderPath}/requirements.txt", "fastapi>=0.100.0\nuvicorn\n");

        $service = app(ProjectLinkService::class);
        $project = $service->link('FastAPI Project', $folderPath);

        expect($project->framework)->toBe(ProjectFramework::FastApi);
        expect($project->port)->toBe(ProjectFramework::FastApi->defaultPort());

        File::deleteDirectory($folderPath);
    });

    it('falls back to Custom framework for unknown projects', function () {
        $folderPath = sys_get_temp_dir().'/link-test-'.uniqid();
        File::ensureDirectoryExists($folderPath);
        File::put("{$folderPath}/README.md", '# My Project');

        $service = app(ProjectLinkService::class);
        $project = $service->link('Custom Project', $folderPath);

        expect($project->framework)->toBe(ProjectFramework::Custom);
        expect($project->port)->toBe(ProjectFramework::Custom->defaultPort());

        File::deleteDirectory($folderPath);
    });

    it('allocates unique ports for multiple linked projects', function () {
        $folderPath1 = sys_get_temp_dir().'/link-test-'.uniqid();
        $folderPath2 = sys_get_temp_dir().'/link-test-'.uniqid();
        File::ensureDirectoryExists($folderPath1);
        File::ensureDirectoryExists($folderPath2);
        File::put("{$folderPath1}/index.html", '<html></html>');
        File::put("{$folderPath2}/index.html", '<html></html>');

        $service = app(ProjectLinkService::class);

        $project1 = $service->link('First Linked Project', $folderPath1);
        $project2 = $service->link('Second Linked Project', $folderPath2);

        expect($project1->port)->not->toBe($project2->port);

        File::deleteDirectory($folderPath1);
        File::deleteDirectory($folderPath2);
    });

    it('creates link log entry', function () {
        $folderPath = sys_get_temp_dir().'/link-test-'.uniqid();
        File::ensureDirectoryExists($folderPath);
        File::put("{$folderPath}/index.html", '<html></html>');

        $service = app(ProjectLinkService::class);
        $project = $service->link('Logged Project', $folderPath);

        expect(ProjectLog::where('project_id', $project->id)
            ->where('type', 'link')
            ->where('message', 'like', '%Linked existing folder%')
            ->exists())->toBeTrue();

        File::deleteDirectory($folderPath);
    });

    it('converts special characters in project name to slug', function () {
        $folderPath = sys_get_temp_dir().'/link-test-'.uniqid();
        File::ensureDirectoryExists($folderPath);
        File::put("{$folderPath}/index.html", '<html></html>');

        $service = app(ProjectLinkService::class);

        $project1 = $service->link('My App v2.0!', $folderPath);
        expect($project1->slug)->toBe('my-app-v20');
        File::deleteDirectory($folderPath);

        $folderPath2 = sys_get_temp_dir().'/link-test-'.uniqid();
        File::ensureDirectoryExists($folderPath2);
        File::put("{$folderPath2}/index.html", '<html></html>');

        $project2 = $service->link('Hello World (Test)', $folderPath2);
        expect($project2->slug)->toBe('hello-world-test');

        File::deleteDirectory($folderPath2);
    });
});
