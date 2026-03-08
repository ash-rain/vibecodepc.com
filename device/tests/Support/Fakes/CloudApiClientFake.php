<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Services\CloudApiClient;
use VibecodePC\Common\DTOs\DeviceStatusResult;

/**
 * Fake implementation of CloudApiClient for testing.
 * Provides predictable responses and tracks method calls.
 */
class CloudApiClientFake extends CloudApiClient
{
    /** @var array<string, mixed> */
    private array $responses = [];

    /** @var array<int, array{method: string, args: array}> */
    private array $calls = [];

    private bool $shouldFail = false;

    private ?\Throwable $exceptionToThrow = null;

    public function __construct()
    {
        // Bypass parent constructor - we don't need cloudUrl for fakes
    }

    /**
     * Set a predefined response for a method.
     */
    public function setResponse(string $method, mixed $response): self
    {
        $this->responses[$method] = $response;

        return $this;
    }

    /**
     * Configure the fake to throw an exception on the next call.
     */
    public function setException(\Throwable $exception): self
    {
        $this->exceptionToThrow = $exception;

        return $this;
    }

    /**
     * Configure the fake to fail with a runtime exception.
     */
    public function setToFail(string $message = 'Fake API failure'): self
    {
        $this->shouldFail = true;
        $this->setException(new \RuntimeException($message));

        return $this;
    }

    /**
     * Reset the fake to its default state.
     */
    public function reset(): self
    {
        $this->responses = [];
        $this->calls = [];
        $this->shouldFail = false;
        $this->exceptionToThrow = null;

        return $this;
    }

    /**
     * Get all recorded calls.
     *
     * @return array<int, array{method: string, args: array}>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    /**
     * Check if a method was called.
     */
    public function wasCalled(string $method): bool
    {
        foreach ($this->calls as $call) {
            if ($call['method'] === $method) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get calls for a specific method.
     *
     * @return array<int, array{method: string, args: array}>
     */
    public function getCallsForMethod(string $method): array
    {
        return array_filter(
            $this->calls,
            fn (array $call) => $call['method'] === $method
        );
    }

    /**
     * Get the last call made.
     *
     * @return array{method: string, args: array}|null
     */
    public function getLastCall(): ?array
    {
        $calls = $this->calls;

        return empty($calls) ? null : end($calls);
    }

    public function getDeviceStatus(string $deviceId): DeviceStatusResult
    {
        $this->recordCall(__FUNCTION__, func_get_args());

        if ($this->exceptionToThrow) {
            throw $this->exceptionToThrow;
        }

        return $this->responses[__FUNCTION__] ?? DeviceStatusResult::fromArray([
            'status' => 'active',
            'tunnel_active' => true,
        ]);
    }

    public function registerDevice(array $deviceInfo): void
    {
        $this->recordCall(__FUNCTION__, func_get_args());

        if ($this->exceptionToThrow) {
            throw $this->exceptionToThrow;
        }
    }

    public function checkSubdomainAvailability(string $subdomain): bool
    {
        $this->recordCall(__FUNCTION__, func_get_args());

        if ($this->exceptionToThrow) {
            throw $this->exceptionToThrow;
        }

        // Default: 'test' subdomains are available, others are not
        return $this->responses[__FUNCTION__] ?? str_starts_with($subdomain, 'test');
    }

    /**
     * Provision a tunnel with a predictable ID.
     *
     * @return array{tunnel_id: string, tunnel_token: string}
     */
    public function provisionTunnel(string $deviceId, string $subdomain): array
    {
        $this->recordCall(__FUNCTION__, func_get_args());

        if ($this->exceptionToThrow) {
            throw $this->exceptionToThrow;
        }

        return $this->responses[__FUNCTION__] ?? [
            'tunnel_id' => 'test-tunnel-999',
            'tunnel_token' => 'test-token-value-'.hash('sha256', $subdomain),
        ];
    }

    public function registerTunnelUrl(string $deviceId, string $tunnelUrl): void
    {
        $this->recordCall(__FUNCTION__, func_get_args());

        if ($this->exceptionToThrow) {
            throw $this->exceptionToThrow;
        }
    }

    public function reconfigureTunnel(string $deviceId, int $port): void
    {
        $this->recordCall(__FUNCTION__, func_get_args());

        if ($this->exceptionToThrow) {
            throw $this->exceptionToThrow;
        }
    }

    /**
     * @param  array<int, array{service: string, path?: string}>  $ingress
     */
    public function reconfigureTunnelIngress(string $deviceId, array $ingress): void
    {
        $this->recordCall(__FUNCTION__, func_get_args());

        if ($this->exceptionToThrow) {
            throw $this->exceptionToThrow;
        }
    }

    /**
     * @param  array{cpu_percent: float, ram_used_mb: int, ram_total_mb: int, disk_used_gb: float, disk_total_gb: float, temperature_c: float|null, running_projects: int, tunnel_active: bool, firmware_version: string, quick_tunnels?: array, analytics?: array<string, int>}  $metrics
     */
    public function sendHeartbeat(string $deviceId, array $metrics): void
    {
        $this->recordCall(__FUNCTION__, func_get_args());

        if ($this->exceptionToThrow) {
            throw $this->exceptionToThrow;
        }
    }

    /**
     * @return array{period: string, routes: array<int, array{project: string, requests: int, avg_response_time_ms: int}>}|null
     */
    public function fetchTrafficStats(string $deviceId): ?array
    {
        $this->recordCall(__FUNCTION__, func_get_args());

        if ($this->exceptionToThrow) {
            throw $this->exceptionToThrow;
        }

        return $this->responses[__FUNCTION__] ?? [
            'period' => '24h',
            'routes' => [],
        ];
    }

    /**
     * @return array{config_version: int, subdomain: string|null, tunnel_token?: string}|null
     */
    public function getDeviceConfig(string $deviceId): ?array
    {
        $this->recordCall(__FUNCTION__, func_get_args());

        if ($this->exceptionToThrow) {
            throw $this->exceptionToThrow;
        }

        return $this->responses[__FUNCTION__] ?? null;
    }

    /**
     * @param  array<int, mixed>  $args
     */
    private function recordCall(string $method, array $args): void
    {
        $this->calls[] = [
            'method' => $method,
            'args' => $args,
        ];
    }
}
