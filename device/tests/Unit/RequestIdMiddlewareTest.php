<?php

declare(strict_types=1);

use App\Http\Middleware\RequestIdMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->middleware = new RequestIdMiddleware;
    $this->request = Request::create('http://localhost/dashboard', 'GET');
});

it('generates a request ID when none is provided', function () {
    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('OK', 200);
    });

    $requestId = $response->headers->get('X-Request-Id');

    expect($requestId)->not->toBeNull();
    expect($requestId)->toBeString();
    expect(strlen($requestId))->toBe(36); // UUID v4 length
});

it('uses existing X-Request-Id header when provided', function () {
    $existingId = '550e8400-e29b-41d4-a716-446655440000';
    $this->request->headers->set('X-Request-Id', $existingId);

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('OK', 200);
    });

    expect($response->headers->get('X-Request-Id'))->toBe($existingId);
});

it('stores request ID in request attributes', function () {
    $capturedRequest = null;

    $response = $this->middleware->handle($this->request, function ($req) use (&$capturedRequest) {
        $capturedRequest = $req;

        return new Response('OK', 200);
    });

    $requestId = $response->headers->get('X-Request-Id');
    $attributeId = $capturedRequest->attributes->get('request_id');

    expect($attributeId)->toBe($requestId);
});

it('generates new ID when existing header is invalid', function () {
    $this->request->headers->set('X-Request-Id', 'invalid-id!@#$');

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('OK', 200);
    });

    $requestId = $response->headers->get('X-Request-Id');

    expect($requestId)->not->toBe('invalid-id!@#$');
    expect(strlen($requestId))->toBe(36); // UUID v4 length
});

it('accepts short alphanumeric request IDs', function (string $validId) {
    $this->request->headers->set('X-Request-Id', $validId);

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('OK', 200);
    });

    expect($response->headers->get('X-Request-Id'))->toBe($validId);
})->with([
    'short-alphanumeric' => 'abc123xyz',
    'with-hyphens' => 'abc-123-xyz',
    'with-underscores' => 'abc_123_xyz',
    'uppercase' => 'ABC123XYZ',
    'mixed' => 'abc-ABC-123',
    'exact-minimum-length' => 'abcdefgh',
]);

it('rejects too short request IDs', function () {
    $this->request->headers->set('X-Request-Id', 'abc');

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('OK', 200);
    });

    $requestId = $response->headers->get('X-Request-Id');

    expect($requestId)->not->toBe('abc');
    expect(strlen($requestId))->toBe(36); // Generated UUID
});

it('rejects too long request IDs', function () {
    $this->request->headers->set('X-Request-Id', str_repeat('a', 129));

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('OK', 200);
    });

    $requestId = $response->headers->get('X-Request-Id');

    expect(strlen($requestId))->toBe(36); // Generated UUID
});

it('rejects request IDs with special characters', function (string $invalidId) {
    $this->request->headers->set('X-Request-Id', $invalidId);

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('OK', 200);
    });

    $requestId = $response->headers->get('X-Request-Id');

    expect($requestId)->not->toBe($invalidId);
    expect(strlen($requestId))->toBe(36); // Generated UUID
})->with([
    'with-exclamation' => 'abc123!',
    'with-at' => 'abc@123',
    'with-hash' => 'abc#123',
    'with-space' => 'abc 123',
    'with-colon' => 'abc:123',
]);

it('rejects empty request ID', function () {
    $this->request->headers->set('X-Request-Id', '');

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('OK', 200);
    });

    $requestId = $response->headers->get('X-Request-Id');

    expect($requestId)->not->toBe('');
    expect(strlen($requestId))->toBe(36); // Generated UUID
});

it('logs request details at debug level', function () {
    Log::spy();

    $this->middleware->handle($this->request, function ($req) {
        return new Response('OK', 200);
    });

    Log::shouldHaveReceived('debug')->once()->withArgs(function ($message, $context) {
        return $message === 'Request processed'
            && isset($context['request_id'])
            && isset($context['method'])
            && isset($context['url'])
            && isset($context['ip'])
            && isset($context['user_agent']);
    });
});

