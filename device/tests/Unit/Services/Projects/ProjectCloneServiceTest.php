<?php

declare(strict_types=1);

use App\Models\Project;
use App\Services\Projects\ProjectCloneService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
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

it('installs composer dependencies and sets up env for cloned Laravel project', function () {
    Process::fake([
        'git clone*' => Process::result(output: 'Cloning...', exitCode: 0),
        'composer install*' => Process::result(output: 'Installing...', exitCode: 0),
        'cp .env.example*' => Process::result(exitCode: 0),
    ]);

    $basePath = config('vibecodepc.projects.base_path');
    $projectPath = "{$basePath}/laravel-clone";

    // Seed the cloned project files so detectFramework works
    File::ensureDirectoryExists($projectPath);
    File::put("{$projectPath}/composer.json", json_encode([
        'require' => ['laravel/framework' => '^12.0'],
    ]));

    $service = app(ProjectCloneService::class);
    $project = $service->clone('laravel-clone', 'https://github.com/user/repo.git');

    Process::assertRan(fn ($process) => str_contains($process->command, 'composer install'));
    Process::assertRan(fn ($process) => str_contains($process->command, 'cp .env.example'));

    expect($project->framework)->toBe(ProjectFramework::Laravel);
    expect(File::exists("{$projectPath}/docker-compose.yml"))->toBeTrue();
    expect(File::get("{$projectPath}/docker-compose.yml"))->toContain('AUTORUN_LARAVEL_MIGRATION');

    File::deleteDirectory($projectPath);
});

it('installs npm dependencies for cloned Next.js project', function () {
    Process::fake([
        'git clone*' => Process::result(output: 'Cloning...', exitCode: 0),
        'npm install' => Process::result(output: 'Installing...', exitCode: 0),
    ]);

    $basePath = config('vibecodepc.projects.base_path');
    $projectPath = "{$basePath}/nextjs-clone";

    File::ensureDirectoryExists($projectPath);
    File::put("{$projectPath}/package.json", json_encode([
        'dependencies' => ['next' => '^14.0', 'react' => '^18.0'],
    ]));

    $service = app(ProjectCloneService::class);
    $project = $service->clone('nextjs-clone', 'https://github.com/user/repo.git');

    Process::assertRan(fn ($process) => $process->command === 'npm install');

    expect($project->framework)->toBe(ProjectFramework::NextJs);
    expect(File::exists("{$projectPath}/docker-compose.yml"))->toBeTrue();
    expect(File::get("{$projectPath}/docker-compose.yml"))->toContain('npm install && npm run dev');

    File::deleteDirectory($projectPath);
});

it('generates docker-compose with npm install for cloned Astro project', function () {
    Process::fake([
        'git clone*' => Process::result(output: 'Cloning...', exitCode: 0),
        'npm install' => Process::result(output: 'Installing...', exitCode: 0),
    ]);

    $basePath = config('vibecodepc.projects.base_path');
    $projectPath = "{$basePath}/astro-clone";

    File::ensureDirectoryExists($projectPath);
    File::put("{$projectPath}/package.json", json_encode([
        'dependencies' => ['astro' => '^4.0'],
    ]));

    $service = app(ProjectCloneService::class);
    $project = $service->clone('astro-clone', 'https://github.com/user/repo.git');

    expect($project->framework)->toBe(ProjectFramework::Astro);
    expect(File::get("{$projectPath}/docker-compose.yml"))->toContain('npm install && npm run dev');

    File::deleteDirectory($projectPath);
});

it('skips dependency install for static HTML clones', function () {
    Process::fake([
        'git clone*' => Process::result(output: 'Cloning...', exitCode: 0),
    ]);

    $basePath = config('vibecodepc.projects.base_path');
    $projectPath = "{$basePath}/static-clone";

    File::ensureDirectoryExists($projectPath);
    File::put("{$projectPath}/index.html", '<html></html>');

    $service = app(ProjectCloneService::class);
    $project = $service->clone('static-clone', 'https://github.com/user/repo.git');

    Process::assertDidntRun('composer install*');
    Process::assertDidntRun('npm install');

    expect($project->framework)->toBe(ProjectFramework::StaticHtml);

    File::deleteDirectory($projectPath);
});
