<?php

declare(strict_types=1);

use App\Http\Middleware\RequireTunnelAuth;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

beforeEach(function () {
    $this->middleware = new RequireTunnelAuth;
    $this->request = Request::create('http://localhost/dashboard', 'GET');
});

it('allows requests without CF-Connecting-IP header to pass through', function () {
    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('Passed through', 200);
    });

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe('Passed through');
});

it('allows authenticated tunnel requests to pass through', function () {
    $this->request->headers->set('CF-Connecting-IP', '1.2.3.4');
    $this->request->setLaravelSession(app('session')->driver());
    $this->request->session()->put('tunnel_authenticated', true);

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('Passed through', 200);
    });

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe('Passed through');
});

it('allows access to tunnel login page without authentication', function () {
    $this->request = Request::create('http://localhost/tunnel/login', 'GET');
    $this->request->headers->set('CF-Connecting-IP', '1.2.3.4');
    $this->request->setLaravelSession(app('session')->driver());

    // Mock the routeIs check by setting route resolver
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

it('redirects unauthenticated tunnel requests to login page', function () {
    $this->request->headers->set('CF-Connecting-IP', '1.2.3.4');
    $this->request->setLaravelSession(app('session')->driver());

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('Should not reach here', 200);
    });

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toContain('tunnel/login');
});

it('stores intended URL in session when redirecting to login', function () {
    $this->request->headers->set('CF-Connecting-IP', '1.2.3.4');
    $session = app('session')->driver();
    $this->request->setLaravelSession($session);

    $this->middleware->handle($this->request, function ($req) {
        return new Response('Should not reach here', 200);
    });

    expect($session->get('tunnel_auth_intended_url'))->toBe('http://localhost/dashboard');
});

it('handles various CF-Connecting-IP header formats', function (string $ipAddress) {
    $this->request->headers->set('CF-Connecting-IP', $ipAddress);
    $this->request->setLaravelSession(app('session')->driver());

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('Should not reach here', 200);
    });

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->has('Location'))->toBeTrue();
})->with([
    'IPv4 address' => '192.168.1.1',
    'public IPv4' => '203.0.113.45',
    'loopback' => '127.0.0.1',
    'IPv6 address' => '2001:db8::1',
    'Cloudflare IP' => '172.68.1.1',
]);

it('redirects when session tunnel_authenticated is false', function () {
    $this->request->headers->set('CF-Connecting-IP', '1.2.3.4');
    $this->request->setLaravelSession(app('session')->driver());
    $this->request->session()->put('tunnel_authenticated', false);

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('Should not reach here', 200);
    });

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toContain('tunnel/login');
});

it('redirects when session tunnel_authenticated is null', function () {
    $this->request->headers->set('CF-Connecting-IP', '1.2.3.4');
    $this->request->setLaravelSession(app('session')->driver());
    $this->request->session()->put('tunnel_authenticated', null);

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('Should not reach here', 200);
    });

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toContain('tunnel/login');
});

it('does not affect non-tunnel requests', function () {
    // No CF-Connecting-IP header set
    $this->request->setLaravelSession(app('session')->driver());

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('Passed through', 200);
    });

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe('Passed through');
});

it('updates intended URL on subsequent requests', function () {
    $this->request->headers->set('CF-Connecting-IP', '1.2.3.4');
    $session = app('session')->driver();
    $this->request->setLaravelSession($session);

    // First request to /dashboard
    $this->middleware->handle($this->request, function ($req) {
        return new Response('Should not reach here', 200);
    });

    $firstIntendedUrl = $session->get('tunnel_auth_intended_url');
    expect($firstIntendedUrl)->toBe('http://localhost/dashboard');

    // Second request to /dashboard/projects
    $request2 = Request::create('http://localhost/dashboard/projects', 'GET');
    $request2->headers->set('CF-Connecting-IP', '1.2.3.4');
    $request2->setLaravelSession($session);

    $this->middleware->handle($request2, function ($req) {
        return new Response('Should not reach here', 200);
    });

    expect($session->get('tunnel_auth_intended_url'))->toBe('http://localhost/dashboard/projects');
});

it('preserves query parameters when storing intended URL', function () {
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

it('handles empty CF-Connecting-IP header as tunnel request', function () {
    $this->request->headers->set('CF-Connecting-IP', '');
    $this->request->setLaravelSession(app('session')->driver());

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('Should not reach here', 200);
    });

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toContain('tunnel/login');
});

it('allows request through when session is authenticated with various values', function () {
    $this->request->headers->set('CF-Connecting-IP', '1.2.3.4');
    $this->request->setLaravelSession(app('session')->driver());
    $this->request->session()->put('tunnel_authenticated', true);

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('Passed through', 200);
    });

    expect($response->getStatusCode())->toBe(200);
});

it('handles POST requests to tunnel login page', function () {
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

it('handles requests with multiple CF-Connecting-IP values', function () {
    $this->request->headers->set('CF-Connecting-IP', ['1.2.3.4', '5.6.7.8']);
    $this->request->setLaravelSession(app('session')->driver());

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('Should not reach here', 200);
    });

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toContain('tunnel/login');
});

it('handles special characters in intended URL correctly', function () {
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

it('clears and sets intended URL correctly', function () {
    $this->request->headers->set('CF-Connecting-IP', '1.2.3.4');
    $session = app('session')->driver();
    $this->request->setLaravelSession($session);

    // Set existing intended URL
    $session->put('tunnel_auth_intended_url', 'http://localhost/old-url');

    $this->middleware->handle($this->request, function ($req) {
        return new Response('Should not reach here', 200);
    });

    // Should be updated to new URL
    expect($session->get('tunnel_auth_intended_url'))->toBe('http://localhost/dashboard');
});

it('maintains session state across multiple requests', function () {
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

it('returns proper redirect response with route name', function () {
    $this->request->headers->set('CF-Connecting-IP', '1.2.3.4');
    $this->request->setLaravelSession(app('session')->driver());

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('Should not reach here', 200);
    });

    expect($response)->toBeInstanceOf(SymfonyResponse::class);
    expect($response->isRedirect())->toBeTrue();
    expect($response->headers->get('Location'))->toContain('tunnel/login');
});

it('handles edge case where next callback returns redirect response', function () {
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

it('handles different HTTP methods for tunnel requests', function (string $method) {
    $this->request = Request::create('http://localhost/dashboard', $method);
    $this->request->headers->set('CF-Connecting-IP', '1.2.3.4');
    $this->request->setLaravelSession(app('session')->driver());

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('Should not reach here', 200);
    });

    expect($response->getStatusCode())->toBe(302);
})->with(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);
