<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\QuickTunnel;
use App\Services\Tunnel\QuickTunnelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;

uses(RefreshDatabase::class);

beforeEach(function () {
    Process::fake([
        'docker rm -f*' => Process::result(output: '', errorOutput: '', exitCode: 0),
    ]);
});

// ============================================
// start() - Provisioning/Starting Tunnels
// ============================================

it('creates quick tunnel record when starting', function () {
    Process::fake([
        'docker rm -f*' => Process::result(output: '', errorOutput: '', exitCode: 0),
        'docker run -d*' => Process::result(output: 'abc123def456', errorOutput: '', exitCode: 0),
        'docker logs*' => Process::result(
            output: 'INF Your quick tunnel is available at https://abc123.trycloudflare.com',
            errorOutput: '',
            exitCode: 0,
        ),
    ]);

    $service = new QuickTunnelService;
    $tunnel = $service->start(8080, null);

    expect($tunnel)
        ->toBeInstanceOf(QuickTunnel::class)
        ->project_id->toBeNull()
        ->local_port->toBe(8080)
        ->container_id->toBe('abc123def456')
        ->container_name->toStartWith('vibe-qt-dash-');
});

it('creates project tunnel with correct association', function () {
    $project = Project::factory()->create();

    Process::fake([
        'docker rm -f*' => Process::result(output: '', errorOutput: '', exitCode: 0),
        'docker run -d*' => Process::result(output: 'xyz789uvw123', errorOutput: '', exitCode: 0),
        'docker logs*' => Process::result(
            output: 'INF Your quick tunnel is available at https://project456.trycloudflare.com',
            errorOutput: '',
            exitCode: 0,
        ),
    ]);

    $service = new QuickTunnelService;
    $tunnel = $service->start(3000, $project->id);

    expect($tunnel)
        ->project_id->toBe($project->id)
        ->local_port->toBe(3000)
        ->container_name->toContain("p{$project->id}");
});

it('removes existing dashboard tunnel before starting new one', function () {
    $existingTunnel = QuickTunnel::factory()->running()->dashboard()->create([
        'container_name' => 'vibe-qt-dash-old',
        'container_id' => 'old-container-id',
    ]);

    Process::fake([
        'docker rm -f*' => Process::result(output: '', errorOutput: '', exitCode: 0),
        'docker run -d*' => Process::result(output: 'new-container-id', errorOutput: '', exitCode: 0),
        'docker logs*' => Process::result(
            output: 'INF Your quick tunnel is available at https://new.trycloudflare.com',
            errorOutput: '',
            exitCode: 0,
        ),
    ]);

    $service = new QuickTunnelService;
    $newTunnel = $service->start(8080, null);

    expect(QuickTunnel::find($existingTunnel->id))->toBeNull()
        ->and($newTunnel->container_id)->toBe('new-containe');
});

it('removes existing project tunnel before starting new one', function () {
    $project = Project::factory()->create();
    $existingTunnel = QuickTunnel::factory()->running()->forProject($project)->create([
        'container_name' => 'vibe-qt-p'.$project->id.'-old',
        'container_id' => 'old-container-id',
    ]);

    Process::fake([
        'docker rm -f*' => Process::result(output: '', errorOutput: '', exitCode: 0),
        'docker run -d*' => Process::result(output: 'new-container-id', errorOutput: '', exitCode: 0),
        'docker logs*' => Process::result(
            output: 'INF Your quick tunnel is available at https://new.trycloudflare.com',
            errorOutput: '',
            exitCode: 0,
        ),
    ]);

    $service = new QuickTunnelService;
    $newTunnel = $service->start(3000, $project->id);

    expect(QuickTunnel::find($existingTunnel->id))->toBeNull()
        ->and($newTunnel->container_id)->toBe('new-containe');
});

it('throws exception when docker run fails', function () {
    Process::fake([
        'docker rm -f*' => Process::result(output: '', errorOutput: '', exitCode: 0),
        'docker run -d*' => Process::result(
            output: '',
            errorOutput: 'Error: Unable to find image',
            exitCode: 1,
        ),
    ]);

    $service = new QuickTunnelService;

    expect(fn () => $service->start(8080, null))
        ->toThrow(RuntimeException::class, 'Failed to start quick tunnel container');
});

it('sets running status when url is captured', function () {
    Process::fake([
        'docker rm -f*' => Process::result(output: '', errorOutput: '', exitCode: 0),
        'docker run -d*' => Process::result(output: 'container123', errorOutput: '', exitCode: 0),
        'docker logs*' => Process::result(
            output: 'INF Your quick tunnel is available at https://test.trycloudflare.com',
            errorOutput: '',
            exitCode: 0,
        ),
    ]);

    $service = new QuickTunnelService;
    $tunnel = $service->start(8080, null);

    expect($tunnel->status)->toBe('running')
        ->and($tunnel->tunnel_url)->toBe('https://test.trycloudflare.com');
});

