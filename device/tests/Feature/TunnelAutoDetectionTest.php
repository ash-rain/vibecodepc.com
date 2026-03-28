<?php

declare(strict_types=1);

use App\Livewire\Dashboard\Overview;
use App\Models\CloudCredential;
use App\Models\TunnelConfig;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;

beforeEach(function () {
    Process::fake();

    // Set up cloud credential like OverviewTest does
    CloudCredential::create([
        'pairing_token_encrypted' => 'test-token',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => true,
        'paired_at' => now(),
    ]);

    // Clean up any existing token file
    $tokenPath = storage_path('tunnel/token');
    if (File::exists($tokenPath)) {
        File::delete($tokenPath);
    }
});

afterEach(function () {
    // Clean up token file after tests
    $tokenPath = storage_path('tunnel/token');
    if (File::exists($tokenPath)) {
        File::delete($tokenPath);
    }
});

it('shows tunnel available banner when tunnel is available after being skipped', function () {
    // Use unpaired credential for tunnel auto-detection UI
    CloudCredential::query()->delete();
    CloudCredential::create([
        'pairing_token_encrypted' => 'test-token',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => false,
        'paired_at' => null,
    ]);

    TunnelConfig::factory()->available()->create();

    Livewire::test(Overview::class)
        ->assertSee('Tunnel detected!')
        ->assertSee('Complete Pairing')
        ->assertSee('Available');
});

it('shows tunnel status as Available when tunnel was skipped but token exists', function () {
    // Use unpaired credential for tunnel auto-detection UI
    CloudCredential::query()->delete();
    CloudCredential::create([
        'pairing_token_encrypted' => 'test-token',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => false,
        'paired_at' => null,
    ]);

    TunnelConfig::factory()->available()->create();

    Livewire::test(Overview::class)
        ->assertSee('Available');
});

it('polls for tunnel status when not paired and not available', function () {
    // Use unpaired credential for the not-paired UI
    CloudCredential::query()->delete();
    CloudCredential::create([
        'pairing_token_encrypted' => 'test-token',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => false,
        'paired_at' => null,
    ]);

    TunnelConfig::factory()->skipped()->create();

    $component = Livewire::test(Overview::class)
        ->assertSee('Device not paired')
        ->assertSee('limited to local network');

    // The component should have wire:poll directive
    $html = $component->html();
    expect($html)->toContain('wire:poll.30s="poll"');
});

it('does not poll when tunnel is already paired', function () {
    TunnelConfig::factory()->verified()->create();

    $component = Livewire::test(Overview::class);

    $html = $component->html();
    expect($html)->not->toContain('wire:poll.30s="poll"');
});

it('dispatches event when tunnel becomes available during poll', function () {
    // Create skipped config
    $config = TunnelConfig::factory()->skipped()->create();

    // Initially no token file
    $component = Livewire::test(Overview::class);

    // Now create the token file to simulate tunnel becoming available
    $tokenPath = storage_path('tunnel/token');
    File::makeDirectory(dirname($tokenPath), 0755, true, true);
    File::put($tokenPath, 'test-token');

    // Update config to available state (as PollTunnelStatus command would do)
    $config->refresh();
    $config->markAsAvailable();

    // Trigger poll - should dispatch event because tunnelAvailable changed from false to true
    $component->call('poll');

    // Should dispatch tunnel-available event
    $component->assertDispatched('tunnel-available');
});

it('updates isPaired status correctly when tunnel is verified', function () {
    TunnelConfig::factory()->verified()->create();

    Livewire::test(Overview::class)
        ->assertDontSee('Device not paired')
        ->assertDontSee('Pair your device');
});

it('shows Offline badge when tunnel is skipped and no token exists', function () {
    TunnelConfig::factory()->skipped()->create();

    Livewire::test(Overview::class)
        ->assertSee('Offline');
});

it('shows Available badge when tunnel was skipped but now available', function () {
    // Use unpaired credential for tunnel auto-detection UI
    CloudCredential::query()->delete();
    CloudCredential::create([
        'pairing_token_encrypted' => 'test-token',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => false,
        'paired_at' => null,
    ]);

    TunnelConfig::factory()->available()->create();

    Livewire::test(Overview::class)
        ->assertSee('Available');
});

it('shows Online badge when tunnel is verified and running', function () {
    // Create token file to simulate running tunnel
    $tokenPath = storage_path('tunnel/token');
    File::makeDirectory(dirname($tokenPath), 0755, true, true);
    File::put($tokenPath, 'test-token');

    TunnelConfig::factory()->verified()->create([
        'tunnel_token_encrypted' => encrypt('test-token'),
    ]);

    Livewire::test(Overview::class)
        ->assertSee('Online');
});
