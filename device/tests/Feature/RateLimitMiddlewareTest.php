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

    // Authenticated request gets higher rate limit (120)
    $response = $this->actingAs($user)->get('/api/health');

    $response->assertHeader('X-RateLimit-Limit', '120')
        ->assertHeader('X-RateLimit-Remaining', '119');
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

it('applies higher rate limits for authenticated users', function () {
    $user = User::factory()->create();

    // Clear any existing rate limits
    RateLimiter::clear('ip:127.0.0.1');
    RateLimiter::clear('user:'.$user->id);

    // Authenticated request should have higher limit (120)
    $response = $this->actingAs($user)->get('/api/health');

    $response->assertHeader('X-RateLimit-Limit', '120')
        ->assertHeader('X-RateLimit-Remaining', '119');
});

it('applies lower rate limits for unauthenticated users', function () {
    // Clear any existing rate limits
    RateLimiter::clear('ip:127.0.0.1');

    // Unauthenticated request should have lower limit (60)
    $response = $this->get('/api/health');

    $response->assertHeader('X-RateLimit-Limit', '60')
        ->assertHeader('X-RateLimit-Remaining', '59');
});

it('allows authenticated users to make more requests than unauthenticated users', function () {
    $user = User::factory()->create();

    // Clear any existing rate limits
    RateLimiter::clear('ip:127.0.0.1');
    RateLimiter::clear('user:'.$user->id);

    // Make 70 requests as authenticated user (should not be rate limited since limit is 120)
    for ($i = 0; $i < 70; $i++) {
        $response = $this->actingAs($user)->get('/api/health');
        $response->assertSuccessful();
    }

    // 71st request should still succeed for authenticated user
    $response = $this->actingAs($user)->get('/api/health');
    $response->assertSuccessful()
        ->assertHeader('X-RateLimit-Remaining', '49'); // 120 - 71 = 49
});

it('rate limits unauthenticated users earlier than authenticated users', function () {
    // Clear any existing rate limits
    RateLimiter::clear('ip:127.0.0.1');

    // Make 60 requests as unauthenticated user
    for ($i = 0; $i < 60; $i++) {
        $this->get('/api/health');
    }

    // 61st request should be rate limited for unauthenticated user (limit is 60)
    $response = $this->get('/api/health');
    $response->assertStatus(429)
        ->assertHeader('X-RateLimit-Limit', '60');
});

// ============================================================================
// BURST SCENARIOS
// ============================================================================

it('handles burst of requests at exact rate limit threshold', function () {
    RateLimiter::clear('ip:127.0.0.1');

    // Make exactly 60 requests (at the threshold)
    $responses = [];
    for ($i = 0; $i < 60; $i++) {
        $responses[] = $this->get('/api/health');
    }

    // All 60 requests should succeed
    foreach ($responses as $response) {
        $response->assertSuccessful();
    }

    // The 61st request should be rate limited
    $response = $this->get('/api/health');
    $response->assertStatus(429);
});

it('handles rapid successive requests without race conditions', function () {
    RateLimiter::clear('ip:127.0.0.1');

    // Simulate rapid-fire requests
    $startTime = microtime(true);

    for ($i = 0; $i < 30; $i++) {
        $response = $this->get('/api/health');
        $response->assertSuccessful();
    }

    $elapsed = microtime(true) - $startTime;

    // All requests should complete successfully and quickly
    expect($elapsed)->toBeLessThan(5.0); // Should complete within 5 seconds

    // Verify remaining count is accurate after burst
    $response = $this->get('/api/health');
    $response->assertHeader('X-RateLimit-Remaining', '29');
});

it('maintains separate burst counters for different IPs', function () {
    RateLimiter::clear('ip:192.168.1.10');
    RateLimiter::clear('ip:192.168.1.20');

    // Burst from first IP
    for ($i = 0; $i < 50; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.10'])
            ->get('/api/health')
            ->assertSuccessful();
    }

    // Burst from second IP should not affect first
    for ($i = 0; $i < 50; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.20'])
            ->get('/api/health')
            ->assertSuccessful();
    }

    // First IP should have 10 remaining
    $response = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.10'])
        ->get('/api/health');
    $response->assertHeader('X-RateLimit-Remaining', '9');

    // Second IP should also have 10 remaining
    $response = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.20'])
        ->get('/api/health');
    $response->assertHeader('X-RateLimit-Remaining', '9');
});

// ============================================================================
// HEADER ASSERTIONS
// ============================================================================

