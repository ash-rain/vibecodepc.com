<?php

declare(strict_types=1);

use App\Livewire\Dashboard\SystemSettings;
use App\Services\Tunnel\TunnelService;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;

beforeEach(function () {
    Process::fake([
        'systemctl is-active ssh' => Process::result(output: 'inactive', exitCode: 3),
        '*' => Process::result(),
    ]);
});

it('renders the system settings page', function () {
    Livewire::test(SystemSettings::class)
        ->assertStatus(200)
        ->assertSee('System Settings');
});

it('shows network information', function () {
    Livewire::test(SystemSettings::class)
        ->assertSee('Network Configuration');
});

it('can toggle ssh', function () {
    Livewire::test(SystemSettings::class)
        ->call('toggleSsh');

    Process::assertRan('sudo systemctl enable ssh && sudo systemctl start ssh');
});

it('can check for updates', function () {
    Livewire::test(SystemSettings::class)
        ->call('checkForUpdates')
        ->assertSet('statusMessage', 'Package list updated. Check for upgradable packages.');
});

it('factory reset calls artisan command and redirects', function () {
    $tunnelMock = Mockery::mock(TunnelService::class);
    $tunnelMock->shouldReceive('stop')->andReturn(null);
    app()->instance(TunnelService::class, $tunnelMock);

    Livewire::test(SystemSettings::class)
        ->call('factoryReset')
        ->assertRedirect('/');
});
