<?php

declare(strict_types=1);

use App\Models\CloudCredential;
use App\Models\DeviceState;
use App\Models\Project;
use App\Models\QuickTunnel;
use App\Services\DeviceStateService;
use App\Services\Tunnel\TunnelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tunnelMock = Mockery::mock(TunnelService::class);
    $this->tunnelMock->shouldReceive('isRunning')->andReturn(true)->byDefault();
    $this->app->instance(TunnelService::class, $this->tunnelMock);

    // Fake process calls for metrics - note we need to include all commands
    Process::fake([
        '*top*' => Process::result(output: '25.3'),
        '*free -m*' => Process::result(output: "Mem: 8192 4096 4096 0 0 0\n"),
        '*df -BG*' => Process::result(output: "Filesystem Size Used Avail Use% Mounted on\n/dev/root 64G 32G 32G 50% /\n"),
        '*thermal_zone0*' => Process::result(output: '52000'),
        '*uptime*' => Process::result(output: 'up 5 days, 3 hours, 20 minutes'),
        '*hostname -I*' => Process::result(output: '192.168.1.100'),
        '*ip link show eth0*' => Process::result(output: 'eth0: state UP'),
        '*ip link show wlan0*' => Process::result(output: ''),
        '*timedatectl*' => Process::result(output: 'UTC'),
    ]);

    // Create paired cloud credential
    CloudCredential::create([
        'pairing_token_encrypted' => 'encrypted-token',
        'cloud_username' => 'test-user',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => true,
        'paired_at' => now(),
    ]);

    // Set device state to dashboard
    DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);
});

it('displays health metrics in table format', function () {
    Project::factory()->count(3)->running()->create();
    QuickTunnel::factory()->count(2)->running()->create();

    $this->artisan('device:health')
        ->assertSuccessful()
        ->expectsOutputToContain('Device Health Status Report')
        ->expectsOutputToContain('Device Information')
        ->expectsOutputToContain('System Resources')
        ->expectsOutputToContain('Network Status')
        ->expectsOutputToContain('Application State');
});

it('outputs metrics in JSON format', function () {
    $this->artisan('device:health', ['--json' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('cpu_percent');
});

it('shows correct device information', function () {
    $this->artisan('device:health')
        ->assertSuccessful()
        ->expectsOutputToContain('test-user')
        ->expectsOutputToContain('Dashboard');
});

it('shows correct network status', function () {
    $this->artisan('device:health')
        ->assertSuccessful()
        ->expectsOutputToContain('Local IP')
        ->expectsOutputToContain('Internet');
});

it('shows running projects count', function () {
    Project::factory()->count(5)->running()->create();
    Project::factory()->count(3)->stopped()->create();

    $this->artisan('device:health')
        ->assertSuccessful()
        ->expectsOutputToContain('Running Projects');
});

it('shows tunnel status', function () {
    $this->tunnelMock->shouldReceive('isRunning')->andReturn(true);

    $this->artisan('device:health')
        ->assertSuccessful()
        ->expectsOutputToContain('Tunnel Status');
});

it('shows quick tunnels count', function () {
    QuickTunnel::factory()->count(4)->running()->create();
    QuickTunnel::factory()->count(2)->stopped()->create();

    $this->artisan('device:health')
        ->assertSuccessful()
        ->expectsOutputToContain('Quick Tunnels');
});

it('shows not paired status when unpaired', function () {
    CloudCredential::truncate();

    $this->artisan('device:health')
        ->assertSuccessful()
        ->expectsOutputToContain('Not paired');
});

it('handles device without cloud credentials gracefully', function () {
    CloudCredential::truncate();

    $this->artisan('device:health')
        ->assertSuccessful();
});

it('displays uptime when available', function () {
    $this->artisan('device:health')
        ->assertSuccessful()
        ->expectsOutputToContain('Uptime');
});

it('includes report generation timestamp', function () {
    $this->artisan('device:health')
        ->assertSuccessful()
        ->expectsOutputToContain('Report generated');
});

it('displays temperature when available', function () {
    Process::fake([
        '*top*' => Process::result(output: '25.3'),
        '*free -m*' => Process::result(output: "Mem: 8192 4096 4096 0 0 0\n"),
        '*df -BG*' => Process::result(output: "Filesystem Size Used Avail Use% Mounted on\n/dev/root 64G 32G 32G 50% /\n"),
        '*thermal_zone0*' => Process::result(output: '52000'),
        '*uptime*' => Process::result(output: 'up 2 hours'),
    ]);

    $this->artisan('device:health')
        ->assertSuccessful()
        ->expectsOutputToContain('Temperature');
});

it('shows timezone information', function () {
    $this->artisan('device:health')
        ->assertSuccessful()
        ->expectsOutputToContain('Timezone');
});

it('accepts format option', function () {
    $this->artisan('device:health', ['--format' => 'json'])
        ->assertSuccessful();
});