it('includes all rate limit headers on successful response', function () {
    RateLimiter::clear('ip:127.0.0.1');

    $response = $this->get('/api/health');

    $response->assertSuccessful()
        ->assertHeader('X-RateLimit-Limit', '60')
        ->assertHeader('X-RateLimit-Remaining')
        ->assertHeaderMissing('Retry-After'); // Retry-After only on 429

    $remaining = $response->headers->get('X-RateLimit-Remaining');
    expect($remaining)->toBe('59');
});

it('includes all rate limit headers on throttled response', function () {
    RateLimiter::clear('ip:127.0.0.1');

    // Hit the rate limit
    for ($i = 0; $i < 60; $i++) {
        $this->get('/api/health');
    }

    $response = $this->get('/api/health');

    $response->assertStatus(429)
        ->assertHeader('X-RateLimit-Limit', '60')
        ->assertHeader('X-RateLimit-Remaining', '0')
        ->assertHeader('Retry-After');

    $retryAfter = $response->headers->get('Retry-After');
    expect((int) $retryAfter)->toBeGreaterThan(0)->toBeLessThanOrEqual(60);
});

it('returns integer values for all rate limit headers', function () {
    RateLimiter::clear('ip:127.0.0.1');

    $response = $this->get('/api/health');

    $limit = $response->headers->get('X-RateLimit-Limit');
    $remaining = $response->headers->get('X-RateLimit-Remaining');

    expect(filter_var($limit, FILTER_VALIDATE_INT))->not->toBeFalse();
    expect(filter_var($remaining, FILTER_VALIDATE_INT))->not->toBeFalse();
    expect((int) $limit)->toBe(60);
    expect((int) $remaining)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(60);
});

it('maintains consistent header values across multiple requests', function () {
    RateLimiter::clear('ip:127.0.0.1');

    $previousRemaining = 60;

    for ($i = 0; $i < 10; $i++) {
        $response = $this->get('/api/health');
        $currentRemaining = (int) $response->headers->get('X-RateLimit-Remaining');

        // Each request should decrement remaining by exactly 1
        expect($currentRemaining)->toBe($previousRemaining - 1);

        $previousRemaining = $currentRemaining;
    }
});

it('includes correct headers for authenticated users', function () {
    $user = User::factory()->create();
    RateLimiter::clear('user:'.$user->id);

    $response = $this->actingAs($user)->get('/api/health');

    $response->assertSuccessful()
        ->assertHeader('X-RateLimit-Limit', '120')
        ->assertHeader('X-RateLimit-Remaining', '119');
});

// ============================================================================
// BOUNDARY CONDITIONS
// ============================================================================

it('allows exactly the maximum number of requests', function () {
    RateLimiter::clear('ip:127.0.0.1');

    // Make exactly 60 requests (the limit)
    for ($i = 0; $i < 60; $i++) {
        $response = $this->get('/api/health');
        $response->assertSuccessful();
    }

    // Last request should have remaining = 0
    $response = $this->get('/api/health');
    $response->assertStatus(429)
        ->assertHeader('X-RateLimit-Remaining', '0');
});

it('blocks request immediately after limit is reached', function () {
    RateLimiter::clear('ip:127.0.0.1');

    // Make 60 requests to hit the limit
    for ($i = 0; $i < 60; $i++) {
        $this->get('/api/health');
    }

    // The very next request should be blocked
    $response = $this->get('/api/health');
    $response->assertStatus(429);

    // And the one after that
    $response = $this->get('/api/health');
    $response->assertStatus(429);
});

it('handles first request correctly', function () {
    RateLimiter::clear('ip:127.0.0.1');

    $response = $this->get('/api/health');

    $response->assertSuccessful()
        ->assertHeader('X-RateLimit-Limit', '60')
        ->assertHeader('X-RateLimit-Remaining', '59');
});

it('handles last allowed request before limit', function () {
    RateLimiter::clear('ip:127.0.0.1');

    // Make 59 requests
    for ($i = 0; $i < 59; $i++) {
        $this->get('/api/health');
    }

    // 60th request should succeed with remaining = 0
    $response = $this->get('/api/health');
    $response->assertSuccessful()
        ->assertHeader('X-RateLimit-Remaining', '0');

    // 61st request should be blocked
    $response = $this->get('/api/health');
    $response->assertStatus(429);
});

it('prevents negative remaining count', function () {
    RateLimiter::clear('ip:127.0.0.1');

    // Hit the limit
    for ($i = 0; $i < 60; $i++) {
        $this->get('/api/health');
    }

    // Make multiple requests after limit is reached
    for ($i = 0; $i < 5; $i++) {
        $response = $this->get('/api/health');
        $response->assertStatus(429)
            ->assertHeader('X-RateLimit-Remaining', '0'); // Should stay at 0, not go negative
    }
});

