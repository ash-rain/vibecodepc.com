<?php

declare(strict_types=1);

use App\Livewire\Wizard\Tunnel;
use App\Models\CloudCredential;
use App\Models\TunnelConfig;
use App\Services\WizardProgressService;
use Livewire\Livewire;
use Tests\Support\Concerns\HasTunnelFakes;
use VibecodePC\Common\Enums\WizardStep;

uses(HasTunnelFakes::class);

beforeEach(function () {
    app(WizardProgressService::class)->seedProgress();
    $this->setUpTunnelFakes();
});

// ============================================================================
// RENDERING TESTS
// ============================================================================

it('renders the tunnel step', function () {
    Livewire::test(Tunnel::class)
        ->assertStatus(200)
        ->assertSee('Cloudflare Tunnel');
});

it('shows tunnel status badges', function () {
    // Default state from HasTunnelFakes: configured=true, running=true
    Livewire::test(Tunnel::class)
        ->assertSet('tunnelConfigured', true)
        ->assertSet('tunnelRunning', true)
        ->assertSee('Tunnel Configured')
        ->assertSee('Running');
});

it('shows not configured state when tunnel is not set up', function () {
    $this->configureUnconfiguredState();

    Livewire::test(Tunnel::class)
        ->assertSet('tunnelConfigured', false)
        ->assertSet('tunnelRunning', false)
        ->assertSee('Not Configured');
});

it('shows stopped state when configured but not running', function () {
    $this->tunnelMock->shouldReceive('getStatus')->andReturn([
        'installed' => true,
        'running' => false,
        'configured' => true,
    ])->byDefault();
    $this->tunnelMock->shouldReceive('isRunning')->andReturn(false)->byDefault();

    Livewire::test(Tunnel::class)
        ->assertSet('tunnelConfigured', true)
        ->assertSet('tunnelRunning', false)
        ->assertSee('Tunnel Configured')
        ->assertSee('Stopped');
});

// ============================================================================
// INITIALIZATION TESTS
// ============================================================================

it('loads subdomain from existing tunnel config', function () {
    TunnelConfig::updateOrCreate(
        ['subdomain' => 'my-device'],
        [
            'tunnel_id' => 'test-tunnel-id',
            'tunnel_token_encrypted' => 'encrypted-token',
            'status' => 'active',
        ]
    );

    $this->tunnelMock->shouldReceive('getStatus')->andReturn([
        'installed' => true,
        'running' => true,
        'configured' => true,
    ])->byDefault();

    Livewire::test(Tunnel::class)
        ->assertSet('subdomain', 'my-device');
});

it('prefills subdomain from cloud username when not configured', function () {
    $this->configureUnconfiguredState();

    CloudCredential::create([
        'pairing_token_encrypted' => 'test-token',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => true,
        'paired_at' => now(),
    ]);

    Livewire::test(Tunnel::class)
        ->assertSet('newSubdomain', 'testuser')
        ->assertSet('subdomainAvailable', true);
});

it('does not prefill subdomain when no cloud credential exists', function () {
    $this->configureUnconfiguredState();

    Livewire::test(Tunnel::class)
        ->assertSet('newSubdomain', '')
        ->assertSet('subdomainAvailable', false);
});

// ============================================================================
// SUBDOMAIN VALIDATION TESTS
// ============================================================================

it('validates empty subdomain on availability check', function () {
    $this->configureUnconfiguredState();

    Livewire::test(Tunnel::class)
        ->set('newSubdomain', '')
        ->call('checkAvailability')
        ->assertSet('error', 'Please enter a subdomain.')
        ->assertSet('subdomainAvailable', false);
});

it('validates subdomain must start with a letter', function () {
    $this->configureUnconfiguredState();

    Livewire::test(Tunnel::class)
        ->set('newSubdomain', '123-device')
        ->call('checkAvailability')
        ->assertSet('error', 'Subdomain must start with a letter, use lowercase alphanumeric and hyphens only.')
        ->assertSet('subdomainAvailable', false);
});

it('validates subdomain cannot end with hyphen', function () {
    $this->configureUnconfiguredState();

    Livewire::test(Tunnel::class)
        ->set('newSubdomain', 'device-')
        ->call('checkAvailability')
        ->assertSet('error', 'Subdomain must start with a letter, use lowercase alphanumeric and hyphens only.')
        ->assertSet('subdomainAvailable', false);
});

it('validates subdomain cannot contain uppercase letters', function () {
    $this->configureUnconfiguredState();

    Livewire::test(Tunnel::class)
        ->set('newSubdomain', 'MyDevice')
        ->call('checkAvailability')
        ->assertSet('error', 'Subdomain must start with a letter, use lowercase alphanumeric and hyphens only.')
        ->assertSet('subdomainAvailable', false);
});