it('captures tunnel url from docker logs', function () {
    Process::fake([
        'docker rm -f*' => Process::result(output: '', errorOutput: '', exitCode: 0),
        'docker run -d*' => Process::result(output: 'container123', errorOutput: '', exitCode: 0),
        'docker logs*' => Process::result(
            output: "2025-01-15T10:30:00Z INF Your quick tunnel is available at https://capt123.trycloudflare.com\n",
            errorOutput: '',
            exitCode: 0,
        ),
    ]);

    $service = new QuickTunnelService;
    $tunnel = $service->start(8080, null);

    expect($tunnel->tunnel_url)->toBe('https://capt123.trycloudflare.com');
});

// ============================================
// startForDashboard() - Dashboard Convenience Method
// ============================================

it('starts dashboard tunnel on configured port', function () {
    config(['vibecodepc.tunnel.device_app_port' => 9000]);

    Process::fake([
        'docker rm -f*' => Process::result(output: '', errorOutput: '', exitCode: 0),
        'docker run -d*' => Process::result(output: 'container123', errorOutput: '', exitCode: 0),
        'docker logs*' => Process::result(
            output: 'INF Your quick tunnel is available at https://dashboard.trycloudflare.com',
            errorOutput: '',
            exitCode: 0,
        ),
    ]);

    $service = new QuickTunnelService;
    $url = $service->startForDashboard();

    expect($url)->toBe('https://dashboard.trycloudflare.com');
});

it('returns null when dashboard tunnel url cannot be captured', function () {
    config(['vibecodepc.tunnel.device_app_port' => 8080]);

    Process::fake([
        'docker rm -f*' => Process::result(output: '', errorOutput: '', exitCode: 0),
        'docker run -d*' => Process::result(output: 'container123', errorOutput: '', exitCode: 0),
        'docker logs*' => Process::result(output: 'No URL here', errorOutput: '', exitCode: 0),
    ]);

    $service = new QuickTunnelService;
    $url = $service->startForDashboard();

    expect($url)->toBeNull();
});

// ============================================
// isHealthy() - Status/Health Check
// ============================================

it('returns true when container is running', function () {
    $tunnel = QuickTunnel::factory()->running()->create([
        'container_name' => 'vibe-qt-dash-abc123',
    ]);

    Process::fake([
        'docker inspect*' => Process::result(output: 'true', errorOutput: '', exitCode: 0),
    ]);

    $service = new QuickTunnelService;

    expect($service->isHealthy($tunnel))->toBeTrue();
});

it('returns false when container is not running', function () {
    $tunnel = QuickTunnel::factory()->running()->create([
        'container_name' => 'vibe-qt-dash-abc123',
    ]);

    Process::fake([
        'docker inspect*' => Process::result(output: 'false', errorOutput: '', exitCode: 0),
    ]);

    $service = new QuickTunnelService;

    expect($service->isHealthy($tunnel))->toBeFalse();
});

it('returns false when container inspection fails', function () {
    $tunnel = QuickTunnel::factory()->running()->create([
        'container_name' => 'vibe-qt-dash-abc123',
    ]);

    Process::fake([
        'docker inspect*' => Process::result(output: '', errorOutput: 'No such container', exitCode: 1),
    ]);

    $service = new QuickTunnelService;

    expect($service->isHealthy($tunnel))->toBeFalse();
});

it('returns false when tunnel status is stopped', function () {
    $tunnel = QuickTunnel::factory()->stopped()->create();
    $service = new QuickTunnelService;

    expect($service->isHealthy($tunnel))->toBeFalse();
});

it('returns false when tunnel status is error', function () {
    $tunnel = QuickTunnel::factory()->error()->create();
    $service = new QuickTunnelService;

    expect($service->isHealthy($tunnel))->toBeFalse();
});

it('returns true when tunnel status is starting', function () {
    $tunnel = QuickTunnel::factory()->starting()->create([
        'container_name' => 'vibe-qt-dash-starting',
    ]);

    Process::fake([
        'docker inspect*' => Process::result(output: 'true', errorOutput: '', exitCode: 0),
    ]);

    $service = new QuickTunnelService;

    expect($service->isHealthy($tunnel))->toBeTrue();
});

// ============================================
// stop() - Stopping Tunnels
// ============================================