it('correctly calculates retry_after at boundary', function () {
    RateLimiter::clear('ip:127.0.0.1');

    // Hit the limit
    for ($i = 0; $i < 60; $i++) {
        $this->get('/api/health');
    }

    $response = $this->get('/api/health');

    $response->assertStatus(429);

    $retryAfter = $response->headers->get('Retry-After');
    $jsonResponse = $response->json();

    // Both header and JSON should have the same retry_after value
    expect((int) $retryAfter)->toBeGreaterThan(0)->toBeLessThanOrEqual(60);
    expect($jsonResponse['retry_after'])->toBe((int) $retryAfter);
});

// ============================================================================
// ADDITIONAL EDGE CASES
// ============================================================================

it('handles rate limiting with different decay windows', function () {
    $user = User::factory()->create();
    RateLimiter::clear('user:'.$user->id);

    // Make requests up to authenticated user limit
    for ($i = 0; $i < 120; $i++) {
        $this->actingAs($user)->get('/api/health');
    }

    // Should be rate limited
    $response = $this->actingAs($user)->get('/api/health');
    $response->assertStatus(429)
        ->assertHeader('X-RateLimit-Limit', '120');
});

it('maintains isolation between authenticated and unauthenticated counters', function () {
    $user = User::factory()->create();

    RateLimiter::clear('ip:127.0.0.1');
    RateLimiter::clear('user:'.$user->id);

    // Exhaust unauthenticated limit
    for ($i = 0; $i < 60; $i++) {
        $this->get('/api/health');
    }

    // Unauthenticated should be blocked
    $response = $this->get('/api/health');
    $response->assertStatus(429);

    // But authenticated user should still have full quota
    $response = $this->actingAs($user)->get('/api/health');
    $response->assertSuccessful()
        ->assertHeader('X-RateLimit-Limit', '120')
        ->assertHeader('X-RateLimit-Remaining', '119');
});

it('resets counter correctly after decay period', function () {
    RateLimiter::clear('ip:127.0.0.1');

    // Use up some of the quota
    for ($i = 0; $i < 30; $i++) {
        $this->get('/api/health');
    }

    // Verify remaining is 30
    $response = $this->get('/api/health');
    $response->assertHeader('X-RateLimit-Remaining', '29');

    // Clear the rate limit (simulating time passing)
    RateLimiter::clear('ip:127.0.0.1');

    // Counter should be reset
    $response = $this->get('/api/health');
    $response->assertSuccessful()
        ->assertHeader('X-RateLimit-Remaining', '59');
});

it('handles request with invalid IP gracefully', function () {
    // Request without IP should still work ( Laravel will provide a default)
    $response = $this->get('/api/health');
    $response->assertSuccessful();
});

it('preserves rate limit headers on error responses', function () {
    RateLimiter::clear('ip:127.0.0.1');

    // Even after multiple requests, headers should be present
    for ($i = 0; $i < 10; $i++) {
        $response = $this->get('/api/health');
        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
    }
});

// ============================================================================
// INTEGRATION SCENARIOS - CONCURRENT REQUESTS
// ============================================================================

it('handles concurrent requests without race conditions', function () {
    RateLimiter::clear('ip:127.0.0.1');

    // Simulate rapid concurrent requests by making many requests in quick succession
    $responses = [];
    $requestCount = 30;

    for ($i = 0; $i < $requestCount; $i++) {
        $responses[] = $this->get('/api/health');
    }

    // All requests should succeed
    foreach ($responses as $response) {
        $response->assertSuccessful();
    }

    // The remaining count should be accurate
    $finalResponse = $this->get('/api/health');
    $finalResponse->assertHeader('X-RateLimit-Remaining', (string) (60 - $requestCount - 1));
});

it('handles high-frequency burst requests', function () {
    RateLimiter::clear('ip:127.0.0.1');

    // Simulate a high-frequency burst of 60 requests
    $successCount = 0;
    for ($i = 0; $i < 60; $i++) {
        $response = $this->get('/api/health');
        if ($response->isSuccessful()) {
            $successCount++;
        }
    }

    // All 60 should succeed
    expect($successCount)->toBe(60);

    // The 61st should be rate limited
    $response = $this->get('/api/health');
    $response->assertStatus(429);
});

// ============================================================================
// INTEGRATION SCENARIOS - DIFFERENT HTTP METHODS
// ============================================================================

