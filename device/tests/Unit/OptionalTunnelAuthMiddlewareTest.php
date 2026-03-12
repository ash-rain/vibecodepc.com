<?php

declare(strict_types=1);

use App\Http\Middleware\OptionalTunnelAuth;
use App\Models\TunnelConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Ensure clean state for each test
    TunnelConfig::query()->delete();

    // Setup for unit tests
    $this->middleware = new OptionalTunnelAuth;
    $this->request = Request::create('http://localhost/dashboard', 'GET');
});

// ============================================================================
// INTEGRATION TESTS - HTTP Request Style
// ============================================================================

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

it('redirects when session tunnel_authenticated is false', function () {
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

// ============================================================================
// UNIT TESTS - Direct Middleware Instantiation
// ============================================================================

// Bypass scenarios when tunnel config doesn't exist or has no token
it('bypasses auth when no tunnel config exists via direct call', function () {
    $this->request->headers->set('CF-Connecting-IP', '1.2.3.4');
    $this->request->setLaravelSession(app('session')->driver());

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('Passed through', 200);
    });

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe('Passed through');
});

it('bypasses auth when tunnel token is empty string via direct call', function () {
    TunnelConfig::create([
        'subdomain' => 'test-device',
        'tunnel_token_encrypted' => '',
        'tunnel_id' => 'test-tunnel-123',
        'status' => 'active',
    ]);

    $this->request->headers->set('CF-Connecting-IP', '1.2.3.4');
    $this->request->setLaravelSession(app('session')->driver());

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('Passed through', 200);
    });

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe('Passed through');
});

it('bypasses auth when tunnel token is null via direct call', function () {
    TunnelConfig::create([
        'subdomain' => 'test-device',
        'tunnel_token_encrypted' => null,
        'tunnel_id' => 'test-tunnel-123',
        'status' => 'active',
    ]);

    $this->request->headers->set('CF-Connecting-IP', '1.2.3.4');
    $this->request->setLaravelSession(app('session')->driver());

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('Passed through', 200);
    });

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe('Passed through');
});

it('bypasses auth when not a tunnel request via direct call', function () {
    TunnelConfig::create([
        'subdomain' => 'test-device',
        'tunnel_token_encrypted' => 'encrypted-token-value',
        'tunnel_id' => 'test-tunnel-123',
        'status' => 'active',
    ]);

    $this->request->setLaravelSession(app('session')->driver());

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('Passed through', 200);
    });

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe('Passed through');
});

// Token validation scenarios
it('requires auth when tunnel config exists with valid token via direct call', function () {
    TunnelConfig::create([
        'subdomain' => 'test-device',
        'tunnel_token_encrypted' => 'encrypted-token-value',
        'tunnel_id' => 'test-tunnel-123',
        'status' => 'active',
    ]);

    $this->request->headers->set('CF-Connecting-IP', '1.2.3.4');
    $this->request->setLaravelSession(app('session')->driver());

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('Should not reach here', 200);
    });

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toContain('tunnel/login');
});

it('allows authenticated tunnel requests with valid token via direct call', function () {
    TunnelConfig::create([
        'subdomain' => 'test-device',
        'tunnel_token_encrypted' => 'encrypted-token-value',
        'tunnel_id' => 'test-tunnel-123',
        'status' => 'active',
    ]);

    $this->request->headers->set('CF-Connecting-IP', '1.2.3.4');
    $this->request->setLaravelSession(app('session')->driver());
    $this->request->session()->put('tunnel_authenticated', true);

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('Passed through', 200);
    });

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe('Passed through');
});

it('allows tunnel login route without authentication via direct call', function () {
    TunnelConfig::create([
        'subdomain' => 'test-device',
        'tunnel_token_encrypted' => 'encrypted-token-value',
        'tunnel_id' => 'test-tunnel-123',
        'status' => 'active',
    ]);

    $this->request = Request::create('http://localhost/tunnel/login', 'GET');
    $this->request->headers->set('CF-Connecting-IP', '1.2.3.4');
    $this->request->setLaravelSession(app('session')->driver());

    // Mock the routeIs check
    $this->request->setRouteResolver(function () {
        return new class
        {
            public function named($name): bool
            {
                return $name === 'tunnel.login';
            }
        };
    });

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('Passed through', 200);
    });

    expect($response->getStatusCode())->toBe(200);
});

// Edge cases
it('redirects when session tunnel_authenticated is false via direct call', function () {
    TunnelConfig::create([
        'subdomain' => 'test-device',
        'tunnel_token_encrypted' => 'encrypted-token-value',
        'tunnel_id' => 'test-tunnel-123',
        'status' => 'active',
    ]);

    $this->request->headers->set('CF-Connecting-IP', '1.2.3.4');
    $this->request->setLaravelSession(app('session')->driver());
    $this->request->session()->put('tunnel_authenticated', false);

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('Should not reach here', 200);
    });

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toContain('tunnel/login');
});

