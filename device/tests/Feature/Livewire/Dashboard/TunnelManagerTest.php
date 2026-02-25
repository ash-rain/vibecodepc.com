<?php

declare(strict_types=1);

use App\Livewire\Dashboard\TunnelManager;
use App\Models\Project;
use App\Models\TunnelConfig;
use App\Services\Tunnel\TunnelService;
use Livewire\Livewire;

beforeEach(function () {
    $this->tunnelMock = Mockery::mock(TunnelService::class);
    $this->tunnelMock->shouldReceive('getStatus')->andReturn([
        'installed' => true,
        'running' => true,
        'configured' => true,
    ])->byDefault();

    $this->app->instance(TunnelService::class, $this->tunnelMock);

    TunnelConfig::factory()->verified()->create(['subdomain' => 'mydevice']);
});

it('renders the tunnel manager', function () {
    Livewire::test(TunnelManager::class)
        ->assertStatus(200)
        ->assertSee('Tunnel')
        ->assertSee('Running');
});

it('shows the device subdomain', function () {
    $expected = 'mydevice.' . config('vibecodepc.cloud_domain');

    Livewire::test(TunnelManager::class)
        ->assertSee($expected);
});

it('shows not configured when tunnel has no credentials', function () {
    $this->tunnelMock->shouldReceive('getStatus')->andReturn([
        'installed' => true,
        'running' => false,
        'configured' => false,
    ]);

    Livewire::test(TunnelManager::class)
        ->assertSee('Not Configured')
        ->assertDontSee('Restart');
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
    $this->tunnelMock->shouldReceive('stop')->once()->andReturn(null);
    $this->tunnelMock->shouldReceive('start')->once()->andReturn(null);
    $this->tunnelMock->shouldReceive('isRunning')->andReturn(true);
    $this->tunnelMock->shouldReceive('hasCredentials')->andReturn(true);

    Livewire::test(TunnelManager::class)
        ->call('restartTunnel')
        ->assertSet('tunnelRunning', true)
        ->assertSet('error', '');
});

it('shows error when restart fails', function () {
    $this->tunnelMock->shouldReceive('stop')->once()->andReturn(null);
    $this->tunnelMock->shouldReceive('start')->once()->andReturn('Failed to start cloudflared.');
    $this->tunnelMock->shouldReceive('isRunning')->andReturn(false);
    $this->tunnelMock->shouldReceive('hasCredentials')->andReturn(true);

    Livewire::test(TunnelManager::class)
        ->call('restartTunnel')
        ->assertSet('tunnelRunning', false)
        ->assertSee('Failed to start cloudflared.');
});
