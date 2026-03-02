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
    $expected = 'mydevice.'.config('vibecodepc.cloud_domain');

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

it('shows setup cta when tunnel is not configured', function () {
    $this->tunnelMock->shouldReceive('getStatus')->andReturn([
        'installed' => true,
        'running' => false,
        'configured' => false,
    ]);

    // Clear any existing tunnel config
    TunnelConfig::query()->delete();

    Livewire::test(TunnelManager::class)
        ->assertSee('Enable Remote Access')
        ->assertSee('Set up Cloudflare Tunnel')
        ->assertSee('Free')
        ->assertSee('No credit card');
});

it('shows custom subdomain form in collapsible section', function () {
    $this->tunnelMock->shouldReceive('getStatus')->andReturn([
        'installed' => true,
        'running' => false,
        'configured' => false,
    ]);

    TunnelConfig::query()->delete();

    Livewire::test(TunnelManager::class)
        ->assertSee('Or enter a custom subdomain');
});

it('checks subdomain availability when requested', function () {
    $mockCloudApi = Mockery::mock(\App\Services\CloudApiClient::class);
    $mockCloudApi->shouldReceive('checkSubdomainAvailability')
        ->with('mydevice')
        ->once()
        ->andReturn(true);

    $this->app->instance(\App\Services\CloudApiClient::class, $mockCloudApi);

    $this->tunnelMock->shouldReceive('getStatus')->andReturn([
        'installed' => true,
        'running' => false,
        'configured' => false,
    ]);

    TunnelConfig::query()->delete();

    Livewire::test(TunnelManager::class)
        ->set('newSubdomain', 'mydevice')
        ->call('checkAvailability')
        ->assertSet('subdomainAvailable', true)
        ->assertSee('mydevice.'.config('vibecodepc.cloud_domain').' is available!');
});

it('shows error when subdomain is taken', function () {
    $mockCloudApi = Mockery::mock(\App\Services\CloudApiClient::class);
    $mockCloudApi->shouldReceive('checkSubdomainAvailability')
        ->with('taken')
        ->once()
        ->andReturn(false);

    $this->app->instance(\App\Services\CloudApiClient::class, $mockCloudApi);

    $this->tunnelMock->shouldReceive('getStatus')->andReturn([
        'installed' => true,
        'running' => false,
        'configured' => false,
    ]);

    TunnelConfig::query()->delete();

    Livewire::test(TunnelManager::class)
        ->set('newSubdomain', 'taken')
        ->call('checkAvailability')
        ->assertSet('subdomainAvailable', false)
        ->assertSee('This subdomain is taken. Try another.');
});

it('provisions tunnel when subdomain is available', function () {
    $mockCloudApi = Mockery::mock(\App\Services\CloudApiClient::class);
    $mockCloudApi->shouldReceive('checkSubdomainAvailability')
        ->andReturn(true);
    $mockCloudApi->shouldReceive('provisionTunnel')
        ->once()
        ->andReturn([
            'tunnel_id' => 'test-tunnel-123',
            'tunnel_token' => 'test-token-value',
        ]);

    $this->app->instance(\App\Services\CloudApiClient::class, $mockCloudApi);

    $this->tunnelMock->shouldReceive('getStatus')->andReturn([
        'installed' => true,
        'running' => false,
        'configured' => false,
    ]);
    $this->tunnelMock->shouldReceive('start')->once()->andReturn(null);
    $this->tunnelMock->shouldReceive('isRunning')->andReturn(true);
    $this->tunnelMock->shouldReceive('hasCredentials')->andReturn(true);

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
        ->and($config->tunnel_id)->toBe('test-tunnel-123')
        ->and($config->status)->toBe('active');
});

it('shows error when provisioning fails', function () {
    $mockCloudApi = Mockery::mock(\App\Services\CloudApiClient::class);
    $mockCloudApi->shouldReceive('provisionTunnel')
        ->once()
        ->andThrow(new \Exception('API connection failed'));

    $this->app->instance(\App\Services\CloudApiClient::class, $mockCloudApi);

    $this->tunnelMock->shouldReceive('getStatus')->andReturn([
        'installed' => true,
        'running' => false,
        'configured' => false,
    ]);

    TunnelConfig::query()->delete();

    Livewire::test(TunnelManager::class)
        ->set('newSubdomain', 'mydevice')
        ->set('subdomainAvailable', true)
        ->call('provisionTunnel')
        ->assertSet('isProvisioning', false)
        ->assertSee('Failed to provision tunnel: API connection failed');
});

