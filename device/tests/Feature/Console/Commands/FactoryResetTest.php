<?php

declare(strict_types=1);

use App\Models\AiProviderConfig;
use App\Models\CloudCredential;
use App\Models\DeviceState;
use App\Models\GitHubCredential;
use App\Models\Project;
use App\Models\ProjectLog;
use App\Models\TunnelConfig;
use App\Models\WizardProgress;
use App\Services\DeviceStateService;
use App\Services\Tunnel\TunnelService;
use App\Services\WizardProgressService;
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

it('prompts for confirmation code without --force', function () {
    $this->artisan('device:factory-reset')
        ->expectsQuestion('Enter confirmation code', 'WRONG')
        ->assertFailed()
        ->expectsOutputToContain('Confirmation code mismatch');

    // Nothing should be truncated since we cancelled
});

it('truncates all tables in correct order', function () {
    // Create test data in each table
    TunnelConfig::factory()->verified()->create();
    AiProviderConfig::factory()->validated()->create();
    GitHubCredential::factory()->create();
    $project = Project::factory()->create();
    ProjectLog::factory()->create(['project_id' => $project->id]);

    // Verify data exists before reset
    expect(TunnelConfig::count())->toBe(1);
    expect(AiProviderConfig::count())->toBe(1);
    expect(GitHubCredential::count())->toBe(1);
    expect(Project::count())->toBe(1);
    expect(ProjectLog::count())->toBe(1);

    $this->artisan('device:factory-reset', ['--force' => true])
        ->assertSuccessful();

    // Verify all tables are truncated (ProjectLog before Project to avoid FK constraint issues)
    expect(TunnelConfig::count())->toBe(0)
        ->and(AiProviderConfig::count())->toBe(0)
        ->and(GitHubCredential::count())->toBe(0)
        ->and(ProjectLog::count())->toBe(0)
        ->and(Project::count())->toBe(0);
});

it('handles empty database gracefully', function () {
    // Database starts empty with just migrations
    // Ensure all tables are empty
    TunnelConfig::truncate();
    AiProviderConfig::truncate();
    GitHubCredential::truncate();
    ProjectLog::truncate();
    Project::truncate();

    $this->artisan('device:factory-reset', ['--force' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Factory reset complete');

    // Verify tables are still empty after reset
    expect(TunnelConfig::count())->toBe(0)
        ->and(AiProviderConfig::count())->toBe(0)
        ->and(GitHubCredential::count())->toBe(0)
        ->and(ProjectLog::count())->toBe(0)
        ->and(Project::count())->toBe(0);
});

it('displays confirmation code prompt in interactive mode', function () {
    TunnelConfig::factory()->verified()->create();
    $project = Project::factory()->create();

    // In interactive mode without --force or --confirm-code, it should display the warning
    // and ask for confirmation code. We'll cancel by providing wrong code.
    $this->artisan('device:factory-reset')
        ->expectsOutputToContain('FACTORY RESET')
        ->expectsQuestion('Enter confirmation code', 'WRONG')
        ->assertFailed()
        ->expectsOutputToContain('Confirmation code mismatch');

    // Verify data was NOT truncated since we provided wrong code
    expect(Project::count())->toBe(1)
        ->and(TunnelConfig::count())->toBe(1);
});

it('outputs progress messages during truncation', function () {
    TunnelConfig::factory()->create();
    Project::factory()->create();

    $this->artisan('device:factory-reset', ['--force' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Stopping tunnel')
        ->expectsOutputToContain('Clearing database')
        ->expectsOutputToContain('Resetting wizard')
        ->expectsOutputToContain('Factory reset complete');
});

it('calls wizard reset service during factory reset', function () {
    $wizardMock = Mockery::mock(WizardProgressService::class);
    $wizardMock->shouldReceive('resetWizard')->once()->andReturn(null);
    $this->app->instance(WizardProgressService::class, $wizardMock);

    $this->artisan('device:factory-reset', ['--force' => true])
        ->assertSuccessful();
});

it('sets device state to wizard mode', function () {
    DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);

    $this->artisan('device:factory-reset', ['--force' => true])
        ->assertSuccessful();

    expect(DeviceState::getValue(DeviceStateService::MODE_KEY))->toBe(DeviceStateService::MODE_WIZARD);
});

it('accepts valid confirmation code via --confirm-code option', function () {
    TunnelConfig::factory()->verified()->create();
    $project = Project::factory()->create();
    ProjectLog::factory()->create(['project_id' => $project->id]);

    $this->artisan('device:factory-reset', ['--confirm-code' => 'ABC234'])
        ->assertSuccessful()
        ->expectsOutputToContain('Factory reset complete');

    // Verify data was actually truncated
    expect(Project::count())->toBe(0)
        ->and(ProjectLog::count())->toBe(0)
        ->and(TunnelConfig::count())->toBe(0);
});

it('rejects invalid confirmation code via --confirm-code option', function () {
    TunnelConfig::factory()->verified()->create();
    $project = Project::factory()->create();

    $this->artisan('device:factory-reset', ['--confirm-code' => 'SHORT'])
        ->assertFailed()
        ->expectsOutputToContain('Invalid confirmation code');

    // Verify data was NOT truncated
    expect(Project::count())->toBe(1)
        ->and(TunnelConfig::count())->toBe(1);
});

it('rejects confirmation code with invalid characters', function () {
    $this->artisan('device:factory-reset', ['--confirm-code' => 'ABC@#$'])
        ->assertFailed()
        ->expectsOutputToContain('Invalid confirmation code');
});

it('rejects lowercase confirmation code', function () {
    $this->artisan('device:factory-reset', ['--confirm-code' => 'abc234'])
        ->assertFailed()
        ->expectsOutputToContain('Invalid confirmation code');
});

it('rejects too short confirmation code', function () {
    $this->artisan('device:factory-reset', ['--confirm-code' => 'ABC23'])
        ->assertFailed()
        ->expectsOutputToContain('Invalid confirmation code');
});

it('rejects too long confirmation code', function () {
    $this->artisan('device:factory-reset', ['--confirm-code' => 'ABC2345'])
        ->assertFailed()
        ->expectsOutputToContain('Invalid confirmation code');
});

it('rejects confirmation code that is all numbers', function () {
    $this->artisan('device:factory-reset', ['--confirm-code' => '123456'])
        ->assertFailed()
        ->expectsOutputToContain('Invalid confirmation code');
});

it('rejects confirmation code with ambiguous characters O and 0', function () {
    $this->artisan('device:factory-reset', ['--confirm-code' => 'ABC0O2'])
        ->assertFailed()
        ->expectsOutputToContain('Invalid confirmation code');
});

it('rejects confirmation code with I and 1', function () {
    $this->artisan('device:factory-reset', ['--confirm-code' => 'ABCI12'])
        ->assertFailed()
        ->expectsOutputToContain('Invalid confirmation code');
});