it('accepts valid subdomain format', function () {
    $this->configureUnconfiguredState();
    $this->configureSubdomainAvailability(['my-device']);

    Livewire::test(Tunnel::class)
        ->set('newSubdomain', 'my-device')
        ->call('checkAvailability')
        ->assertSet('error', '')
        ->assertSet('subdomainAvailable', true);
});

it('accepts subdomain with numbers in middle', function () {
    $this->configureUnconfiguredState();
    $this->configureSubdomainAvailability(['device-123']);

    Livewire::test(Tunnel::class)
        ->set('newSubdomain', 'device-123')
        ->call('checkAvailability')
        ->assertSet('subdomainAvailable', true);
});

// ============================================================================
// AVAILABILITY CHECK TESTS
// ============================================================================

it('shows success message when subdomain is available', function () {
    $this->configureUnconfiguredState();
    $this->configureSubdomainAvailability(['available-device']);

    Livewire::test(Tunnel::class)
        ->set('newSubdomain', 'available-device')
        ->call('checkAvailability')
        ->assertSet('subdomainAvailable', true)
        ->assertSee('available-device');
});

it('shows unavailable message when subdomain is taken', function () {
    $this->configureUnconfiguredState();
    $this->cloudApiFake->setResponse('checkSubdomainAvailability', false);

    Livewire::test(Tunnel::class)
        ->set('newSubdomain', 'taken-device')
        ->call('checkAvailability')
        ->assertSet('subdomainAvailable', false)
        ->assertSee('This subdomain is taken');
});

it('handles api error during availability check', function () {
    $this->configureUnconfiguredState();
    $this->configureApiFailure('checkSubdomainAvailability', 'Connection refused');

    Livewire::test(Tunnel::class)
        ->set('newSubdomain', 'test-device')
        ->call('checkAvailability')
        ->assertSet('subdomainAvailable', false)
        ->assertSee('Could not check availability');
});

it('clears previous error when checking new subdomain', function () {
    $this->configureUnconfiguredState();

    $component = Livewire::test(Tunnel::class);

    // First set an error
    $component->set('error', 'Previous error');

    // Then check availability with valid subdomain
    $this->configureSubdomainAvailability(['valid-device']);
    $component
        ->set('newSubdomain', 'valid-device')
        ->call('checkAvailability')
        ->assertSet('error', '');
});

// ============================================================================
// PROVISION TUNNEL TESTS
// ============================================================================

it('provisions tunnel when subdomain is available', function () {
    $this->configureUnconfiguredState();
    $this->configureSubdomainAvailability(['my-device']);
    $this->configureProvisionResponse('test-tunnel-999', 'test-token-value');

    $this->tunnelMock->shouldReceive('start')->andReturn(null)->once();

    Livewire::test(Tunnel::class)
        ->set('newSubdomain', 'my-device')
        ->set('subdomainAvailable', true)
        ->call('provisionTunnel')
        ->assertSet('subdomain', 'my-device')
        ->assertSet('isProvisioning', false)
        ->assertSet('subdomainAvailable', false); // Cleared after successful provision

    // Verify tunnel config was created in database
    $config = TunnelConfig::where('subdomain', 'my-device')->first();
    expect($config)->not->toBeNull()
        ->and($config->tunnel_id)->toBe('test-tunnel-999')
        ->and($config->tunnel_token_encrypted)->toBe('test-token-value')
        ->and($config->status)->toBe('active');

    $this->assertCloudApiCalled('provisionTunnel');
});

it('does not provision when subdomain is not available', function () {
    $this->configureUnconfiguredState();

    Livewire::test(Tunnel::class)
        ->set('newSubdomain', 'unavailable-device')
        ->set('subdomainAvailable', false)
        ->call('provisionTunnel')
        ->assertSet('subdomain', null);

    $this->assertCloudApiNotCalled('provisionTunnel');
});

it('shows error when provision fails', function () {
    $this->configureUnconfiguredState();
    $this->configureSubdomainAvailability(['my-device']);
    $this->configureApiFailure('provisionTunnel', 'API error: rate limited');

    Livewire::test(Tunnel::class)
        ->set('newSubdomain', 'my-device')
        ->set('subdomainAvailable', true)
        ->call('provisionTunnel')
        ->assertSet('isProvisioning', false)
        ->assertSet('error', 'Failed to provision tunnel: API error: rate limited');
});

it('handles tunnel start failure during provision', function () {
    $this->configureUnconfiguredState();
    $this->configureSubdomainAvailability(['my-device']);
    $this->configureProvisionResponse('test-tunnel-999', 'test-token-value');

    $this->tunnelMock->shouldReceive('start')->andReturn('Failed to start tunnel service')->once();
    $this->tunnelMock->shouldReceive('cleanup')->once();

    Livewire::test(Tunnel::class)
        ->set('newSubdomain', 'my-device')
        ->set('subdomainAvailable', true)
        ->call('provisionTunnel')
        ->assertSet('isProvisioning', false)
        ->assertSet('error', 'Tunnel provisioned but failed to start: Failed to start tunnel service');
});