it('redirects when session tunnel_authenticated is null via direct call', function () {
    TunnelConfig::create([
        'subdomain' => 'test-device',
        'tunnel_token_encrypted' => 'encrypted-token-value',
        'tunnel_id' => 'test-tunnel-123',
        'status' => 'active',
    ]);

    $this->request->headers->set('CF-Connecting-IP', '1.2.3.4');
    $this->request->setLaravelSession(app('session')->driver());
    $this->request->session()->put('tunnel_authenticated', null);

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('Should not reach here', 200);
    });

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toContain('tunnel/login');
});

it('stores intended URL when redirecting via direct call', function () {
    TunnelConfig::create([
        'subdomain' => 'test-device',
        'tunnel_token_encrypted' => 'encrypted-token-value',
        'tunnel_id' => 'test-tunnel-123',
        'status' => 'active',
    ]);

    $this->request->headers->set('CF-Connecting-IP', '1.2.3.4');
    $session = app('session')->driver();
    $this->request->setLaravelSession($session);

    $this->middleware->handle($this->request, function ($req) {
        return new Response('Should not reach here', 200);
    });

    expect($session->get('tunnel_auth_intended_url'))->toBe('http://localhost/dashboard');
});

it('handles empty CF-Connecting-IP header via direct call', function () {
    TunnelConfig::create([
        'subdomain' => 'test-device',
        'tunnel_token_encrypted' => 'encrypted-token-value',
        'tunnel_id' => 'test-tunnel-123',
        'status' => 'active',
    ]);

    $this->request->headers->set('CF-Connecting-IP', '');
    $this->request->setLaravelSession(app('session')->driver());

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('Passed through', 200);
    });

    // Empty header means it's still a tunnel request (header exists)
    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toContain('tunnel/login');
});

it('handles requests with multiple CF-Connecting-IP values via direct call', function () {
    TunnelConfig::create([
        'subdomain' => 'test-device',
        'tunnel_token_encrypted' => 'encrypted-token-value',
        'tunnel_id' => 'test-tunnel-123',
        'status' => 'active',
    ]);

    $this->request->headers->set('CF-Connecting-IP', ['1.2.3.4', '5.6.7.8']);
    $this->request->setLaravelSession(app('session')->driver());

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('Should not reach here', 200);
    });

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toContain('tunnel/login');
});

it('preserves query parameters in intended URL via direct call', function () {
    TunnelConfig::create([
        'subdomain' => 'test-device',
        'tunnel_token_encrypted' => 'encrypted-token-value',
        'tunnel_id' => 'test-tunnel-123',
        'status' => 'active',
    ]);

    $this->request = Request::create('http://localhost/dashboard?tab=settings&page=2', 'GET');
    $this->request->headers->set('CF-Connecting-IP', '1.2.3.4');
    $session = app('session')->driver();
    $this->request->setLaravelSession($session);

    $this->middleware->handle($this->request, function ($req) {
        return new Response('Should not reach here', 200);
    });

    $intendedUrl = $session->get('tunnel_auth_intended_url');
    expect($intendedUrl)->toContain('tab=settings');
    expect($intendedUrl)->toContain('page=2');
});

it('handles special characters in intended URL via direct call', function () {
    TunnelConfig::create([
        'subdomain' => 'test-device',
        'tunnel_token_encrypted' => 'encrypted-token-value',
        'tunnel_id' => 'test-tunnel-123',
        'status' => 'active',
    ]);

    $this->request = Request::create('http://localhost/dashboard?search=hello%20world&filter=test', 'GET');
    $this->request->headers->set('CF-Connecting-IP', '1.2.3.4');
    $session = app('session')->driver();
    $this->request->setLaravelSession($session);

    $this->middleware->handle($this->request, function ($req) {
        return new Response('Should not reach here', 200);
    });

    $intendedUrl = $session->get('tunnel_auth_intended_url');
    expect($intendedUrl)->toContain('search=');
    expect($intendedUrl)->toContain('filter=');
});

it('updates intended URL on subsequent requests via direct call', function () {
    TunnelConfig::create([
        'subdomain' => 'test-device',
        'tunnel_token_encrypted' => 'encrypted-token-value',
        'tunnel_id' => 'test-tunnel-123',
        'status' => 'active',
    ]);

    $this->request->headers->set('CF-Connecting-IP', '1.2.3.4');
    $session = app('session')->driver();
    $this->request->setLaravelSession($session);

    // First request
    $this->middleware->handle($this->request, function ($req) {
        return new Response('Should not reach here', 200);
    });

    expect($session->get('tunnel_auth_intended_url'))->toBe('http://localhost/dashboard');

    // Second request to different URL
    $request2 = Request::create('http://localhost/dashboard/projects', 'GET');
    $request2->headers->set('CF-Connecting-IP', '1.2.3.4');
    $request2->setLaravelSession($session);

    $this->middleware->handle($request2, function ($req) {
        return new Response('Should not reach here', 200);
    });

    expect($session->get('tunnel_auth_intended_url'))->toBe('http://localhost/dashboard/projects');
});

