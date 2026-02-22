<?php

declare(strict_types=1);

use App\Models\CloudCredential;
use App\Models\DeviceState;
use App\Models\TunnelConfig;
use App\Models\WizardProgress;
use App\Services\DeviceStateService;
use App\Services\Tunnel\TunnelService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tunnelMock = Mockery::mock(TunnelService::class);
    $this->tunnelMock->shouldReceive('stop')->andReturn(null)->byDefault();
    $this->app->instance(TunnelService::class, $this->tunnelMock);
});

it('resets all data with --force flag', function () {
    TunnelConfig::factory()->verified()->create();
    CloudCredential::create([
        'pairing_token_encrypted' => 'token',
        'cloud_username' => 'user',
        'cloud_email' => 'user@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => true,
        'paired_at' => now(),
    ]);

    $this->artisan('device:factory-reset', ['--force' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Factory reset complete');

    expect(TunnelConfig::count())->toBe(0);
});

it('preserves device identity after reset', function () {
    CloudCredential::create([
        'pairing_token_encrypted' => 'token',
        'cloud_username' => 'user',
        'cloud_email' => 'user@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => true,
        'paired_at' => now(),
    ]);

    $this->artisan('device:factory-reset', ['--force' => true])
        ->assertSuccessful();

    expect(CloudCredential::count())->toBe(1)
        ->and(CloudCredential::current()->cloud_username)->toBe('user');
});

it('reseeds wizard progress after reset', function () {
    WizardProgress::truncate();

    $this->artisan('device:factory-reset', ['--force' => true])
        ->assertSuccessful();

    expect(WizardProgress::count())->toBeGreaterThan(0)
        ->and(WizardProgress::where('status', 'pending')->count())->toBe(WizardProgress::count());
});

it('resets device mode to wizard after reset', function () {
    DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);

    $this->artisan('device:factory-reset', ['--force' => true])
        ->assertSuccessful();

    expect(DeviceState::getValue(DeviceStateService::MODE_KEY))->toBe(DeviceStateService::MODE_WIZARD);
});

it('stops the tunnel during reset', function () {
    $this->tunnelMock->shouldReceive('stop')->once()->andReturn(null);

    $this->artisan('device:factory-reset', ['--force' => true])
        ->assertSuccessful();
});

it('prompts for confirmation without --force', function () {
    $this->artisan('device:factory-reset')
        ->expectsConfirmation('This will erase ALL data, projects, and settings. Continue?', 'no')
        ->assertSuccessful()
        ->expectsOutputToContain('Cancelled');

    // Nothing should be truncated since we cancelled
});
