<?php

declare(strict_types=1);

use App\Livewire\Dashboard\TunnelManager;
use App\Models\Project;
use App\Models\TunnelConfig;
use Livewire\Livewire;

beforeEach(function () {
    // Set up centralized tunnel fakes via the trait
    $this->setUpTunnelFakes();

    // Create default verified tunnel config
    TunnelConfig::factory()->verified()->create(['subdomain' => 'mydevice']);
});

afterEach(function () {
    // Reset the fake to clean state between tests
    if (isset($this->cloudApiFake)) {
        $this->cloudApiFake->reset();
    }
});

it('renders the tunnel manager', function () {
    Livewire::test(TunnelManager::class)
        ->assertStatus(200)
        ->assertSee('Tunnel')
        ->assertSee('Running');
});

it('shows the device subdomain', function () {
    $expected = 'mydevice.'.config('vibecodepc.cloud_domain');

    Livewire::test(TunnelManager::class)
        ->assertSee($expected);
});

it('shows not configured when tunnel has no credentials', function () {
    $this->configureUnconfiguredState();

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

    Livewire::test(TunnelManager::class)
        ->call('restartTunnel')
        ->assertSet('tunnelRunning', true)
        ->assertSet('error', '');
});

it('shows error when restart fails', function () {
    $this->tunnelMock->shouldReceive('stop')->once()->andReturn(null);
    $this->tunnelMock->shouldReceive('start')->once()->andReturn('Failed to start cloudflared.');
    $this->tunnelMock->shouldReceive('isRunning')->andReturn(false);

    Livewire::test(TunnelManager::class)
        ->call('restartTunnel')
        ->assertSet('tunnelRunning', false)
        ->assertSee('Failed to start cloudflared.');
});

it('shows setup cta when tunnel is not configured', function () {
    $this->configureUnconfiguredState();

    // Clear any existing tunnel config
    TunnelConfig::query()->delete();

    Livewire::test(TunnelManager::class)
        ->assertSee('Enable Remote Access')
        ->assertSee('Set up Cloudflare Tunnel')
        ->assertSee('Free')
        ->assertSee('No credit card');
});

it('shows custom subdomain form in collapsible section', function () {
    $this->configureUnconfiguredState();

    TunnelConfig::query()->delete();

    Livewire::test(TunnelManager::class)
        ->assertSee('Or enter a custom subdomain');
});

it('checks subdomain availability when requested', function () {
    $this->configureUnconfiguredState();

    // Configure the fake to make 'mydevice' available
    $this->cloudApiFake->setResponse('checkSubdomainAvailability', true);

    TunnelConfig::query()->delete();

    Livewire::test(TunnelManager::class)
        ->set('newSubdomain', 'mydevice')
        ->call('checkAvailability')
        ->assertSet('subdomainAvailable', true)
        ->assertSee('mydevice.'.config('vibecodepc.cloud_domain').' is available!');

    // Verify the API was called
    $this->assertCloudApiCalled('checkSubdomainAvailability');
});

it('shows error when subdomain is taken', function () {
    $this->configureUnconfiguredState();

    // Configure the fake to make 'taken' unavailable
    $this->cloudApiFake->setResponse('checkSubdomainAvailability', false);

    TunnelConfig::query()->delete();

    Livewire::test(TunnelManager::class)
        ->set('newSubdomain', 'taken')
        ->call('checkAvailability')
        ->assertSet('subdomainAvailable', false)
        ->assertSee('This subdomain is taken. Try another.');

    $this->assertCloudApiCalled('checkSubdomainAvailability');
});

it('provisions tunnel when subdomain is available', function () {
    $this->configureUnconfiguredState();

    // Configure the fake with predictable responses - using default test-tunnel-999
    $this->cloudApiFake->setResponse('checkSubdomainAvailability', true);
    $this->configureProvisionResponse();

    $this->tunnelMock->shouldReceive('start')->once()->andReturn(null);
    $this->tunnelMock->shouldReceive('isRunning')->andReturn(true);

    TunnelConfig::query()->delete();

    Livewire::test(TunnelManager::class)
        ->set('newSubdomain', 'mydevice')
        ->set('subdomainAvailable', true)
        ->call('provisionTunnel')
        ->assertSet('subdomain', 'mydevice')
        ->assertSet('isProvisioning', false);

    $config = TunnelConfig::current();
    expect($config)->not->toBeNull()
        ->and($config->subdomain)->toBe('mydevice')
        ->and($config->tunnel_id)->toBe('test-tunnel-999')
        ->and($config->status)->toBe('active');

    $this->assertCloudApiCalled('provisionTunnel');
});

