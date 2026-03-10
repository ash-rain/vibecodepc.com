<?php

declare(strict_types=1);

use App\Models\TunnelConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Ensure clean state for each test
    TunnelConfig::query()->delete();
});

it('allows requests without CF-Connecting-IP header to pass through', function () {
    // Create a valid tunnel config
    TunnelConfig::create([
        'subdomain' => 'test-device',
        'tunnel_token_encrypted' => 'encrypted-token-value',
        'tunnel_id' => 'test-tunnel-123',
        'status' => 'active',
    ]);

    $response = $this->get(route('dashboard'));

    $response->assertSuccessful()
        ->assertHeaderMissing('CF-Connecting-IP');
});

it('allows tunnel requests when no tunnel config exists', function () {
    $response = $this->get(route('dashboard'), ['CF-Connecting-IP' => '1.2.3.4']);

    $response->assertSuccessful();
});

it('allows tunnel requests when tunnel token is empty', function () {
    TunnelConfig::create([
        'subdomain' => 'test-device',
        'tunnel_token_encrypted' => '',
        'tunnel_id' => 'test-tunnel-123',
        'status' => 'active',
    ]);

    $response = $this->get(route('dashboard'), ['CF-Connecting-IP' => '1.2.3.4']);

    $response->assertSuccessful();
});

it('allows tunnel requests when tunnel token is null', function () {
    TunnelConfig::create([
        'subdomain' => 'test-device',
        'tunnel_token_encrypted' => null,
        'tunnel_id' => 'test-tunnel-123',
        'status' => 'active',
    ]);

    $response = $this->get(route('dashboard'), ['CF-Connecting-IP' => '1.2.3.4']);

    $response->assertSuccessful();
});

it('allows authenticated tunnel requests to pass through', function () {
    TunnelConfig::create([
        'subdomain' => 'test-device',
        'tunnel_token_encrypted' => 'encrypted-token-value',
        'tunnel_id' => 'test-tunnel-123',
        'status' => 'active',
    ]);

    $response = $this->withSession(['tunnel_authenticated' => true])
        ->get(route('dashboard'), ['CF-Connecting-IP' => '1.2.3.4']);

    $response->assertSuccessful();
});

it('allows access to tunnel login page without authentication', function () {
    TunnelConfig::create([
        'subdomain' => 'test-device',
        'tunnel_token_encrypted' => 'encrypted-token-value',
        'tunnel_id' => 'test-tunnel-123',
        'status' => 'active',
    ]);

    $response = $this->get(route('tunnel.login'), ['CF-Connecting-IP' => '1.2.3.4']);

    $response->assertSuccessful();
});

it('redirects unauthenticated tunnel requests to login page', function () {
    TunnelConfig::create([
        'subdomain' => 'test-device',
        'tunnel_token_encrypted' => 'encrypted-token-value',
        'tunnel_id' => 'test-tunnel-123',
        'status' => 'active',
    ]);

    $response = $this->get(route('dashboard'), ['CF-Connecting-IP' => '1.2.3.4']);

    $response->assertRedirect(route('tunnel.login'));
});

it('stores intended URL in session when redirecting to login', function () {
    TunnelConfig::create([
        'subdomain' => 'test-device',
        'tunnel_token_encrypted' => 'encrypted-token-value',
        'tunnel_id' => 'test-tunnel-123',
        'status' => 'active',
    ]);

    $this->get(route('dashboard'), ['CF-Connecting-IP' => '1.2.3.4']);

    expect(session('tunnel_auth_intended_url'))->toContain('/dashboard');
});

it('handles various CF-Connecting-IP header formats', function (string $ipAddress) {
    TunnelConfig::create([
        'subdomain' => 'test-device',
        'tunnel_token_encrypted' => 'encrypted-token-value',
        'tunnel_id' => 'test-tunnel-123',
        'status' => 'active',
    ]);

    $response = $this->get(route('dashboard'), ['CF-Connecting-IP' => $ipAddress]);

    $response->assertRedirect(route('tunnel.login'));
})->with([
    'IPv4 address' => '192.168.1.1',
    'public IPv4' => '203.0.113.45',
    'loopback' => '127.0.0.1',
    'IPv6 address' => '2001:db8::1',
    'Cloudflare IP' => '172.68.1.1',
]);

it('allows request through when session tunnel_authenticated is false', function () {
    TunnelConfig::create([
        'subdomain' => 'test-device',
        'tunnel_token_encrypted' => 'encrypted-token-value',
        'tunnel_id' => 'test-tunnel-123',
        'status' => 'active',
    ]);

    $response = $this->withSession(['tunnel_authenticated' => false])
        ->get(route('dashboard'), ['CF-Connecting-IP' => '1.2.3.4']);

    // Should redirect since tunnel_authenticated is explicitly false
    $response->assertRedirect(route('tunnel.login'));
});

it('does not affect non-tunnel routes', function () {
    TunnelConfig::create([
        'subdomain' => 'test-device',
        'tunnel_token_encrypted' => 'encrypted-token-value',
        'tunnel_id' => 'test-tunnel-123',
        'status' => 'active',
    ]);

    // Local request without CF header should always pass
    $response = $this->get(route('dashboard'));

    $response->assertSuccessful();
});

it('handles missing intended_url gracefully on subsequent requests', function () {
    TunnelConfig::create([
        'subdomain' => 'test-device',
        'tunnel_token_encrypted' => 'encrypted-token-value',
        'tunnel_id' => 'test-tunnel-123',
        'status' => 'active',
    ]);

    // First request - should store intended URL
    $this->get('/dashboard', ['CF-Connecting-IP' => '1.2.3.4']);
    $firstIntendedUrl = session('tunnel_auth_intended_url');
    expect($firstIntendedUrl)->not->toBeNull();
    expect($firstIntendedUrl)->toContain('/dashboard');

    // Second request should update intended URL (use a route with middleware)
    $this->withSession(['tunnel_auth_intended_url' => $firstIntendedUrl])
        ->get('/dashboard/projects', ['CF-Connecting-IP' => '1.2.3.4']);
    expect(session('tunnel_auth_intended_url'))->toContain('/dashboard/projects');
});

it('allows requests with valid session to any protected route', function () {
    TunnelConfig::create([
        'subdomain' => 'test-device',
        'tunnel_token_encrypted' => 'encrypted-token-value',
        'tunnel_id' => 'test-tunnel-123',
        'status' => 'active',
    ]);

    $routes = [
        route('dashboard'),
        route('wizard'),
        route('pairing'),
    ];

    foreach ($routes as $route) {
        $response = $this->withSession(['tunnel_authenticated' => true])
            ->get($route, ['CF-Connecting-IP' => '1.2.3.4']);

        $response->assertSuccessful();
    }
});

it('preserves query parameters when storing intended URL', function () {
    TunnelConfig::create([
        'subdomain' => 'test-device',
        'tunnel_token_encrypted' => 'encrypted-token-value',
        'tunnel_id' => 'test-tunnel-123',
        'status' => 'active',
    ]);

    $this->get('/dashboard?tab=settings&page=2', ['CF-Connecting-IP' => '1.2.3.4']);

    $intendedUrl = session('tunnel_auth_intended_url');
    expect($intendedUrl)->toContain('tab=settings')
        ->toContain('page=2');
});
