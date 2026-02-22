<?php

declare(strict_types=1);

use App\Services\Projects\ProjectCloneService;
use Illuminate\Support\Facades\File;
use VibecodePC\Common\Enums\ProjectFramework;

it('detects Laravel framework from composer.json', function () {
    $path = sys_get_temp_dir().'/clone-test-'.uniqid();
    File::ensureDirectoryExists($path);
    File::put("{$path}/composer.json", json_encode([
        'require' => ['laravel/framework' => '^12.0'],
    ]));

    $service = app(ProjectCloneService::class);

    expect($service->detectFramework($path))->toBe(ProjectFramework::Laravel);

    File::deleteDirectory($path);
});

it('detects Next.js framework from package.json', function () {
    $path = sys_get_temp_dir().'/clone-test-'.uniqid();
    File::ensureDirectoryExists($path);
    File::put("{$path}/package.json", json_encode([
        'dependencies' => ['next' => '^14.0', 'react' => '^18.0'],
    ]));

    $service = app(ProjectCloneService::class);

    expect($service->detectFramework($path))->toBe(ProjectFramework::NextJs);

    File::deleteDirectory($path);
});

it('detects Astro framework from package.json', function () {
    $path = sys_get_temp_dir().'/clone-test-'.uniqid();
    File::ensureDirectoryExists($path);
    File::put("{$path}/package.json", json_encode([
        'dependencies' => ['astro' => '^4.0'],
    ]));

    $service = app(ProjectCloneService::class);

    expect($service->detectFramework($path))->toBe(ProjectFramework::Astro);

    File::deleteDirectory($path);
});

it('detects FastAPI framework from requirements.txt', function () {
    $path = sys_get_temp_dir().'/clone-test-'.uniqid();
    File::ensureDirectoryExists($path);
    File::put("{$path}/requirements.txt", "fastapi>=0.100.0\nuvicorn\n");

    $service = app(ProjectCloneService::class);

    expect($service->detectFramework($path))->toBe(ProjectFramework::FastApi);

    File::deleteDirectory($path);
});

it('detects Static HTML from index.html', function () {
    $path = sys_get_temp_dir().'/clone-test-'.uniqid();
    File::ensureDirectoryExists($path);
    File::put("{$path}/index.html", '<html><body>Hello</body></html>');

    $service = app(ProjectCloneService::class);

    expect($service->detectFramework($path))->toBe(ProjectFramework::StaticHtml);

    File::deleteDirectory($path);
});

it('falls back to Custom for unknown projects', function () {
    $path = sys_get_temp_dir().'/clone-test-'.uniqid();
    File::ensureDirectoryExists($path);
    File::put("{$path}/README.md", '# My Project');

    $service = app(ProjectCloneService::class);

    expect($service->detectFramework($path))->toBe(ProjectFramework::Custom);

    File::deleteDirectory($path);
});
