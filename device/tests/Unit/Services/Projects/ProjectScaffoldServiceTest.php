<?php

declare(strict_types=1);

use App\Jobs\ScaffoldProjectJob;
use App\Models\AiProviderConfig;
use App\Models\Project;
use App\Models\ProjectLog;
use App\Services\Projects\ProjectScaffoldService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use VibecodePC\Common\Enums\AiProvider;
use VibecodePC\Common\Enums\ProjectFramework;
use VibecodePC\Common\Enums\ProjectStatus;

beforeEach(function () {
    Storage::fake('local');
    File::ensureDirectoryExists(config('vibecodepc.projects.base_path'));
});

afterEach(function () {
    $basePath = config('vibecodepc.projects.base_path');
    if (File::isDirectory($basePath)) {
        File::deleteDirectory($basePath);
    }
});

describe('scaffold()', function () {
    it('creates a project with Scaffolding status and dispatches job', function () {
        Queue::fake();

        $service = app(ProjectScaffoldService::class);
        $project = $service->scaffold('My Test App', ProjectFramework::StaticHtml);

        expect($project->name)->toBe('My Test App');
        expect($project->slug)->toBe('my-test-app');
        expect($project->framework)->toBe(ProjectFramework::StaticHtml);
        expect($project->status)->toBe(ProjectStatus::Scaffolding);
        expect($project->path)->toContain('my-test-app');
        expect($project->port)->toBe(ProjectFramework::StaticHtml->defaultPort());

        Queue::assertPushed(ScaffoldProjectJob::class, function (ScaffoldProjectJob $job) use ($project) {
            return $job->project->id === $project->id
                && $job->framework === ProjectFramework::StaticHtml;
        });

        expect(ProjectLog::where('project_id', $project->id)->where('type', 'scaffold')->exists())->toBeTrue();
    });

    it('allocates unique ports for multiple projects', function () {
        Queue::fake();

        $service = app(ProjectScaffoldService::class);

        $project1 = $service->scaffold('App One', ProjectFramework::Laravel);
        $project2 = $service->scaffold('App Two', ProjectFramework::Laravel);

        expect($project1->port)->not->toBe($project2->port);
    });

    it('creates project directory', function () {
        Queue::fake();

        $service = app(ProjectScaffoldService::class);
        $project = $service->scaffold('Test Project', ProjectFramework::StaticHtml);

        expect(File::isDirectory($project->path))->toBeTrue();
    });
});

describe('runScaffold() - Laravel', function () {
    it('scaffolds Laravel project successfully', function () {
        Process::fake([
            'bash -lc*composer create-project*' => Process::result(output: 'Installing Laravel...', exitCode: 0),
        ]);

        $projectPath = config('vibecodepc.projects.base_path').'/laravel-test';
        File::ensureDirectoryExists($projectPath);

        $project = Project::factory()->create([
            'framework' => ProjectFramework::Laravel,
            'status' => ProjectStatus::Scaffolding,
            'path' => $projectPath,
        ]);

        $service = app(ProjectScaffoldService::class);
        $service->runScaffold($project, ProjectFramework::Laravel);

        Process::assertRan(fn ($process) => str_contains($process->command, 'composer create-project'));

        expect($project->fresh()->status)->toBe(ProjectStatus::Created);
        expect(File::exists("{$project->path}/docker-compose.yml"))->toBeTrue();
    });

    it('generates correct docker-compose for Laravel', function () {
        Process::fake([
            'bash -lc*composer create-project*' => Process::result(output: 'Installing Laravel...', exitCode: 0),
        ]);

        $projectPath = config('vibecodepc.projects.base_path').'/laravel-compose-test';
        File::ensureDirectoryExists($projectPath);

        $project = Project::factory()->create([
            'framework' => ProjectFramework::Laravel,
            'status' => ProjectStatus::Scaffolding,
            'port' => 8000,
            'path' => $projectPath,
        ]);

        $service = app(ProjectScaffoldService::class);
        $service->runScaffold($project, ProjectFramework::Laravel);

        $composeContent = File::get("{$project->path}/docker-compose.yml");
        expect($composeContent)->toContain('serversideup/php:8.4-fpm-nginx');
        expect($composeContent)->toContain('8000:8080');
        expect($composeContent)->toContain('/var/www/html');
    });

    it('logs error when Laravel scaffolding fails', function () {
        Process::fake([
            'bash -lc*composer create-project*' => Process::result(output: '', errorOutput: 'Composer error', exitCode: 1),
        ]);

        $projectPath = config('vibecodepc.projects.base_path').'/laravel-fail-test';
        File::ensureDirectoryExists($projectPath);

        $project = Project::factory()->create([
            'framework' => ProjectFramework::Laravel,
            'status' => ProjectStatus::Scaffolding,
            'path' => $projectPath,
        ]);

        $service = app(ProjectScaffoldService::class);
        $service->runScaffold($project, ProjectFramework::Laravel);

        expect($project->fresh()->status)->toBe(ProjectStatus::Error);
        expect(ProjectLog::where('project_id', $project->id)->where('type', 'error')->exists())->toBeTrue();
    });
});

