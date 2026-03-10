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

it('handles edge case when config is deleted during execution', function () {
    TunnelConfig::factory()->skipped()->create();

    // Create the token file
    $tokenPath = storage_path('tunnel/token');
    File::makeDirectory(dirname($tokenPath), 0755, true, true);
    File::put($tokenPath, 'test-tunnel-token');

    // Delete the config after setup to simulate race condition
    TunnelConfig::query()->delete();

    // Command should handle gracefully
    $this->artisan('device:poll-tunnel-status')
        ->assertSuccessful();
});

it('handles edge case when token file is empty', function () {
    TunnelConfig::factory()->skipped()->create();

    // Create an empty token file - should NOT trigger detection since isRunning() checks filesize > 0
    $tokenPath = storage_path('tunnel/token');
    File::makeDirectory(dirname($tokenPath), 0755, true, true);
    File::put($tokenPath, '');

    // Command should exit silently because empty file doesn't count as "running"
    $this->artisan('device:poll-tunnel-status')
        ->doesntExpectOutputToContain('Tunnel token detected')
        ->assertSuccessful();

    $config = TunnelConfig::current();
    expect($config->status)->toBe('skipped');
});

it('displays correct output when tunnel is already available', function () {
    TunnelConfig::factory()->available()->create();

    // Create the token file
    $tokenPath = storage_path('tunnel/token');
    File::makeDirectory(dirname($tokenPath), 0755, true, true);
    File::put($tokenPath, 'test-tunnel-token');

    $this->artisan('device:poll-tunnel-status')
        ->doesntExpectOutputToContain('Tunnel token detected')
        ->assertSuccessful();
});

it('handles missing tunnel config gracefully', function () {
    // No tunnel config created
    $this->artisan('device:poll-tunnel-status')
        ->assertSuccessful()
        ->doesntExpectOutputToContain('Tunnel token detected');
});

it('validates command signature and description', function () {
    $this->artisan('device:poll-tunnel-status --help')
        ->assertSuccessful()
        ->expectsOutputToContain('device:poll-tunnel-status')
        ->expectsOutputToContain('Check if tunnel token file appeared and update tunnel status');
});

// Edge case tests for timeout handling, invalid responses, and error scenarios

it('handles error returned from pollStatus service method', function () {
    TunnelConfig::factory()->skipped()->create();

    // Create the token file
    $tokenPath = storage_path('tunnel/token');
    File::makeDirectory(dirname($tokenPath), 0755, true, true);
    File::put($tokenPath, 'test-tunnel-token');

    // Mock the TunnelService to return an error
    $mockService = Mockery::mock(\App\Services\Tunnel\TunnelService::class);
    $mockService->shouldReceive('pollStatus')
        ->once()
        ->andReturn([
            'detected' => false,
            'message' => null,
            'error' => 'Database connection timeout while updating tunnel status',
        ]);

    $this->app->instance(\App\Services\Tunnel\TunnelService::class, $mockService);

    $this->artisan('device:poll-tunnel-status')
        ->expectsOutputToContain('Database connection timeout while updating tunnel status')
        ->assertFailed();
});

it('handles exception thrown by pollStatus service method', function () {
    TunnelConfig::factory()->skipped()->create();

    // Create the token file
    $tokenPath = storage_path('tunnel/token');
    File::makeDirectory(dirname($tokenPath), 0755, true, true);
    File::put($tokenPath, 'test-tunnel-token');

    // Mock the TunnelService to throw an exception
    // Note: The command doesn't catch exceptions, so they bubble up
    $mockService = Mockery::mock(\App\Services\Tunnel\TunnelService::class);
    $mockService->shouldReceive('pollStatus')
        ->once()
        ->andThrow(new \Exception('Service timeout: pollStatus took too long to respond'));

    $this->app->instance(\App\Services\Tunnel\TunnelService::class, $mockService);

    // Expect the exception to bubble up since the command doesn't catch it
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Service timeout: pollStatus took too long to respond');

    $this->artisan('device:poll-tunnel-status')->run();
});

it('handles database exception during markAsAvailable', function () {
    TunnelConfig::factory()->skipped()->create();

    // Create the token file
    $tokenPath = storage_path('tunnel/token');
    File::makeDirectory(dirname($tokenPath), 0755, true, true);
    File::put($tokenPath, 'test-tunnel-token');

    // Mock the TunnelService to simulate error from markAsAvailable
    $mockService = Mockery::mock(\App\Services\Tunnel\TunnelService::class);
    $mockService->shouldReceive('pollStatus')
        ->once()
        ->andReturn([
            'detected' => false,
            'message' => null,
            'error' => 'Failed to update tunnel status: Database deadlock detected',
        ]);

    $this->app->instance(\App\Services\Tunnel\TunnelService::class, $mockService);

    $this->artisan('device:poll-tunnel-status')
        ->expectsOutputToContain('Database deadlock detected')
        ->assertFailed();
});

it('handles timeout simulation with slow pollStatus response', function () {
    TunnelConfig::factory()->skipped()->create();

    // Create the token file
    $tokenPath = storage_path('tunnel/token');
    File::makeDirectory(dirname($tokenPath), 0755, true, true);
    File::put($tokenPath, 'test-tunnel-token');

    // Mock the TunnelService to simulate a timeout scenario
    $mockService = Mockery::mock(\App\Services\Tunnel\TunnelService::class);
    $mockService->shouldReceive('pollStatus')
        ->once()
        ->andReturn([
            'detected' => false,
            'message' => null,
            'error' => 'Operation timed out: Token file check exceeded 30 seconds',
        ]);

    $this->app->instance(\App\Services\Tunnel\TunnelService::class, $mockService);

    $this->artisan('device:poll-tunnel-status')
        ->expectsOutputToContain('Operation timed out')
        ->assertFailed();
});