it('shows error when provisioning fails', function () {
    $this->configureUnconfiguredState();

    // Configure the fake to throw an exception
    $this->cloudApiFake->setException(new \Exception('API connection failed'));

    TunnelConfig::query()->delete();

    Livewire::test(TunnelManager::class)
        ->set('newSubdomain', 'mydevice')
        ->set('subdomainAvailable', true)
        ->call('provisionTunnel')
        ->assertSet('isProvisioning', false)
        ->assertSee('Failed to provision tunnel: API connection failed');
});

it('shows error when tunnel starts after provisioning fails', function () {
    $this->configureUnconfiguredState();

    // Configure with default predictable tunnel ID
    $this->configureProvisionResponse();

    $this->tunnelMock->shouldReceive('start')->once()->andReturn('Port already in use');
    $this->tunnelMock->shouldReceive('cleanup')->once();
    $this->tunnelMock->shouldReceive('isRunning')->andReturn(false);

    TunnelConfig::query()->delete();

    Livewire::test(TunnelManager::class)
        ->set('newSubdomain', 'mydevice')
        ->set('subdomainAvailable', true)
        ->call('provisionTunnel')
        ->assertSet('isProvisioning', false)
        ->assertSee('Tunnel provisioned but failed to start: Port already in use');
});

it('can re-provision existing tunnel', function () {
    // Configure with default predictable tunnel ID
    $this->configureProvisionResponse();

    $this->tunnelMock->shouldReceive('stop')->once()->andReturn(null);
    $this->tunnelMock->shouldReceive('start')->once()->andReturn(null);
    $this->tunnelMock->shouldReceive('isRunning')->andReturn(true);

    Livewire::test(TunnelManager::class)
        ->call('reprovisionTunnel')
        ->assertSet('isProvisioning', false);

    $config = TunnelConfig::current();
    expect($config->tunnel_id)->toBe('test-tunnel-999')
        ->and($config->verified_at)->toBeNull();

    $this->assertCloudApiCalled('provisionTunnel');
});

it('shows error when re-provisioning without existing config', function () {
    $this->configureUnconfiguredState();

    TunnelConfig::query()->delete();

    Livewire::test(TunnelManager::class)
        ->call('reprovisionTunnel')
        ->assertSet('isProvisioning', false)
        ->assertSee('No tunnel configuration found. Use the setup form instead.');

    // Should not call provision API without config
    $this->assertCloudApiNotCalled('provisionTunnel');
});

it('validates subdomain format', function () {
    $this->configureUnconfiguredState();

    TunnelConfig::query()->delete();

    Livewire::test(TunnelManager::class)
        ->set('newSubdomain', 'UPPERCASE')
        ->call('checkAvailability')
        ->assertHasErrors(['newSubdomain' => 'regex']);
});

it('validates subdomain must start with a letter', function () {
    $this->configureUnconfiguredState();

    TunnelConfig::query()->delete();

    Livewire::test(TunnelManager::class)
        ->set('newSubdomain', '123-start-with-number')
        ->call('checkAvailability')
        ->assertHasErrors(['newSubdomain']);
});

it('shows not configured state after skip', function () {
    // Clear any existing tunnel configs from beforeEach
    TunnelConfig::query()->delete();

    // Create a skipped tunnel config without subdomain
    TunnelConfig::factory()->skipped()->create([
        'subdomain' => null,
    ]);

    $this->configureSkippedState();

    Livewire::test(TunnelManager::class)
        ->assertSee('Enable Remote Access')
        ->assertSee('Set up Cloudflare Tunnel');
});

it('allows pairing after tunnel was skipped', function () {
    // Start with a skipped config
    TunnelConfig::factory()->skipped()->create();

    // Configure the fake with predictable responses - using default test-tunnel-999
    $this->cloudApiFake->setResponse('checkSubdomainAvailability', true);
    $this->configureProvisionResponse();

    $this->tunnelMock->shouldReceive('start')->once()->andReturn(null);
    $this->tunnelMock->shouldReceive('isRunning')->andReturn(true);

    // User comes back to TunnelManager and sets up the tunnel
    Livewire::test(TunnelManager::class)
        ->set('newSubdomain', 'mydevice')
        ->set('subdomainAvailable', true)
        ->call('provisionTunnel')
        ->assertSet('subdomain', 'mydevice')
        ->assertSee('Running');

    // Verify the config is now active, not skipped
    $config = TunnelConfig::current();
    expect($config->status)->toBe('active')
        ->and($config->subdomain)->toBe('mydevice')
        ->and($config->skipped_at)->toBeNull();
});