describe('runScaffold() - Next.js', function () {
    it('scaffolds Next.js project successfully', function () {
        Process::fake([
            'bash -lc*npx create-next-app*' => Process::result(output: 'Creating Next.js app...', exitCode: 0),
        ]);

        $projectPath = config('vibecodepc.projects.base_path').'/nextjs-test';
        File::ensureDirectoryExists($projectPath);

        $project = Project::factory()->create([
            'framework' => ProjectFramework::NextJs,
            'status' => ProjectStatus::Scaffolding,
            'path' => $projectPath,
        ]);

        $service = app(ProjectScaffoldService::class);
        $service->runScaffold($project, ProjectFramework::NextJs);

        Process::assertRan(fn ($process) => str_contains($process->command, 'create-next-app'));

        expect($project->fresh()->status)->toBe(ProjectStatus::Created);
        expect(File::exists("{$project->path}/docker-compose.yml"))->toBeTrue();
    });

    it('generates correct docker-compose for Next.js', function () {
        Process::fake([
            'bash -lc*npx create-next-app*' => Process::result(output: 'Creating Next.js app...', exitCode: 0),
        ]);

        $projectPath = config('vibecodepc.projects.base_path').'/nextjs-compose-test';
        File::ensureDirectoryExists($projectPath);

        $project = Project::factory()->create([
            'framework' => ProjectFramework::NextJs,
            'status' => ProjectStatus::Scaffolding,
            'port' => 3000,
            'path' => $projectPath,
        ]);

        $service = app(ProjectScaffoldService::class);
        $service->runScaffold($project, ProjectFramework::NextJs);

        $composeContent = File::get("{$project->path}/docker-compose.yml");
        expect($composeContent)->toContain('node:22-slim');
        expect($composeContent)->toContain('3000:3000');
        expect($composeContent)->toContain('npm run dev');
    });
});

describe('runScaffold() - Astro', function () {
    it('scaffolds Astro project successfully', function () {
        Process::fake([
            'bash -lc*npm create astro*' => Process::result(output: 'Creating Astro project...', exitCode: 0),
        ]);

        $projectPath = config('vibecodepc.projects.base_path').'/astro-test';
        File::ensureDirectoryExists($projectPath);

        $project = Project::factory()->create([
            'framework' => ProjectFramework::Astro,
            'status' => ProjectStatus::Scaffolding,
            'path' => $projectPath,
        ]);

        $service = app(ProjectScaffoldService::class);
        $service->runScaffold($project, ProjectFramework::Astro);

        Process::assertRan(fn ($process) => str_contains($process->command, 'create astro'));

        expect($project->fresh()->status)->toBe(ProjectStatus::Created);
    });

    it('generates correct docker-compose for Astro', function () {
        Process::fake([
            'bash -lc*npm create astro*' => Process::result(output: 'Creating Astro project...', exitCode: 0),
        ]);

        $projectPath = config('vibecodepc.projects.base_path').'/astro-compose-test';
        File::ensureDirectoryExists($projectPath);

        $project = Project::factory()->create([
            'framework' => ProjectFramework::Astro,
            'status' => ProjectStatus::Scaffolding,
            'port' => 4321,
            'path' => $projectPath,
        ]);

        $service = app(ProjectScaffoldService::class);
        $service->runScaffold($project, ProjectFramework::Astro);

        $composeContent = File::get("{$project->path}/docker-compose.yml");
        expect($composeContent)->toContain('node:22-slim');
        expect($composeContent)->toContain('4321:4321');
        expect($composeContent)->toContain('--host');
    });
});