it('handles invalid response structure from pollStatus', function () {
    TunnelConfig::factory()->skipped()->create();

    // Create the token file
    $tokenPath = storage_path('tunnel/token');
    File::makeDirectory(dirname($tokenPath), 0755, true, true);
    File::put($tokenPath, 'test-tunnel-token');

    // Mock the TunnelService to return response with null message
    // This tests that the command handles missing/null message gracefully
    $mockService = Mockery::mock(\App\Services\Tunnel\TunnelService::class);
    $mockService->shouldReceive('pollStatus')
        ->once()
        ->andReturn([
            'detected' => true,
            'message' => null,
            'error' => null,
        ]);

    $this->app->instance(\App\Services\Tunnel\TunnelService::class, $mockService);

    // Command should handle null message gracefully - it will try to output null
    $this->artisan('device:poll-tunnel-status')
        ->expectsOutputToContain('Tunnel token detected')
        ->assertSuccessful();
});

it('handles race condition when token file is deleted during processing', function () {
    TunnelConfig::factory()->skipped()->create();

    // Create the token file initially
    $tokenPath = storage_path('tunnel/token');
    File::makeDirectory(dirname($tokenPath), 0755, true, true);
    File::put($tokenPath, 'test-tunnel-token');

    // Mock the TunnelService to simulate race condition
    $mockService = Mockery::mock(\App\Services\Tunnel\TunnelService::class);
    $mockService->shouldReceive('pollStatus')
        ->once()
        ->andReturn([
            'detected' => false,
            'message' => null,
            'error' => 'Token file was removed during status check',
        ]);

    $this->app->instance(\App\Services\Tunnel\TunnelService::class, $mockService);

    $this->artisan('device:poll-tunnel-status')
        ->expectsOutputToContain('Token file was removed during status check')
        ->assertFailed();
});

it('handles retry exhaustion scenario', function () {
    TunnelConfig::factory()->skipped()->create();

    // Create the token file
    $tokenPath = storage_path('tunnel/token');
    File::makeDirectory(dirname($tokenPath), 0755, true, true);
    File::put($tokenPath, 'test-tunnel-token');

    // Mock the TunnelService to return retry exhaustion error
    $mockService = Mockery::mock(\App\Services\Tunnel\TunnelService::class);
    $mockService->shouldReceive('pollStatus')
        ->once()
        ->andReturn([
            'detected' => false,
            'message' => null,
            'error' => 'Max retry attempts exceeded: Unable to update tunnel status after 3 attempts',
        ]);

    $this->app->instance(\App\Services\Tunnel\TunnelService::class, $mockService);

    $this->artisan('device:poll-tunnel-status')
        ->expectsOutputToContain('Max retry attempts exceeded')
        ->assertFailed();
});

it('handles database connection lost during status update', function () {
    TunnelConfig::factory()->skipped()->create();

    // Create the token file
    $tokenPath = storage_path('tunnel/token');
    File::makeDirectory(dirname($tokenPath), 0755, true, true);
    File::put($tokenPath, 'test-tunnel-token');

    // Mock the TunnelService to return connection lost error
    $mockService = Mockery::mock(\App\Services\Tunnel\TunnelService::class);
    $mockService->shouldReceive('pollStatus')
        ->once()
        ->andReturn([
            'detected' => false,
            'message' => null,
            'error' => 'Database connection lost: MySQL server has gone away',
        ]);

    $this->app->instance(\App\Services\Tunnel\TunnelService::class, $mockService);

    $this->artisan('device:poll-tunnel-status')
        ->expectsOutputToContain('Database connection lost')
        ->assertFailed();
});

it('handles permission denied when reading token file', function () {
    TunnelConfig::factory()->skipped()->create();

    // Create the token file
    $tokenPath = storage_path('tunnel/token');
    File::makeDirectory(dirname($tokenPath), 0755, true, true);
    File::put($tokenPath, 'test-tunnel-token');

    // Mock the TunnelService to return permission error
    $mockService = Mockery::mock(\App\Services\Tunnel\TunnelService::class);
    $mockService->shouldReceive('pollStatus')
        ->once()
        ->andReturn([
            'detected' => false,
            'message' => null,
            'error' => 'Permission denied: Unable to read token file',
        ]);

    $this->app->instance(\App\Services\Tunnel\TunnelService::class, $mockService);

    $this->artisan('device:poll-tunnel-status')
        ->expectsOutputToContain('Permission denied')
        ->assertFailed();
});

it('handles corrupt token file with invalid content', function () {
    TunnelConfig::factory()->skipped()->create();

    // Create the token file
    $tokenPath = storage_path('tunnel/token');
    File::makeDirectory(dirname($tokenPath), 0755, true, true);
    File::put($tokenPath, 'test-tunnel-token');

    // Mock the TunnelService to return corrupt token error
    $mockService = Mockery::mock(\App\Services\Tunnel\TunnelService::class);
    $mockService->shouldReceive('pollStatus')
        ->once()
        ->andReturn([
            'detected' => false,
            'message' => null,
            'error' => 'Invalid token format: Token file contains malformed data',
        ]);

    $this->app->instance(\App\Services\Tunnel\TunnelService::class, $mockService);

    $this->artisan('device:poll-tunnel-status')
        ->expectsOutputToContain('Invalid token format')
        ->assertFailed();
});
