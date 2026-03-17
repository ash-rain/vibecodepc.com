<?php

declare(strict_types=1);

use App\Jobs\PollTunnelUrlJob;
use App\Models\QuickTunnel;
use App\Services\Tunnel\QuickTunnelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Process;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake([
        \App\Events\QuickTunnelUrlDiscovered::class,
    ]);
});

// ============================================
// URL Discovery Success
// ============================================

it('discovers tunnel url on first poll attempt', function () {
    $tunnel = QuickTunnel::factory()->starting()->create([
        'container_name' => 'vibe-qt-dash-abc123',
        'tunnel_url' => null,
    ]);

    Process::fake([
        'docker logs*' => Process::result(
            output: 'INF Your quick tunnel is available at https://discovered.trycloudflare.com',
            errorOutput: '',
            exitCode: 0,
        ),
    ]);

    $job = new PollTunnelUrlJob($tunnel, maxWaitSeconds: 10, pollIntervalSeconds: 1);
    $job->handle(app(QuickTunnelService::class));

    $tunnel->refresh();
    expect($tunnel->tunnel_url)->toBe('https://discovered.trycloudflare.com')
        ->and($tunnel->status)->toBe('running');

    Event::assertDispatched(\App\Events\QuickTunnelUrlDiscovered::class, function ($event) {
        return $event->url === 'https://discovered.trycloudflare.com';
    });
});

it('polls until url is found within timeout', function () {
    $tunnel = QuickTunnel::factory()->starting()->create([
        'container_name' => 'vibe-qt-dash-abc123',
        'tunnel_url' => null,
    ]);

    $callCount = 0;
    Process::fake(function ($command) use (&$callCount) {
        $callCount++;

        // URL available on 3rd attempt
        if ($callCount >= 3) {
            return Process::result(
                output: 'INF Your quick tunnel is available at https://found.trycloudflare.com',
                errorOutput: '',
                exitCode: 0,
            );
        }

        return Process::result(
            output: 'Still starting...',
            errorOutput: '',
            exitCode: 0,
        );
    });

    $job = new PollTunnelUrlJob($tunnel, maxWaitSeconds: 10, pollIntervalSeconds: 1);
    $job->handle(app(QuickTunnelService::class));

    $tunnel->refresh();
    expect($tunnel->tunnel_url)->toBe('https://found.trycloudflare.com');
    expect($callCount)->toBe(3);
});

// ============================================
// Timeout and Edge Cases
// ============================================

it('gives up after max wait seconds if url not found', function () {
    $tunnel = QuickTunnel::factory()->starting()->create([
        'container_name' => 'vibe-qt-dash-abc123',
        'tunnel_url' => null,
    ]);

    Process::fake([
        'docker logs*' => Process::result(
            output: 'No URL here yet',
            errorOutput: '',
            exitCode: 0,
        ),
    ]);

    $job = new PollTunnelUrlJob($tunnel, maxWaitSeconds: 3, pollIntervalSeconds: 1);
    $job->handle(app(QuickTunnelService::class));

    $tunnel->refresh();
    expect($tunnel->tunnel_url)->toBeNull()
        ->and($tunnel->status)->toBe('starting');

    Event::assertNotDispatched(\App\Events\QuickTunnelUrlDiscovered::class);
});

it('stops polling when tunnel is no longer active', function () {
    $tunnel = QuickTunnel::factory()->starting()->create([
        'container_name' => 'vibe-qt-dash-abc123',
        'tunnel_url' => null,
    ]);

    Process::fake([
        'docker logs*' => Process::result(
            output: 'No URL',
            errorOutput: '',
            exitCode: 0,
        ),
    ]);

    $callCount = 0;
    Process::fake(function ($command) use (&$callCount, $tunnel) {
        $callCount++;

        // After first poll, stop the tunnel
        if ($callCount === 1) {
            $tunnel->update(['status' => 'stopped']);
        }

        return Process::result(output: 'No URL', errorOutput: '', exitCode: 0);
    });

    $job = new PollTunnelUrlJob($tunnel, maxWaitSeconds: 10, pollIntervalSeconds: 1);
    $job->handle(app(QuickTunnelService::class));

    expect($callCount)->toBe(1);
});

it('stops polling when tunnel already has url', function () {
    $tunnel = QuickTunnel::factory()->running()->create([
        'container_name' => 'vibe-qt-dash-abc123',
        'tunnel_url' => 'https://already-set.trycloudflare.com',
    ]);

    Process::fake([
        'docker logs*' => Process::result(
            output: 'INF Your quick tunnel is available at https://different.trycloudflare.com',
            errorOutput: '',
            exitCode: 0,
        ),
    ]);

    $job = new PollTunnelUrlJob($tunnel, maxWaitSeconds: 10, pollIntervalSeconds: 1);
    $job->handle(app(QuickTunnelService::class));

    // URL should remain unchanged
    $tunnel->refresh();
    expect($tunnel->tunnel_url)->toBe('https://already-set.trycloudflare.com');

    // Should not have polled logs
    Process::assertNotRan('docker logs*');
});

// ============================================
// Uniqueness
// ============================================

it('has unique id based on tunnel', function () {
    $tunnel = QuickTunnel::factory()->starting()->create();

    $job = new PollTunnelUrlJob($tunnel);

    expect($job->uniqueId())->toBe((string) $tunnel->id);
});

// ============================================
// Error Handling
// ============================================

it('handles docker logs failure gracefully', function () {
    $tunnel = QuickTunnel::factory()->starting()->create([
        'container_name' => 'vibe-qt-dash-abc123',
        'tunnel_url' => null,
    ]);

    Process::fake([
        'docker logs*' => Process::result(
            output: '',
            errorOutput: 'No such container',
            exitCode: 1,
        ),
    ]);

    $job = new PollTunnelUrlJob($tunnel, maxWaitSeconds: 3, pollIntervalSeconds: 1);

    // Should not throw, just poll until timeout
    expect(fn () => $job->handle(app(QuickTunnelService::class)))->not->toThrow(\Throwable::class);

    $tunnel->refresh();
    expect($tunnel->tunnel_url)->toBeNull();
});

// ============================================
// Configuration
// ============================================

it('respects custom poll intervals', function () {
    $tunnel = QuickTunnel::factory()->starting()->create([
        'container_name' => 'vibe-qt-dash-abc123',
        'tunnel_url' => null,
    ]);

    Process::fake([
        'docker logs*' => Process::result(output: 'No URL', errorOutput: '', exitCode: 0),
    ]);

    $job = new PollTunnelUrlJob($tunnel, maxWaitSeconds: 6, pollIntervalSeconds: 3);

    expect($job->maxWaitSeconds)->toBe(6)
        ->and($job->pollIntervalSeconds)->toBe(3);
});