describe('runScaffold() - FastAPI', function () {
    it('scaffolds FastAPI project with correct files', function () {
        $project = Project::factory()->create([
            'framework' => ProjectFramework::FastApi,
            'status' => ProjectStatus::Scaffolding,
            'name' => 'FastAPI Test',
            'path' => config('vibecodepc.projects.base_path').'/fastapi-test',
        ]);

        $service = app(ProjectScaffoldService::class);
        $service->runScaffold($project, ProjectFramework::FastApi);

        expect(File::exists("{$project->path}/main.py"))->toBeTrue();
        expect(File::exists("{$project->path}/requirements.txt"))->toBeTrue();

        $mainPy = File::get("{$project->path}/main.py");
        expect($mainPy)->toContain('FastAPI');
        expect($mainPy)->toContain('Hello from VibeCodePC!');

        $requirements = File::get("{$project->path}/requirements.txt");
        expect($requirements)->toContain('fastapi[standard]');
        expect($requirements)->toContain('uvicorn[standard]');

        expect($project->fresh()->status)->toBe(ProjectStatus::Created);
    });

    it('generates correct docker-compose for FastAPI', function () {
        $project = Project::factory()->create([
            'framework' => ProjectFramework::FastApi,
            'status' => ProjectStatus::Scaffolding,
            'port' => 8000,
            'path' => config('vibecodepc.projects.base_path').'/fastapi-compose-test',
        ]);

        $service = app(ProjectScaffoldService::class);
        $service->runScaffold($project, ProjectFramework::FastApi);

        $composeContent = File::get("{$project->path}/docker-compose.yml");
        expect($composeContent)->toContain('python:3.12-slim');
        expect($composeContent)->toContain('8000:8000');
        expect($composeContent)->toContain('uvicorn main:app');
        expect($composeContent)->toContain('--reload');
    });
});

describe('runScaffold() - Static HTML', function () {
    it('scaffolds Static HTML project with correct file', function () {
        $project = Project::factory()->create([
            'framework' => ProjectFramework::StaticHtml,
            'status' => ProjectStatus::Scaffolding,
            'name' => 'Static Site',
            'path' => config('vibecodepc.projects.base_path').'/static-test',
        ]);

        $service = app(ProjectScaffoldService::class);
        $service->runScaffold($project, ProjectFramework::StaticHtml);

        expect(File::exists("{$project->path}/index.html"))->toBeTrue();

        $html = File::get("{$project->path}/index.html");
        expect($html)->toContain('<title>Static Site</title>');
        expect($html)->toContain('tailwindcss');
        expect($html)->toContain('Your static site is ready');

        expect($project->fresh()->status)->toBe(ProjectStatus::Created);
    });

    it('generates correct docker-compose for Static HTML', function () {
        $project = Project::factory()->create([
            'framework' => ProjectFramework::StaticHtml,
            'status' => ProjectStatus::Scaffolding,
            'port' => 8080,
            'path' => config('vibecodepc.projects.base_path').'/static-compose-test',
        ]);

        $service = app(ProjectScaffoldService::class);
        $service->runScaffold($project, ProjectFramework::StaticHtml);

        $composeContent = File::get("{$project->path}/docker-compose.yml");
        expect($composeContent)->toContain('nginx:alpine');
        expect($composeContent)->toContain('8080:80');
        expect($composeContent)->toContain('/usr/share/nginx/html');
    });
});

describe('runScaffold() - Custom', function () {
    it('scaffolds Custom project with .gitkeep', function () {
        $project = Project::factory()->create([
            'framework' => ProjectFramework::Custom,
            'status' => ProjectStatus::Scaffolding,
            'path' => config('vibecodepc.projects.base_path').'/custom-test',
        ]);

        $service = app(ProjectScaffoldService::class);
        $service->runScaffold($project, ProjectFramework::Custom);

        expect(File::exists("{$project->path}/.gitkeep"))->toBeTrue();
        expect(File::get("{$project->path}/.gitkeep"))->toBe('');
        expect($project->fresh()->status)->toBe(ProjectStatus::Created);
    });

    it('generates correct docker-compose for Custom', function () {
        $project = Project::factory()->create([
            'framework' => ProjectFramework::Custom,
            'status' => ProjectStatus::Scaffolding,
            'port' => 8080,
            'path' => config('vibecodepc.projects.base_path').'/custom-compose-test',
        ]);

        $service = app(ProjectScaffoldService::class);
        $service->runScaffold($project, ProjectFramework::Custom);

        $composeContent = File::get("{$project->path}/docker-compose.yml");
        expect($composeContent)->toContain('ubuntu:24.04');
        expect($composeContent)->toContain('8080:8080');
        expect($composeContent)->toContain('sleep infinity');
    });
});