it('logs correct request information', function () {
    $loggedContext = null;

    Log::shouldReceive('debug')
        ->once()
        ->withArgs(function ($message, $context) use (&$loggedContext) {
            $loggedContext = $context;

            return true;
        });

    $this->request->server->set('REMOTE_ADDR', '192.168.1.100');
    $this->request->headers->set('User-Agent', 'TestAgent/1.0');

    $this->middleware->handle($this->request, function ($req) {
        return new Response('OK', 200);
    });

    expect($loggedContext['method'])->toBe('GET');
    expect($loggedContext['url'])->toBe('http://localhost/dashboard');
    expect($loggedContext['ip'])->toBe('192.168.1.100');
    expect($loggedContext['user_agent'])->toBe('TestAgent/1.0');
    expect($loggedContext['request_id'])->toBeString();
});

it('each request generates unique request ID', function () {
    $ids = [];

    for ($i = 0; $i < 10; $i++) {
        $request = Request::create('http://localhost/dashboard', 'GET');
        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK', 200);
        });
        $ids[] = $response->headers->get('X-Request-Id');
    }

    expect($ids)->toHaveCount(10);
    expect($ids)->toEqual(array_unique($ids)); // All unique
});

it('getRequestId static method returns request ID from attributes', function () {
    $capturedRequest = null;

    $this->middleware->handle($this->request, function ($req) use (&$capturedRequest) {
        $capturedRequest = $req;

        return new Response('OK', 200);
    });

    $requestId = RequestIdMiddleware::getRequestId($capturedRequest);

    expect($requestId)->toBeString();
    expect(strlen($requestId))->toBe(36);
});

it('getRequestId returns null when middleware not applied', function () {
    $request = Request::create('http://localhost/dashboard', 'GET');

    $requestId = RequestIdMiddleware::getRequestId($request);

    expect($requestId)->toBeNull();
});

it('works with POST requests', function () {
    $this->request = Request::create('http://localhost/api/data', 'POST', ['key' => 'value']);

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('Created', 201);
    });

    expect($response->headers->has('X-Request-Id'))->toBeTrue();
    expect(strlen($response->headers->get('X-Request-Id')))->toBe(36);
});

it('works with error responses', function () {
    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('Not Found', 404);
    });

    expect($response->getStatusCode())->toBe(404);
    expect($response->headers->has('X-Request-Id'))->toBeTrue();
});

it('preserves existing response headers', function () {
    $response = $this->middleware->handle($this->request, function ($req) {
        $res = new Response('OK', 200);
        $res->headers->set('X-Custom-Header', 'custom-value');

        return $res;
    });

    expect($response->headers->get('X-Custom-Header'))->toBe('custom-value');
    expect($response->headers->get('X-Request-Id'))->toBeString();
});

it('handles requests with query parameters', function () {
    $this->request = Request::create('http://localhost/dashboard?search=test&page=2', 'GET');

    Log::spy();

    $response = $this->middleware->handle($this->request, function ($req) {
        return new Response('OK', 200);
    });

    Log::shouldHaveReceived('debug')->once()->withArgs(function ($message, $context) {
        return str_contains($context['url'], 'search=test')
            && str_contains($context['url'], 'page=2');
    });

    expect($response->headers->has('X-Request-Id'))->toBeTrue();
});

it('handles forwarded requests with X-Forwarded-For', function () {
    $this->request->headers->set('X-Forwarded-For', '10.0.0.1');

    Log::spy();

    $this->middleware->handle($this->request, function ($req) {
        return new Response('OK', 200);
    });

    Log::shouldHaveReceived('debug')->withArgs(function ($message, $context) {
        // IP should be resolved properly
        return $context['ip'] !== null;
    });
});

it('propagates same request ID through multiple middleware calls', function () {
    $existingId = '550e8400-e29b-41d4-a716-446655440000';
    $this->request->headers->set('X-Request-Id', $existingId);

    // First middleware call
    $response1 = $this->middleware->handle($this->request, function ($req) use ($existingId) {
        expect($req->attributes->get('request_id'))->toBe($existingId);

        return new Response('OK', 200);
    });

    expect($response1->headers->get('X-Request-Id'))->toBe($existingId);

    // Simulate a second request with the same ID from upstream
    $request2 = Request::create('http://localhost/another', 'GET');
    $request2->headers->set('X-Request-Id', $existingId);

    $response2 = $this->middleware->handle($request2, function ($req) use ($existingId) {
        expect($req->attributes->get('request_id'))->toBe($existingId);

        return new Response('OK', 200);
    });

    expect($response2->headers->get('X-Request-Id'))->toBe($existingId);
});
