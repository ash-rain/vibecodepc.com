<?php

declare(strict_types=1);

namespace App\Services\Traits;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

trait RetryableTrait
{
    /**
     * Maximum number of retry attempts.
     */
    protected int $maxRetries = 4;

    /**
     * Base delay in milliseconds for exponential backoff.
     */
    protected int $baseDelayMs = 100;

    /**
     * Maximum delay in milliseconds for exponential backoff.
     */
    protected int $maxDelayMs = 5000;

    /**
     * HTTP status codes that indicate transient failures and should be retried.
     *
     * @var array<int>
     */
    protected array $retryableStatuses = [408, 429, 500, 502, 503, 504];

    /**
     * Determine if an exception represents a transient failure that should be retried.
     */
    public function shouldRetry(\Throwable $exception): bool
    {
        // Connection errors (network issues, timeouts)
        if ($exception instanceof ConnectionException) {
            return true;
        }

        // Request exceptions with retryable status codes
        if ($exception instanceof RequestException && $exception->response !== null) {
            return in_array($exception->response->status(), $this->retryableStatuses, true);
        }

        return false;
    }

    /**
     * Calculate exponential backoff delay in milliseconds.
     * Uses exponential backoff with jitter: min(maxDelay, baseDelay * 2^attempt)
     */
    public function calculateBackoffDelay(int $attempt): int
    {
        $exponentialDelay = $this->baseDelayMs * (2 ** ($attempt - 1));
        $cappedDelay = min($exponentialDelay, $this->maxDelayMs);

        // Add random jitter (±20%) to prevent thundering herd
        $jitter = (int) ($cappedDelay * 0.2 * (mt_rand() / mt_getrandmax() * 2 - 1));

        return max(0, $cappedDelay + $jitter);
    }

    /**
     * Set the maximum number of retry attempts.
     */
    public function setMaxRetries(int $maxRetries): self
    {
        $this->maxRetries = $maxRetries;

        return $this;
    }

    /**
     * Set the base delay in milliseconds.
     */
    public function setBaseDelayMs(int $baseDelayMs): self
    {
        $this->baseDelayMs = $baseDelayMs;

        return $this;
    }

    /**
     * Set the maximum delay in milliseconds.
     */
    public function setMaxDelayMs(int $maxDelayMs): self
    {
        $this->maxDelayMs = $maxDelayMs;

        return $this;
    }

    /**
     * Set the HTTP status codes that should trigger retries.
     *
     * @param  array<int>  $statuses
     */
    public function setRetryableStatuses(array $statuses): self
    {
        $this->retryableStatuses = $statuses;

        return $this;
    }

    /**
     * Get the configuration for Laravel HTTP client retry method.
     *
     * @return array{times: int, sleepMilliseconds: \Closure, when: \Closure, throw: bool}
     */
    public function getRetryConfig(): array
    {
        return [
            'times' => $this->maxRetries,
            'sleepMilliseconds' => fn (int $attempt, \Throwable $exception) => $this->calculateBackoffDelay($attempt),
            'when' => fn (\Throwable $exception) => $this->shouldRetry($exception),
            'throw' => false,
        ];
    }
}