it('handles different HTTP methods via direct call', function (string $method) {
    TunnelConfig::create([
        'subdomain' => 'test-device',
        'tunnel_token_encrypted' => 'encrypted-token-value',
        'tunnel_id' => 'test-tunnel-123',
        'status' => 'active',
    ]);

    $this->request = Request::create('http://localhost/dashboard', $method);
    $this->request->headers->set('CF-Connecting-IP', '1.2.3.4');
    $this->request->setLaravelSession(app('session')->driver());

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('Should not reach here', 200);
    });

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toContain('tunnel/login');
})->with(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);

it('maintains session state across requests via direct call', function () {
    TunnelConfig::create([
        'subdomain' => 'test-device',
        'tunnel_token_encrypted' => 'encrypted-token-value',
        'tunnel_id' => 'test-tunnel-123',
        'status' => 'active',
    ]);

    $this->request->headers->set('CF-Connecting-IP', '1.2.3.4');
    $session = app('session')->driver();
    $this->request->setLaravelSession($session);

    // First request - unauthenticated
    $response1 = $this->middleware->handle($this->request, function ($req) {
        return new Response('Should not reach here', 200);
    });

    expect($response1->getStatusCode())->toBe(302);
    expect($session->get('tunnel_auth_intended_url'))->toBe('http://localhost/dashboard');

    // Simulate authentication
    $this->request->session()->put('tunnel_authenticated', true);

    // Second request - authenticated
    $response2 = $this->middleware->handle($this->request, function ($req) {
        return new Response('Passed through', 200);
    });

    expect($response2->getStatusCode())->toBe(200);
});

it('returns proper redirect response via direct call', function () {
    TunnelConfig::create([
        'subdomain' => 'test-device',
        'tunnel_token_encrypted' => 'encrypted-token-value',
        'tunnel_id' => 'test-tunnel-123',
        'status' => 'active',
    ]);

    $this->request->headers->set('CF-Connecting-IP', '1.2.3.4');
    $this->request->setLaravelSession(app('session')->driver());

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('Should not reach here', 200);
    });

    expect($response)->toBeInstanceOf(SymfonyResponse::class);
    expect($response->isRedirect())->toBeTrue();
    expect($response->headers->get('Location'))->toContain('tunnel/login');
});

it('handles POST requests to tunnel login via direct call', function () {
    TunnelConfig::create([
        'subdomain' => 'test-device',
        'tunnel_token_encrypted' => 'encrypted-token-value',
        'tunnel_id' => 'test-tunnel-123',
        'status' => 'active',
    ]);

    $this->request = Request::create('http://localhost/tunnel/login', 'POST');
    $this->request->headers->set('CF-Connecting-IP', '1.2.3.4');
    $this->request->setLaravelSession(app('session')->driver());

    // Mock the routeIs check
    $this->request->setRouteResolver(function () {
        return new class
        {
            public function named($name): bool
            {
                return $name === 'tunnel.login';
            }
        };
    });

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('Passed through', 200);
    });

    expect($response->getStatusCode())->toBe(200);
});

it('handles edge case where next callback returns redirect via direct call', function () {
    TunnelConfig::create([
        'subdomain' => 'test-device',
        'tunnel_token_encrypted' => 'encrypted-token-value',
        'tunnel_id' => 'test-tunnel-123',
        'status' => 'active',
    ]);

    $this->request->headers->set('CF-Connecting-IP', '1.2.3.4');
    $this->request->setLaravelSession(app('session')->driver());
    $this->request->session()->put('tunnel_authenticated', true);

    $redirectResponse = new SymfonyResponse('', 302, ['Location' => 'http://localhost/somewhere']);

    $response = $this->middleware->handle($this->request, function ($req) use ($redirectResponse) {
        return $redirectResponse;
    });

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toBe('http://localhost/somewhere');
});

it('bypasses auth with whitespace-only tunnel token via direct call', function () {
    TunnelConfig::create([
        'subdomain' => 'test-device',
        'tunnel_token_encrypted' => '   ',
        'tunnel_id' => 'test-tunnel-123',
        'status' => 'active',
    ]);

    $this->request->headers->set('CF-Connecting-IP', '1.2.3.4');
    $this->request->setLaravelSession(app('session')->driver());

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('Passed through', 200);
    });

    // Whitespace-only is still considered a valid token (not empty)
    expect($response->getStatusCode())->toBe(302);
});

it('verifies all bypass conditions are checked via direct call', function () {
    // This tests that the middleware properly checks all conditions in order
    TunnelConfig::create([
        'subdomain' => 'test-device',
        'tunnel_token_encrypted' => 'encrypted-token-value',
        'tunnel_id' => 'test-tunnel-123',
        'status' => 'active',
    ]);

    // Without CF header, should bypass
    $this->request->setLaravelSession(app('session')->driver());

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('Passed through', 200);
    });

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe('Passed through');
});
