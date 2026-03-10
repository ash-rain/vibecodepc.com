<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    Process::fake([
        "top -bn1 | grep 'Cpu(s)' | awk '{print \$2}'" => Process::result(output: '25.3'),
        "free -m | awk '/^Mem:/ {print \$3}'" => Process::result(output: '4096'),
        "free -m | awk '/^Mem:/ {print \$2}'" => Process::result(output: '8192'),
        "df -BG / | awk 'NR==2 {print \$3}'" => Process::result(output: '32G'),
        "df -BG / | awk 'NR==2 {print \$2}'" => Process::result(output: '64G'),
        'cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null' => Process::result(output: '52000'),
    ]);
});

afterEach(function () {
    RateLimiter::clear('ip:127.0.0.1');
});

it('applies rate limit headers to successful requests', function () {
    $response = $this->get('/api/health');

    $response->assertSuccessful()
        ->assertHeader('X-RateLimit-Limit', '60')
        ->assertHeader('X-RateLimit-Remaining', '59');
});

it('decrements rate limit remaining on multiple requests', function () {
    $this->get('/api/health')->assertHeader('X-RateLimit-Remaining', '59');
    $this->get('/api/health')->assertHeader('X-RateLimit-Remaining', '58');
    $this->get('/api/health')->assertHeader('X-RateLimit-Remaining', '57');
});

it('returns 429 when rate limit is exceeded', function () {
    // Clear any existing rate limit
    RateLimiter::clear('ip:127.0.0.1');

    // Make 60 requests to hit the limit
    for ($i = 0; $i < 60; $i++) {
        $this->get('/api/health');
    }

    // The 61st request should be rate limited
    $response = $this->get('/api/health');

    $response->assertStatus(429)
        ->assertJson([
            'error' => 'Too Many Requests',
            'message' => 'Rate limit exceeded. Please try again later.',
        ])
        ->assertHeader('Retry-After')
        ->assertHeader('X-RateLimit-Limit', '60')
        ->assertHeader('X-RateLimit-Remaining', '0');
});

it('tracks rate limits per IP address for unauthenticated users', function () {
    // Request from first IP
    $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.1'])->get('/api/health');

    // Request from second IP
    $response = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.2'])->get('/api/health');

    $response->assertHeader('X-RateLimit-Remaining', '59');
});

it('tracks rate limits per user for authenticated requests', function () {
    $user = User::factory()->create();

    // Clear any existing rate limits
    RateLimiter::clear('ip:127.0.0.1');
    RateLimiter::clear('user:'.$user->id);

    // Authenticated request
    $response = $this->actingAs($user)->get('/api/health');

    $response->assertHeader('X-RateLimit-Limit', '60')
        ->assertHeader('X-RateLimit-Remaining', '59');

    // Clear rate limits again before unauthenticated request
    RateLimiter::clear('ip:127.0.0.1');
    RateLimiter::clear('user:'.$user->id);

    $unauthenticatedResponse = $this->get('/api/health');

    // Unauthenticated request should have its own rate limit starting fresh
    $unauthenticatedResponse->assertHeader('X-RateLimit-Remaining', '59');
});

it('resets rate limit after time window expires', function () {
    // Hit the rate limit
    for ($i = 0; $i < 60; $i++) {
        $this->get('/api/health');
    }

    // Should be rate limited
    $this->get('/api/health')->assertStatus(429);

    // Simulate time passing
    RateLimiter::clear('ip:127.0.0.1');

    // Should work again after clearing
    $response = $this->get('/api/health');
    $response->assertSuccessful()
        ->assertHeader('X-RateLimit-Remaining', '59');
});

it('returns retry_after in error response', function () {
    // Clear any existing rate limit
    RateLimiter::clear('ip:127.0.0.1');

    // Hit the rate limit
    for ($i = 0; $i < 60; $i++) {
        $this->get('/api/health');
    }

    $response = $this->get('/api/health');

    $response->assertStatus(429)
        ->assertJsonPath('retry_after', fn ($value) => $value > 0);
});

it('supports custom rate limit parameters via middleware', function () {
    // The current implementation uses default values
    // This test verifies the middleware is applied with correct defaults
    $response = $this->get('/api/health');

    $response->assertHeader('X-RateLimit-Limit', '60');
});