describe('injectAiConfigs()', function () {
    it('injects validated AI provider configs into project', function () {
        AiProviderConfig::factory()->create([
            'provider' => AiProvider::OpenAI,
            'api_key_encrypted' => 'test-openai-key',
            'validated_at' => now(),
        ]);

        AiProviderConfig::factory()->create([
            'provider' => AiProvider::Anthropic,
            'api_key_encrypted' => 'test-anthropic-key',
            'validated_at' => now(),
        ]);

        $project = Project::factory()->create([
            'framework' => ProjectFramework::StaticHtml,
            'status' => ProjectStatus::Created,
            'path' => config('vibecodepc.projects.base_path').'/ai-test',
        ]);

        $service = app(ProjectScaffoldService::class);
        $service->injectAiConfigs($project);

        $project->refresh();
        expect($project->env_vars)->toHaveKey('OPENAI_API_KEY');
        expect($project->env_vars)->toHaveKey('ANTHROPIC_API_KEY');
        expect($project->env_vars['OPENAI_API_KEY'])->toBe('test-openai-key');
        expect($project->env_vars['ANTHROPIC_API_KEY'])->toBe('test-anthropic-key');

        expect(ProjectLog::where('project_id', $project->id)
            ->where('type', 'scaffold')
            ->where('message', 'like', '%Injected 2 AI provider config%')
            ->exists())->toBeTrue();
    });

    it('skips AI injection when no providers are validated', function () {
        AiProviderConfig::factory()->create([
            'provider' => AiProvider::OpenAI,
            'api_key_encrypted' => 'test-key',
            'validated_at' => null,
        ]);

        $project = Project::factory()->create([
            'framework' => ProjectFramework::StaticHtml,
            'status' => ProjectStatus::Created,
            'path' => config('vibecodepc.projects.base_path').'/ai-skip-test',
        ]);

        $service = app(ProjectScaffoldService::class);
        $service->injectAiConfigs($project);

        $project->refresh();
        expect($project->env_vars)->toBeNull();
    });

    it('is called during successful scaffold', function () {
        AiProviderConfig::factory()->create([
            'provider' => AiProvider::OpenAI,
            'api_key_encrypted' => 'test-key',
            'validated_at' => now(),
        ]);

        $project = Project::factory()->create([
            'framework' => ProjectFramework::StaticHtml,
            'status' => ProjectStatus::Scaffolding,
            'path' => config('vibecodepc.projects.base_path').'/ai-auto-test',
        ]);

        $service = app(ProjectScaffoldService::class);
        $service->runScaffold($project, ProjectFramework::StaticHtml);

        $project->refresh();
        expect($project->env_vars)->toHaveKey('OPENAI_API_KEY');
    });
});

describe('generateDockerCompose()', function () {
    it('creates docker-compose.yml for each framework', function (ProjectFramework $framework) {
        $project = Project::factory()->create([
            'framework' => $framework,
            'status' => ProjectStatus::Created,
            'port' => $framework->defaultPort(),
            'path' => config('vibecodepc.projects.base_path')."/docker-{$framework->value}-test",
        ]);

        File::ensureDirectoryExists($project->path);

        $service = app(ProjectScaffoldService::class);
        $service->generateDockerCompose($project);

        expect(File::exists("{$project->path}/docker-compose.yml"))->toBeTrue();

        $content = File::get("{$project->path}/docker-compose.yml");
        expect($content)->toContain('services:');
        expect($content)->toContain('app:');
        expect($content)->toContain("{$project->port}:");

        expect(ProjectLog::where('project_id', $project->id)
            ->where('type', 'scaffold')
            ->where('message', 'Generated docker-compose.yml')
            ->exists())->toBeTrue();
    })->with([
        'Laravel' => [ProjectFramework::Laravel],
        'NextJs' => [ProjectFramework::NextJs],
        'Astro' => [ProjectFramework::Astro],
        'FastApi' => [ProjectFramework::FastApi],
        'StaticHtml' => [ProjectFramework::StaticHtml],
        'Custom' => [ProjectFramework::Custom],
    ]);
});

describe('project slug generation', function () {
    it('converts special characters in project name to slug', function () {
        Queue::fake();

        $service = app(ProjectScaffoldService::class);

        $project1 = $service->scaffold('My App v2.0!', ProjectFramework::StaticHtml);
        expect($project1->slug)->toBe('my-app-v20');

        $project2 = $service->scaffold('Hello World (Test)', ProjectFramework::StaticHtml);
        expect($project2->slug)->toBe('hello-world-test');
    });
});

describe('logging', function () {
    it('creates scaffold log entry when starting', function () {
        Queue::fake();

        $service = app(ProjectScaffoldService::class);
        $project = $service->scaffold('Log Test', ProjectFramework::Laravel);

        expect(ProjectLog::where('project_id', $project->id)
            ->where('type', 'scaffold')
            ->where('message', 'like', '%Scaffolding Laravel%')
            ->exists())->toBeTrue();
    });

    it('creates success log when scaffolding completes', function () {
        $project = Project::factory()->create([
            'framework' => ProjectFramework::StaticHtml,
            'status' => ProjectStatus::Scaffolding,
            'path' => config('vibecodepc.projects.base_path').'/log-success-test',
        ]);

        $service = app(ProjectScaffoldService::class);
        $service->runScaffold($project, ProjectFramework::StaticHtml);

        expect(ProjectLog::where('project_id', $project->id)
            ->where('type', 'scaffold')
            ->where('message', 'Project scaffolded successfully.')
            ->exists())->toBeTrue();
    });
});