it('stops tunnel and marks as stopped', function () {
    $tunnel = QuickTunnel::factory()->running()->create([
        'container_name' => 'vibe-qt-dash-abc123',
    ]);

    Process::fake([
        'docker rm -f*' => Process::result(output: '', errorOutput: '', exitCode: 0),
    ]);

    $service = new QuickTunnelService;
    $service->stop($tunnel);

    $tunnel->refresh();

    expect($tunnel->status)->toBe('stopped')
        ->and($tunnel->stopped_at)->not->toBeNull();
});

// ============================================
// cleanup() - Complete Removal
// ============================================

it('removes container and deletes record on cleanup', function () {
    $tunnel = QuickTunnel::factory()->running()->create([
        'container_name' => 'vibe-qt-dash-abc123',
    ]);
    $tunnelId = $tunnel->id;

    Process::fake([
        'docker rm -f*' => Process::result(output: '', errorOutput: '', exitCode: 0),
    ]);

    $service = new QuickTunnelService;
    $service->cleanup($tunnel);

    expect(QuickTunnel::find($tunnelId))->toBeNull();
});

// ============================================
// refreshUrl() - URL Extraction from Logs
// ============================================

it('returns cached tunnel url without checking logs', function () {
    $tunnel = QuickTunnel::factory()->running()->create([
        'container_name' => 'vibe-qt-dash-abc123',
        'tunnel_url' => 'https://existing.trycloudflare.com',
    ]);

    $service = new QuickTunnelService;
    $url = $service->refreshUrl($tunnel);

    expect($url)->toBe('https://existing.trycloudflare.com');
});

it('extracts url from container logs when not cached', function () {
    $tunnel = QuickTunnel::factory()->starting()->create([
        'container_name' => 'vibe-qt-dash-abc123',
        'tunnel_url' => null,
    ]);

    Process::fake([
        'docker logs*' => Process::result(
            output: 'INF Your quick tunnel is available at https://extracted.trycloudflare.com',
            errorOutput: '',
            exitCode: 0,
        ),
    ]);

    $service = new QuickTunnelService;
    $url = $service->refreshUrl($tunnel);

    expect($url)->toBe('https://extracted.trycloudflare.com');

    $tunnel->refresh();

    expect($tunnel->tunnel_url)->toBe('https://extracted.trycloudflare.com')
        ->and($tunnel->status)->toBe('running');
});

it('returns null when url pattern not found in logs', function () {
    $tunnel = QuickTunnel::factory()->starting()->create([
        'container_name' => 'vibe-qt-dash-abc123',
        'tunnel_url' => null,
    ]);

    Process::fake([
        'docker logs*' => Process::result(output: 'No URL in these logs', errorOutput: '', exitCode: 0),
    ]);

    $service = new QuickTunnelService;
    $url = $service->refreshUrl($tunnel);

    expect($url)->toBeNull();

    $tunnel->refresh();
    expect($tunnel->tunnel_url)->toBeNull();
});

it('returns null when docker logs command fails', function () {
    $tunnel = QuickTunnel::factory()->starting()->create([
        'container_name' => 'vibe-qt-dash-abc123',
        'tunnel_url' => null,
    ]);

    Process::fake([
        'docker logs*' => Process::result(output: '', errorOutput: 'No such container', exitCode: 1),
    ]);

    $service = new QuickTunnelService;
    $url = $service->refreshUrl($tunnel);

    expect($url)->toBeNull();
});

// ============================================
// URL Pattern Edge Cases
// ============================================

it('extracts url with hyphenated subdomain', function () {
    $tunnel = QuickTunnel::factory()->starting()->create([
        'container_name' => 'vibe-qt-dash-abc123',
        'tunnel_url' => null,
    ]);

    Process::fake([
        'docker logs*' => Process::result(
            output: 'INF Your quick tunnel is available at https://my-tunnel-123.trycloudflare.com',
            errorOutput: '',
            exitCode: 0,
        ),
    ]);

    $service = new QuickTunnelService;
    $url = $service->refreshUrl($tunnel);

    expect($url)->toBe('https://my-tunnel-123.trycloudflare.com');
});

it('extracts url with numeric subdomain', function () {
    $tunnel = QuickTunnel::factory()->starting()->create([
        'container_name' => 'vibe-qt-dash-abc123',
        'tunnel_url' => null,
    ]);

    Process::fake([
        'docker logs*' => Process::result(
            output: 'INF Your quick tunnel is available at https://tunnel123.trycloudflare.com',
            errorOutput: '',
            exitCode: 0,
        ),
    ]);

    $service = new QuickTunnelService;
    $url = $service->refreshUrl($tunnel);

    expect($url)->toBe('https://tunnel123.trycloudflare.com');
});
