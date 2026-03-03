<?php

declare(strict_types=1);

use App\Models\TunnelConfig;
use Illuminate\Support\Facades\File;

beforeEach(function () {
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

it('exits silently when no tunnel config exists', function () {
    $this->artisan('device:poll-tunnel-status')
        ->assertSuccessful();
});

it('exits silently when tunnel is not skipped', function () {
    TunnelConfig::factory()->active()->create();

    $this->artisan('device:poll-tunnel-status')
        ->assertSuccessful();
});

it('exits silently when tunnel is skipped but token file does not exist', function () {
    TunnelConfig::factory()->skipped()->create();

    $this->artisan('device:poll-tunnel-status')
        ->assertSuccessful();

    $config = TunnelConfig::current();
    expect($config->isSkipped())->toBeTrue();
    expect($config->status)->toBe('skipped');
});

it('detects tunnel token and updates status when token file appears', function () {
    TunnelConfig::factory()->skipped()->create();

    // Create the token file to simulate tunnel being provisioned
    $tokenPath = storage_path('tunnel/token');
    File::makeDirectory(dirname($tokenPath), 0755, true, true);
    File::put($tokenPath, 'test-tunnel-token');

    $this->artisan('device:poll-tunnel-status')
        ->expectsOutputToContain('Tunnel token detected')
        ->expectsOutputToContain('Tunnel is now available')
        ->assertSuccessful();

    $config = TunnelConfig::current();
    expect($config->isSkipped())->toBeFalse();
    expect($config->status)->toBe('available');
    expect($config->skipped_at)->toBeNull();
});
