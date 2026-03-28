<?php

use App\Services\Traits\RetryableTrait;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;

class TestRetryableClass
{
    use RetryableTrait;
}

beforeEach(function () {
    $this->retryable = new TestRetryableClass;
});

describe('RetryableTrait', function () {
    describe('shouldRetry', function () {
        it('returns true for ConnectionException', function () {
            $exception = new ConnectionException('Network error');

            expect($this->retryable->shouldRetry($exception))->toBeTrue();
        });

        it('returns true for RequestException with retryable status codes', function ($statusCode) {
            $response = Mockery::mock(Response::class);
            $response->shouldReceive('status')->andReturn($statusCode);

            $exception = Mockery::mock(RequestException::class);
            $exception->response = $response;

            expect($this->retryable->shouldRetry($exception))->toBeTrue();
        })->with([408, 429, 500, 502, 503, 504]);

        it('returns false for RequestException with non-retryable status codes', function ($statusCode) {
            $response = Mockery::mock(Response::class);
            $response->shouldReceive('status')->andReturn($statusCode);

            $exception = Mockery::mock(RequestException::class);
            $exception->response = $response;

            expect($this->retryable->shouldRetry($exception))->toBeFalse();
        })->with([400, 401, 403, 404, 405, 422]);

        it('returns false for generic exceptions', function () {
            $exception = new \RuntimeException('Something went wrong');

            expect($this->retryable->shouldRetry($exception))->toBeFalse();
        });

        it('returns false for RequestException with null response', function () {
            $exception = Mockery::mock(RequestException::class);
            $exception->response = null;

            expect($this->retryable->shouldRetry($exception))->toBeFalse();
        });
    });

    describe('calculateBackoffDelay', function () {
        it('calculates exponential backoff for first attempt', function () {
            $delay = $this->retryable->calculateBackoffDelay(1);

            // Base delay is 100ms, first attempt: 100 * 2^0 = 100ms
            // With jitter ±20%, delay should be between 80ms and 120ms
            expect($delay)->toBeGreaterThanOrEqual(80)
                ->and($delay)->toBeLessThanOrEqual(120);
        });

        it('calculates exponential backoff for subsequent attempts', function () {
            $delays = [];
            for ($attempt = 1; $attempt <= 5; $attempt++) {
                $delays[$attempt] = $this->retryable->calculateBackoffDelay($attempt);
            }

            // Each attempt should have higher base delay (exponential)
            expect($delays[2])->toBeGreaterThan($delays[1]);
            expect($delays[3])->toBeGreaterThan($delays[2]);
        });

        it('caps delay at max delay', function () {
            // For attempt 10, without cap: 100 * 2^9 = 51200ms
            // Should be capped at 5000ms + up to 20% jitter = 6000ms max
            $delay = $this->retryable->calculateBackoffDelay(10);

            expect($delay)->toBeLessThanOrEqual(6000);
        });

        it('returns non-negative delay', function () {
            $delay = $this->retryable->calculateBackoffDelay(100);

            expect($delay)->toBeGreaterThanOrEqual(0);
        });
    });

    describe('configuration setters', function () {
        it('allows setting max retries', function () {
            $result = $this->retryable->setMaxRetries(10);

            expect($result)->toBe($this->retryable); // fluent interface
            expect($this->retryable->getRetryConfig()['times'])->toBe(10);
        });

        it('allows setting base delay', function () {
            $this->retryable->setBaseDelayMs(200);
            $delay = $this->retryable->calculateBackoffDelay(1);

            // Base 200ms with jitter: between 160ms and 240ms
            expect($delay)->toBeGreaterThanOrEqual(160)
                ->and($delay)->toBeLessThanOrEqual(240);
        });

        it('allows setting max delay', function () {
            $this->retryable->setMaxDelayMs(10000);

            // Large attempt should be capped at new max + up to 20% jitter = 12000ms
            $delay = $this->retryable->calculateBackoffDelay(100);

            expect($delay)->toBeLessThanOrEqual(12000);
        });

        it('allows setting retryable statuses', function () {
            $this->retryable->setRetryableStatuses([500, 503]);

            $response200 = Mockery::mock(Response::class);
            $response200->shouldReceive('status')->andReturn(429);

            $exception = Mockery::mock(RequestException::class);
            $exception->response = $response200;

            // 429 should no longer be retryable
            expect($this->retryable->shouldRetry($exception))->toBeFalse();
        });
    });

    describe('getRetryConfig', function () {
        it('returns array with expected keys', function () {
            $config = $this->retryable->getRetryConfig();

            expect($config)->toHaveKeys(['times', 'sleepMilliseconds', 'when', 'throw']);
        });

        it('returns correct retry count', function () {
            $config = $this->retryable->getRetryConfig();

            expect($config['times'])->toBe(4);
        });

        it('returns callable for sleepMilliseconds', function () {
            $config = $this->retryable->getRetryConfig();

            expect($config['sleepMilliseconds'])->toBeInstanceOf(\Closure::class);

            // Test that it returns a delay value
            $delay = ($config['sleepMilliseconds'])(1, new ConnectionException('test'));
            expect($delay)->toBeInt()->toBeGreaterThan(0);
        });

        it('returns callable for when', function () {
            $config = $this->retryable->getRetryConfig();

            expect($config['when'])->toBeInstanceOf(\Closure::class);

            // Test that it correctly determines retry eligibility
            $shouldRetry = ($config['when'])(new ConnectionException('test'));
            expect($shouldRetry)->toBeTrue();
        });

        it('returns false for throw key', function () {
            $config = $this->retryable->getRetryConfig();

            expect($config['throw'])->toBeFalse();
        });
    });
});
