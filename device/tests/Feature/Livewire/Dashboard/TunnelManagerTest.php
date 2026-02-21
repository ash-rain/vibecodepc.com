<?php

declare(strict_types=1);

use App\Livewire\Dashboard\TunnelManager;
use App\Models\Project;
use App\Models\TunnelConfig;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;

beforeEach(function () {
    Process::fake([
        'which cloudflared' => Process::result(output: '/usr/bin/cloudflared'),
        'systemctl is-active cloudflared' => Process::result(output: 'active'),
        '*' => Process::result(),
    ]);

    TunnelConfig::factory()->verified()->create(['subdomain' => 'mydevice']);
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

    Process::assertRan('sudo systemctl stop cloudflared');
    Process::assertRan('sudo systemctl start cloudflared');
});
