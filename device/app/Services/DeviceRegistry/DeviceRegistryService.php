<?php

declare(strict_types=1);

namespace App\Services\DeviceRegistry;

use App\Services\CloudApiClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;
use VibecodePC\Common\DTOs\DeviceInfo;
use VibecodePC\Common\DTOs\DeviceStatusResult;

class DeviceRegistryService
{
    private const int MAX_RETRIES = 3;

    private const int BASE_DELAY_MS = 100;

    private const int MAX_DELAY_MS = 2000;

    public function __construct(
        private readonly CloudApiClient $cloudApi,
    ) {}

    /**
     * Register a device with the cloud API with retry logic.
     *
     * @throws RuntimeException When registration fails after all retries
     */
    public function registerDeviceWithRetry(DeviceInfo $deviceInfo): void
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $this->cloudApi->registerDevice($deviceInfo->toArray());
                Log::info('Device registered successfully with cloud', [
                    'device_id' => $deviceInfo->id,
                    'attempt' => $attempt,
                ]);

                return;
            } catch (ConnectionException|RequestException $e) {
                $lastException = $e;

                if (! $this->shouldRetry($e) || $attempt === self::MAX_RETRIES) {
                    break;
                }

                $delay = $this->calculateBackoffDelay($attempt);
                Log::warning('Device registration attempt failed, retrying', [
                    'device_id' => $deviceInfo->id,
                    'attempt' => $attempt,
                    'next_attempt' => $attempt + 1,
                    'delay_ms' => $delay,
                    'error' => $e->getMessage(),
                ]);

                usleep($delay * 1000);
            } catch (Throwable $e) {
                $lastException = $e;

                break;
            }
        }

        Log::error('Device registration failed after all retry attempts', [
            'device_id' => $deviceInfo->id,
            'attempts' => self::MAX_RETRIES,
            'error' => $lastException?->getMessage(),
        ]);

        throw new RuntimeException(
            sprintf('Failed to register device after %d attempts: %s', self::MAX_RETRIES, $lastException?->getMessage()),
            0,
            $lastException
        );
    }

    /**
     * Get device status from the cloud API with retry logic.
     *
     * @throws RuntimeException When status check fails after all retries
     */
    public function getDeviceStatusWithRetry(string $deviceId): DeviceStatusResult
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $result = $this->cloudApi->getDeviceStatus($deviceId);
                Log::debug('Device status retrieved successfully', [
                    'device_id' => $deviceId,
                    'attempt' => $attempt,
                ]);

                return $result;
            } catch (ConnectionException|RequestException $e) {
                $lastException = $e;

                if (! $this->shouldRetry($e) || $attempt === self::MAX_RETRIES) {
                    break;
                }

                $delay = $this->calculateBackoffDelay($attempt);
                Log::warning('Device status check attempt failed, retrying', [
                    'device_id' => $deviceId,
                    'attempt' => $attempt,
                    'next_attempt' => $attempt + 1,
                    'delay_ms' => $delay,
                    'error' => $e->getMessage(),
                ]);

                usleep($delay * 1000);
            } catch (Throwable $e) {
                $lastException = $e;

                break;
            }
        }

        Log::error('Device status check failed after all retry attempts', [
            'device_id' => $deviceId,
            'attempts' => self::MAX_RETRIES,
            'error' => $lastException?->getMessage(),
        ]);

        throw new RuntimeException(
            sprintf('Failed to get device status after %d attempts: %s', self::MAX_RETRIES, $lastException?->getMessage()),
            0,
            $lastException
        );
    }

    /**
     * Check if a device is paired with the cloud.
     * Returns null if the check fails (non-critical operation).
     */
    public function checkPairingStatusSafe(string $deviceId): ?DeviceStatusResult
    {
        try {
            return $this->getDeviceStatusWithRetry($deviceId);
        } catch (Throwable $e) {
            Log::warning('Failed to check pairing status (non-critical)', [
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Attempt to register a device, but don't throw on failure (non-critical operation).
     */
    public function registerDeviceSafe(DeviceInfo $deviceInfo): bool
    {
        try {
            $this->registerDeviceWithRetry($deviceInfo);

            return true;
        } catch (Throwable $e) {
            Log::warning('Failed to register device (non-critical)', [
                'device_id' => $deviceInfo->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Determine if an exception represents a transient failure that should be retried.
     */
    private function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        $retryableStatuses = [408, 429, 500, 502, 503, 504];

        if ($exception instanceof RequestException && $exception->response !== null) {
            return in_array($exception->response->status(), $retryableStatuses, true);
        }

        return false;
    }

    /**
     * Calculate exponential backoff delay in milliseconds.
     * Uses exponential backoff with jitter: min(maxDelay, baseDelay * 2^attempt)
     */
    private function calculateBackoffDelay(int $attempt): int
    {
        $exponentialDelay = self::BASE_DELAY_MS * (2 ** ($attempt - 1));
        $cappedDelay = min($exponentialDelay, self::MAX_DELAY_MS);

        $jitter = (int) ($cappedDelay * 0.2 * (mt_rand() / mt_getrandmax() * 2 - 1));

        return max(0, $cappedDelay + $jitter);
    }
}
