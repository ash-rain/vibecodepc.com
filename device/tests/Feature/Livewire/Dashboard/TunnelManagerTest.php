<?php

declare(strict_types=1);

use App\Livewire\Dashboard\TunnelManager;
use App\Models\Project;
use App\Models\TunnelConfig;
use App\Services\Tunnel\TunnelService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;

beforeEach(function () {
    Process::fake([
        'cloudflared --version*' => Process::result(output: '2024.1.0'),
        'pgrep*' => Process::result(output: '12345'),
        '*' => Process::result(),
    ]);

    $this->configPath = storage_path('app/test-cloudflared/config.yml');
    File::deleteDirectory(dirname($this->configPath));

    $this->app->singleton(TunnelService::class, fn () => new TunnelService(configPath: $this->configPath));

    TunnelConfig::factory()->verified()->create(['subdomain' => 'mydevice']);
});

afterEach(function () {
    File::deleteDirectory(dirname($this->configPath));
});

it('renders the tunnel manager', function () {
    Livewire::test(TunnelManager::class)
        ->assertStatus(200)
        ->assertSee('Cloudflare Tunnel')
        ->assertSee('Running');
});

it('shows the device subdomain', function () {
    Livewire::test(TunnelManager::class)
        ->assertSee('mydevice.vibecodepc.com');
});

it('lists projects with tunnel toggle', function () {
    Project::factory()->create(['name' => 'Test Project']);

    Livewire::test(TunnelManager::class)
        ->assertSee('Test Project');
});

it('can toggle project tunnel', function () {
    $project = Project::factory()->create(['tunnel_enabled' => false]);

    Livewire::test(TunnelManager::class)
        ->call('toggleProjectTunnel', $project->id);

    expect($project->fresh()->tunnel_enabled)->toBeTrue();
});

it('can restart the tunnel', function () {
    Livewire::test(TunnelManager::class)
        ->call('restartTunnel');

    Process::assertRan(fn ($process) => str_contains($process->command, 'cloudflared') && (
        str_contains($process->command, 'stop') || str_contains($process->command, 'pkill')
    ));
});