it('shows error when tunnel starts after provisioning fails', function () {
    $mockCloudApi = Mockery::mock(\App\Services\CloudApiClient::class);
    $mockCloudApi->shouldReceive('provisionTunnel')
        ->once()
        ->andReturn([
            'tunnel_id' => 'test-tunnel-123',
            'tunnel_token' => 'test-token-value',
        ]);

    $this->app->instance(\App\Services\CloudApiClient::class, $mockCloudApi);

    $this->tunnelMock->shouldReceive('getStatus')->andReturn([
        'installed' => true,
        'running' => false,
        'configured' => false,
    ]);
    $this->tunnelMock->shouldReceive('start')->once()->andReturn('Port already in use');
    $this->tunnelMock->shouldReceive('cleanup')->once();

    TunnelConfig::query()->delete();

    Livewire::test(TunnelManager::class)
        ->set('newSubdomain', 'mydevice')
        ->set('subdomainAvailable', true)
        ->call('provisionTunnel')
        ->assertSet('isProvisioning', false)
        ->assertSee('Tunnel provisioned but failed to start: Port already in use');
});

it('can re-provision existing tunnel', function () {
    $mockCloudApi = Mockery::mock(\App\Services\CloudApiClient::class);
    $mockCloudApi->shouldReceive('provisionTunnel')
        ->once()
        ->andReturn([
            'tunnel_id' => 'new-tunnel-456',
            'tunnel_token' => 'new-token-value',
        ]);

    $this->app->instance(\App\Services\CloudApiClient::class, $mockCloudApi);

    $this->tunnelMock->shouldReceive('getStatus')->andReturn([
        'installed' => true,
        'running' => true,
        'configured' => true,
    ]);
    $this->tunnelMock->shouldReceive('stop')->once()->andReturn(null);
    $this->tunnelMock->shouldReceive('start')->once()->andReturn(null);
    $this->tunnelMock->shouldReceive('isRunning')->andReturn(true);
    $this->tunnelMock->shouldReceive('hasCredentials')->andReturn(true);

    Livewire::test(TunnelManager::class)
        ->call('reprovisionTunnel')
        ->assertSet('isProvisioning', false);

    $config = TunnelConfig::current();
    expect($config->tunnel_id)->toBe('new-tunnel-456')
        ->and($config->verified_at)->toBeNull();
});

it('shows error when re-provisioning without existing config', function () {
    $this->tunnelMock->shouldReceive('getStatus')->andReturn([
        'installed' => true,
        'running' => false,
        'configured' => false,
    ]);

    TunnelConfig::query()->delete();

    Livewire::test(TunnelManager::class)
        ->call('reprovisionTunnel')
        ->assertSet('isProvisioning', false)
        ->assertSee('No tunnel configuration found. Use the setup form instead.');
});

it('validates subdomain format', function () {
    $this->tunnelMock->shouldReceive('getStatus')->andReturn([
        'installed' => true,
        'running' => false,
        'configured' => false,
    ]);

    TunnelConfig::query()->delete();

    Livewire::test(TunnelManager::class)
        ->set('newSubdomain', 'UPPERCASE')
        ->call('checkAvailability')
        ->assertHasErrors(['newSubdomain' => 'regex']);
});

it('validates subdomain must start with a letter', function () {
    $this->tunnelMock->shouldReceive('getStatus')->andReturn([
        'installed' => true,
        'running' => false,
        'configured' => false,
    ]);

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

    $this->tunnelMock->shouldReceive('getStatus')->andReturn([
        'installed' => true,
        'running' => false,
        'configured' => true, // hasCredentials returns true for skipped
    ]);
    $this->tunnelMock->shouldReceive('isSkipped')->andReturn(true);

    Livewire::test(TunnelManager::class)
        ->assertSee('Enable Remote Access')
        ->assertSee('Set up Cloudflare Tunnel');
});

it('allows pairing after tunnel was skipped', function () {
    // Start with a skipped config
    TunnelConfig::factory()->skipped()->create();

    $mockCloudApi = Mockery::mock(\App\Services\CloudApiClient::class);
    $mockCloudApi->shouldReceive('checkSubdomainAvailability')
        ->andReturn(true);
    $mockCloudApi->shouldReceive('provisionTunnel')
        ->once()
        ->andReturn([
            'tunnel_id' => 'test-tunnel-123',
            'tunnel_token' => 'test-token-value',
        ]);

    $this->app->instance(\App\Services\CloudApiClient::class, $mockCloudApi);

    $this->tunnelMock->shouldReceive('getStatus')->andReturn([
        'installed' => true,
        'running' => false,
        'configured' => true,
    ]);
    $this->tunnelMock->shouldReceive('start')->once()->andReturn(null);
    $this->tunnelMock->shouldReceive('isRunning')->andReturn(true);
    $this->tunnelMock->shouldReceive('hasCredentials')->andReturn(true);

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
