<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Circuit Breaker pattern implementation for preventing cascading failures.
 *
 * States:
 * - CLOSED: Normal operation, requests pass through
 * - OPEN: Requests fail fast after threshold failures, preventing cascading failures
 * - HALF_OPEN: Trial requests sent to check if service has recovered
 */
class CircuitBreaker
{
    private const string CACHE_KEY_PREFIX = 'circuit_breaker:';

    private const string STATE_CLOSED = 'closed';

    private const string STATE_OPEN = 'open';

    private const string STATE_HALF_OPEN = 'half_open';

    /**
     * @param  string  $serviceName  Unique name for this circuit breaker instance
     * @param  int  $failureThreshold  Number of failures before opening circuit
     * @param  int  $recoveryTimeout  Seconds to wait before attempting half-open
     * @param  int  $successThreshold  Number of successes to close circuit from half-open
     */
    public function __construct(
        private readonly string $serviceName,
        private readonly int $failureThreshold = 5,
        private readonly int $recoveryTimeout = 60,
        private readonly int $successThreshold = 2,
    ) {}

    /**
     * Check if the circuit allows requests to pass through.
     */
    public function isClosed(): bool
    {
        $state = $this->getState();

        if ($state === self::STATE_OPEN) {
            // Check if recovery timeout has passed
            $lastFailure = $this->getLastFailureTime();

            if ($lastFailure !== null && (time() - $lastFailure) >= $this->recoveryTimeout) {
                $this->transitionToHalfOpen();

                return true;
            }

            return false;
        }

        return true;
    }

    /**
     * Record a successful request.
     */
    public function recordSuccess(): void
    {
        $state = $this->getState();

        if ($state === self::STATE_HALF_OPEN) {
            $successCount = $this->incrementSuccessCount();

            if ($successCount >= $this->successThreshold) {
                $this->closeCircuit();
            }
        } else {
            // In closed state, reset failure count on success
            $this->resetFailureCount();
        }
    }

    /**
     * Record a failed request.
     */
    public function recordFailure(): void
    {
        $state = $this->getState();

        if ($state === self::STATE_HALF_OPEN) {
            // Failure in half-open state immediately reopens circuit
            $this->openCircuit();
        } else {
            $failureCount = $this->incrementFailureCount();

            if ($failureCount >= $this->failureThreshold) {
                $this->openCircuit();
            }
        }
    }

    /**
     * Get current circuit state.
     */
    public function getCurrentState(): string
    {
        return $this->getState();
    }

    /**
     * Manually reset the circuit to closed state.
     */
    public function reset(): void
    {
        $this->closeCircuit();
    }

    /**
     * Get failure count for monitoring/debugging.
     */
    public function getFailureCount(): int
    {
        return (int) Cache::get($this->getFailureCountKey(), 0);
    }

    /**
     * Get success count for monitoring/debugging.
     */
    public function getSuccessCount(): int
    {
        return (int) Cache::get($this->getSuccessCountKey(), 0);
    }

    private function getState(): string
    {
        return Cache::get($this->getStateKey(), self::STATE_CLOSED);
    }

    private function openCircuit(): void
    {
        Cache::put($this->getStateKey(), self::STATE_OPEN, now()->addDay());
        Cache::put($this->getLastFailureTimeKey(), time(), now()->addDay());
        Cache::forget($this->getFailureCountKey());
        Cache::forget($this->getSuccessCountKey());
    }

    private function closeCircuit(): void
    {
        Cache::put($this->getStateKey(), self::STATE_CLOSED, now()->addDay());
        Cache::forget($this->getFailureCountKey());
        Cache::forget($this->getSuccessCountKey());
        Cache::forget($this->getLastFailureTimeKey());
    }

    private function transitionToHalfOpen(): void
    {
        Cache::put($this->getStateKey(), self::STATE_HALF_OPEN, now()->addDay());
        Cache::forget($this->getSuccessCountKey());
    }

    private function incrementFailureCount(): int
    {
        $key = $this->getFailureCountKey();
        $count = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $count, now()->addMinutes(5));

        return $count;
    }

    private function incrementSuccessCount(): int
    {
        $key = $this->getSuccessCountKey();
        $count = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $count, now()->addMinutes(5));

        return $count;
    }

    private function resetFailureCount(): void
    {
        Cache::forget($this->getFailureCountKey());
    }

    private function getLastFailureTime(): ?int
    {
        return Cache::get($this->getLastFailureTimeKey());
    }

    private function getStateKey(): string
    {
        return self::CACHE_KEY_PREFIX.$this->serviceName.':state';
    }

    private function getFailureCountKey(): string
    {
        return self::CACHE_KEY_PREFIX.$this->serviceName.':failures';
    }

    private function getSuccessCountKey(): string
    {
        return self::CACHE_KEY_PREFIX.$this->serviceName.':successes';
    }

    private function getLastFailureTimeKey(): string
    {
        return self::CACHE_KEY_PREFIX.$this->serviceName.':last_failure';
    }
}