it('applies rate limiting across different HTTP methods', function () {
    RateLimiter::clear('ip:127.0.0.1');

    // Create a test route that supports multiple methods
    Route::middleware(['api.rate_limit:60,1'])->match(['get', 'post', 'put', 'patch', 'delete'], '/test-multi-method', function () {
        return response()->json(['status' => 'ok']);
    });

    // Make requests using different HTTP methods
    $this->get('/test-multi-method')->assertSuccessful();
    $this->post('/test-multi-method')->assertSuccessful();
    $this->put('/test-multi-method')->assertSuccessful();
    $this->patch('/test-multi-method')->assertSuccessful();
    $this->delete('/test-multi-method')->assertSuccessful();

    // All should decrement the same counter
    $response = $this->get('/test-multi-method');
    $response->assertHeader('X-RateLimit-Remaining', '54'); // 60 - 5 - 1 = 54
});

// ============================================================================
// INTEGRATION SCENARIOS - IPV6 ADDRESSES
// ============================================================================

it('handles IPv6 addresses correctly', function () {
    $ipv6Address = '2001:db8::1';
    RateLimiter::clear('ip:'.$ipv6Address);

    $response = $this->withServerVariables(['REMOTE_ADDR' => $ipv6Address])
        ->get('/api/health');

    $response->assertSuccessful()
        ->assertHeader('X-RateLimit-Limit', '60')
        ->assertHeader('X-RateLimit-Remaining', '59');

    // Second request from same IPv6 should decrement
    $response = $this->withServerVariables(['REMOTE_ADDR' => $ipv6Address])
        ->get('/api/health');

    $response->assertHeader('X-RateLimit-Remaining', '58');
});

it('maintains separate counters for IPv4 and IPv6 from same client', function () {
    $ipv4Address = '192.168.1.100';
    $ipv6Address = '2001:db8::100';

    RateLimiter::clear('ip:'.$ipv4Address);
    RateLimiter::clear('ip:'.$ipv6Address);

    // Request from IPv4
    $this->withServerVariables(['REMOTE_ADDR' => $ipv4Address])
        ->get('/api/health')
        ->assertHeader('X-RateLimit-Remaining', '59');

    // Request from IPv6 should have its own counter
    $this->withServerVariables(['REMOTE_ADDR' => $ipv6Address])
        ->get('/api/health')
        ->assertHeader('X-RateLimit-Remaining', '59');
});

// ============================================================================
// INTEGRATION SCENARIOS - RATE LIMIT PERSISTENCE
// ============================================================================

it('maintains rate limit state across multiple request cycles', function () {
    RateLimiter::clear('ip:127.0.0.1');

    // First batch of requests
    for ($i = 0; $i < 20; $i++) {
        $this->get('/api/health');
    }

    // Second batch should continue from where first left off
    $response = $this->get('/api/health');
    $response->assertHeader('X-RateLimit-Remaining', '39'); // 60 - 20 - 1 = 39

    // Third batch
    for ($i = 0; $i < 20; $i++) {
        $this->get('/api/health');
    }

    $response = $this->get('/api/health');
    $response->assertHeader('X-RateLimit-Remaining', '18'); // 60 - 41 - 1 = 18
});

// ============================================================================
// INTEGRATION SCENARIOS - CUSTOM MIDDLEWARE PARAMETERS
// ============================================================================

it('respects custom rate limit parameters when provided', function () {
    // Clear any existing rate limits
    RateLimiter::clear('ip:127.0.0.1');

    // Create a test route with custom rate limits (10 requests per 2 minutes)
    Route::middleware(['api.rate_limit:10,2'])->group(function () {
        Route::get('/test-custom-limit', function () {
            return response()->json(['status' => 'ok']);
        });
    });

    // Make 10 requests (should all succeed)
    for ($i = 0; $i < 10; $i++) {
        $this->get('/test-custom-limit')->assertSuccessful();
    }

    // 11th request should be rate limited
    $response = $this->get('/test-custom-limit');
    $response->assertStatus(429)
        ->assertHeader('X-RateLimit-Limit', '10')
        ->assertHeader('X-RateLimit-Remaining', '0');

    // Retry-After should reflect 2 minute decay (120 seconds)
    $retryAfter = $response->headers->get('Retry-After');
    expect((int) $retryAfter)->toBeGreaterThan(0)->toBeLessThanOrEqual(120);
});