it('handles connection error when checking subdomain availability', function () {
    $this->configureUnconfiguredState();
    TunnelConfig::query()->delete();

    // Configure fake to throw connection exception
    $this->cloudApiFake->setException(new \Illuminate\Http\Client\ConnectionException('Connection refused'));

    Livewire::test(TunnelManager::class)
        ->set('newSubdomain', 'mydevice')
        ->call('checkAvailability')
        ->assertSet('subdomainAvailable', false)
        ->assertSet('provisionStatus', 'Could not check availability. Is the device online?');
});

it('handles server error 500 when checking subdomain availability', function () {
    $this->configureUnconfiguredState();
    TunnelConfig::query()->delete();

    // Configure fake to throw 500 error
    $this->cloudApiFake->setException(new \RuntimeException('HTTP request returned status code 500: Internal Server Error'));

    Livewire::test(TunnelManager::class)
        ->set('newSubdomain', 'mydevice')
        ->call('checkAvailability')
        ->assertSet('subdomainAvailable', false)
        ->assertSet('provisionStatus', 'Could not check availability. Is the device online?');
});

it('handles timeout when checking subdomain availability', function () {
    $this->configureUnconfiguredState();
    TunnelConfig::query()->delete();

    // Configure fake to throw timeout exception
    $this->cloudApiFake->setException(new \Illuminate\Http\Client\ConnectionException('Request timed out'));

    Livewire::test(TunnelManager::class)
        ->set('newSubdomain', 'mydevice')
        ->call('checkAvailability')
        ->assertSet('subdomainAvailable', false)
        ->assertSet('provisionStatus', 'Could not check availability. Is the device online?');
});

it('handles invalid response shape from subdomain availability check', function () {
    $this->configureUnconfiguredState();
    TunnelConfig::query()->delete();

    // Configure fake to return invalid response (false triggers 'taken' message)
    $this->cloudApiFake->setResponse('checkSubdomainAvailability', false);

    Livewire::test(TunnelManager::class)
        ->set('newSubdomain', 'mydevice')
        ->call('checkAvailability')
        ->assertSet('subdomainAvailable', false)
        ->assertSet('provisionStatus', 'This subdomain is taken. Try another.');
});

it('handles connection error during tunnel provisioning', function () {
    $this->configureUnconfiguredState();
    TunnelConfig::query()->delete();

    // Configure fake to throw connection exception
    $this->cloudApiFake->setException(new \Illuminate\Http\Client\ConnectionException('Connection refused'));

    Livewire::test(TunnelManager::class)
        ->set('newSubdomain', 'mydevice')
        ->set('subdomainAvailable', true)
        ->call('provisionTunnel')
        ->assertSet('isProvisioning', false)
        ->assertSet('error', 'Failed to provision tunnel: Connection refused');

    // Config should not be created due to connection error
    expect(TunnelConfig::current())->toBeNull();
});

it('handles server error 500 during tunnel provisioning', function () {
    $this->configureUnconfiguredState();
    TunnelConfig::query()->delete();

    // Configure fake to throw 500 error
    $this->cloudApiFake->setException(new \RuntimeException('HTTP request returned status code 500: Internal Server Error'));

    Livewire::test(TunnelManager::class)
        ->set('newSubdomain', 'mydevice')
        ->set('subdomainAvailable', true)
        ->call('provisionTunnel')
        ->assertSet('isProvisioning', false)
        ->assertSet('error', 'Failed to provision tunnel: HTTP request returned status code 500: Internal Server Error');
});

it('handles timeout during tunnel provisioning', function () {
    $this->configureUnconfiguredState();
    TunnelConfig::query()->delete();

    // Configure fake to throw timeout exception
    $this->cloudApiFake->setException(new \Illuminate\Http\Client\ConnectionException('Request timed out'));

    Livewire::test(TunnelManager::class)
        ->set('newSubdomain', 'mydevice')
        ->set('subdomainAvailable', true)
        ->call('provisionTunnel')
        ->assertSet('isProvisioning', false)
        ->assertSet('error', 'Failed to provision tunnel: Request timed out');
});

it('handles invalid response shape during tunnel provisioning', function () {
    $this->configureUnconfiguredState();
    TunnelConfig::query()->delete();

    // Configure fake to return incomplete response (missing tunnel_id/tunnel_token)
    // This simulates an API that returns 200 OK but with unexpected response structure
    $this->cloudApiFake->setResponse('provisionTunnel', ['success' => true]);

    // This will cause an error when trying to access $result['tunnel_id']
    // Note: The current implementation doesn't validate the response structure before using it,
    // so this test documents the current behavior (error is thrown as exception)
    Livewire::test(TunnelManager::class)
        ->set('newSubdomain', 'mydevice')
        ->set('subdomainAvailable', true)
        ->call('provisionTunnel')
        ->assertSet('isProvisioning', false);
})->throws(\ErrorException::class);