it('clears newSubdomain after successful provision', function () {
    $this->configureUnconfiguredState();
    $this->configureSubdomainAvailability(['my-device']);
    $this->configureProvisionResponse();

    $this->tunnelMock->shouldReceive('start')->andReturn(null);

    Livewire::test(Tunnel::class)
        ->set('newSubdomain', 'my-device')
        ->set('subdomainAvailable', true)
        ->call('provisionTunnel')
        ->assertSet('newSubdomain', '')
        ->assertSet('subdomainAvailable', false);
});

// ============================================================================
// COMPLETE STEP TESTS
// ============================================================================

it('completes the tunnel step', function () {
    $this->configureUnconfiguredState();

    Livewire::test(Tunnel::class)
        ->set('subdomain', 'my-device')
        ->call('complete')
        ->assertDispatched('step-completed');

    expect(app(WizardProgressService::class)->isStepCompleted(WizardStep::Tunnel))->toBeTrue();
});

it('stores subdomain data when completing step', function () {
    $this->configureUnconfiguredState();

    Livewire::test(Tunnel::class)
        ->set('subdomain', 'my-configured-device')
        ->call('complete');

    $data = app(WizardProgressService::class)->getStepData(WizardStep::Tunnel);
    expect($data['subdomain'])->toBe('my-configured-device');
});

it('allows complete without subdomain when tunnel is not configured', function () {
    $this->configureUnconfiguredState();

    Livewire::test(Tunnel::class)
        ->set('subdomain', null)
        ->call('complete')
        ->assertDispatched('step-completed');

    expect(app(WizardProgressService::class)->isStepCompleted(WizardStep::Tunnel))->toBeTrue();
});

// ============================================================================
// SKIP FUNCTIONALITY TESTS
// ============================================================================

it('skips the tunnel step', function () {
    Livewire::test(Tunnel::class)
        ->call('skip')
        ->assertDispatched('step-skipped');

    $progress = app(WizardProgressService::class)->getProgress()
        ->firstWhere('step', WizardStep::Tunnel->value);

    expect($progress->status->value)->toBe('skipped');
});

it('marks step as skipped in wizard progress', function () {
    Livewire::test(Tunnel::class)
        ->call('skip');

    expect(app(WizardProgressService::class)->isStepAccessible(WizardStep::Tunnel))->toBeTrue();
});

it('dispatches step-skipped event when skip is called', function () {
    Livewire::test(Tunnel::class)
        ->call('skip')
        ->assertDispatched('step-skipped');
});

// ============================================================================
// EDGE CASE TESTS
// ============================================================================

it('handles provision when subdomain is not available', function () {
    $this->configureUnconfiguredState();

    // When subdomainAvailable is false, provision should return early
    Livewire::test(Tunnel::class)
        ->set('newSubdomain', 'my-device')
        ->set('subdomainAvailable', false)
        ->call('provisionTunnel')
        ->assertSet('subdomain', null) // Should not provision
        ->assertSet('isProvisioning', false);

    $this->assertCloudApiNotCalled('provisionTunnel');
});

it('resets provision status on new availability check', function () {
    $this->configureUnconfiguredState();

    $component = Livewire::test(Tunnel::class);

    // Set some status
    $component->set('provisionStatus', 'Previous status');

    // Check availability clears it
    $this->configureSubdomainAvailability(['device']);
    $component
        ->set('newSubdomain', 'device')
        ->call('checkAvailability')
        ->assertSet('provisionStatus', 'device.vibecodepc.com is available!');
});

it('shows continue button text when tunnel is configured', function () {
    $this->tunnelMock->shouldReceive('getStatus')->andReturn([
        'installed' => true,
        'running' => true,
        'configured' => true,
    ])->byDefault();

    Livewire::test(Tunnel::class)
        ->assertSee('Continue');
});

it('shows continue without tunnel text when not configured', function () {
    $this->configureUnconfiguredState();

    Livewire::test(Tunnel::class)
        ->assertSee('Continue without tunnel');
});

it('shows skip for now option', function () {
    Livewire::test(Tunnel::class)
        ->assertSee('Skip for now');
});

it('clears error when starting new check', function () {
    $this->configureUnconfiguredState();

    $component = Livewire::test(Tunnel::class);

    // Set an error first
    $component->set('error', 'Some error message');

    // Check availability clears it
    $this->configureSubdomainAvailability(['test-device']);
    $component
        ->set('newSubdomain', 'test-device')
        ->call('checkAvailability')
        ->assertSet('error', '');
});