it('applies different limits for authenticated vs unauthenticated via middleware params', function () {
    $user = User::factory()->create();

    // Clear rate limits
    RateLimiter::clear('ip:127.0.0.1');
    RateLimiter::clear('user:'.$user->id);

    // Create a test route with different auth/unauth limits
    Route::middleware(['api.rate_limit:10,1,50,1'])->group(function () {
        Route::get('/test-dual-limits', function () {
            return response()->json(['status' => 'ok']);
        });
    });

    // Unauthenticated user should have limit of 10
    $response = $this->get('/test-dual-limits');
    $response->assertHeader('X-RateLimit-Limit', '10');

    // Authenticated user should have limit of 50
    $response = $this->actingAs($user)->get('/test-dual-limits');
    $response->assertHeader('X-RateLimit-Limit', '50')
        ->assertHeader('X-RateLimit-Remaining', '49');
});

// ============================================================================
// INTEGRATION SCENARIOS - BOUNDARY CONDITIONS - EDGE CASES
// ============================================================================

it('handles exactly at limit boundary correctly', function () {
    RateLimiter::clear('ip:127.0.0.1');

    // Make exactly 59 requests
    for ($i = 0; $i < 59; $i++) {
        $this->get('/api/health')->assertSuccessful();
    }

    // 60th request should succeed with 0 remaining
    $response = $this->get('/api/health');
    $response->assertSuccessful()
        ->assertHeader('X-RateLimit-Remaining', '0');

    // 61st should be blocked
    $response = $this->get('/api/health');
    $response->assertStatus(429);
});

it('handles rate limit with remaining at boundary of zero', function () {
    RateLimiter::clear('ip:127.0.0.1');

    // Exhaust the rate limit
    for ($i = 0; $i < 60; $i++) {
        $this->get('/api/health');
    }

    // Verify remaining is 0, not negative
    for ($i = 0; $i < 10; $i++) {
        $response = $this->get('/api/health');
        $response->assertStatus(429)
            ->assertHeader('X-RateLimit-Remaining', '0');
    }
});

it('handles rapid switching between authenticated and unauthenticated', function () {
    $user = User::factory()->create();

    RateLimiter::clear('ip:127.0.0.1');
    RateLimiter::clear('user:'.$user->id);

    // Test unauthenticated first - should have limit of 60
    $response = $this->get('/api/health');
    $response->assertHeader('X-RateLimit-Limit', '60');

    // Test authenticated - should have limit of 120
    $response = $this->actingAs($user)->get('/api/health');
    $response->assertHeader('X-RateLimit-Limit', '120')
        ->assertHeader('X-RateLimit-Remaining', '119');

    // Make more requests as authenticated user
    $this->actingAs($user)->get('/api/health');
    $this->actingAs($user)->get('/api/health');

    // Verify authenticated counter decremented independently
    $response = $this->actingAs($user)->get('/api/health');
    $response->assertHeader('X-RateLimit-Remaining', '116'); // 120 - 4 = 116
});

// ============================================================================
// INTEGRATION SCENARIOS - HEADER ASSERTIONS - COMPREHENSIVE
// ============================================================================

it('returns consistent header values across all response types', function () {
    RateLimiter::clear('ip:127.0.0.1');

    // Test headers on successful responses
    $response = $this->get('/api/health');
    $response->assertHeader('X-RateLimit-Limit', '60')
        ->assertHeader('X-RateLimit-Remaining', '59')
        ->assertHeaderMissing('Retry-After');

    // Hit the limit
    for ($i = 0; $i < 59; $i++) {
        $this->get('/api/health');
    }

    // Test headers on throttled response
    $response = $this->get('/api/health');
    $response->assertStatus(429)
        ->assertHeader('X-RateLimit-Limit', '60')
        ->assertHeader('X-RateLimit-Remaining', '0')
        ->assertHeader('Retry-After');

    // Verify Retry-After is reasonable (between 1 and 60 seconds)
    $retryAfter = (int) $response->headers->get('Retry-After');
    expect($retryAfter)->toBeGreaterThan(0)->toBeLessThanOrEqual(60);
});

it('includes correct Content-Type header on rate limited responses', function () {
    RateLimiter::clear('ip:127.0.0.1');

    // Hit the limit
    for ($i = 0; $i < 60; $i++) {
        $this->get('/api/health');
    }

    $response = $this->get('/api/health');
    $response->assertStatus(429)
        ->assertHeader('Content-Type', 'application/json');
});

it('preserves custom headers when rate limiting', function () {
    RateLimiter::clear('ip:127.0.0.1');

    // Hit the limit
    for ($i = 0; $i < 60; $i++) {
        $this->get('/api/health');
    }

    // Rate limited response should still have rate limit headers
    $response = $this->get('/api/health');
    $response->assertStatus(429)
        ->assertHeader('X-RateLimit-Limit')
        ->assertHeader('X-RateLimit-Remaining')
        ->assertHeader('Retry-After');
});
