<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CloudCredential;
use App\Models\TunnelConfig;
use App\Services\Traits\RetryableTrait;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use VibecodePC\Common\DTOs\DeviceStatusResult;

class CloudApiClient
{
    use RetryableTrait;

    private const int FAILURE_THRESHOLD = 5;

    private const int CIRCUIT_TIMEOUT_MINUTES = 1;

    private const string CIRCUIT_CACHE_KEY = 'cloud_api_circuit_state';

    public function __construct(
        private readonly string $cloudUrl,
    ) {
        $this->initCircuitBreaker();
    }

    /**
     * Initialize circuit breaker state if not exists.
     */
    private function initCircuitBreaker(): void
    {
        if (! Cache::has(self::CIRCUIT_CACHE_KEY)) {
            $this->resetCircuitBreaker();
        }
    }

    /**
     * Get the current circuit breaker state.
     *
     * @return array{state: string, failure_count: int, success_count: int, is_closed: bool, last_failure_time: ?int}
     */
    public function getCircuitBreakerState(): array
    {
        $this->initCircuitBreaker();

        return Cache::get(self::CIRCUIT_CACHE_KEY);
    }

    /**
     * Reset the circuit breaker to closed state.
     */
    public function resetCircuitBreaker(): void
    {
        Cache::put(self::CIRCUIT_CACHE_KEY, [
            'state' => 'closed',
            'failure_count' => 0,
            'success_count' => 0,
            'is_closed' => true,
            'last_failure_time' => null,
        ], now()->addMinutes(self::CIRCUIT_TIMEOUT_MINUTES + 1));
    }

    /**
     * Record a failure and potentially open the circuit.
     */
    private function recordFailure(): void
    {
        $state = $this->getCircuitBreakerState();
        $state['failure_count']++;
        $state['last_failure_time'] = time();

        if ($state['failure_count'] >= self::FAILURE_THRESHOLD) {
            $state['state'] = 'open';
            $state['is_closed'] = false;
        }

        Cache::put(self::CIRCUIT_CACHE_KEY, $state, now()->addMinutes(self::CIRCUIT_TIMEOUT_MINUTES + 1));
    }

    /**
     * Record a success and reset failure count.
     */
    private function recordSuccess(): void
    {
        $state = $this->getCircuitBreakerState();
        $state['success_count']++;

        // Reset failure count on success
        if ($state['failure_count'] > 0) {
            $state['failure_count'] = 0;
        }

        // If circuit was open, close it
        if ($state['state'] === 'open') {
            $state['state'] = 'closed';
            $state['is_closed'] = true;
        }

        Cache::put(self::CIRCUIT_CACHE_KEY, $state, now()->addMinutes(self::CIRCUIT_TIMEOUT_MINUTES + 1));
    }

    /**
     * Check if the circuit is open and should fail fast.
     *
     * @throws \RuntimeException
     */
    private function checkCircuit(): void
    {
        $state = $this->getCircuitBreakerState();

        if ($state['state'] === 'open') {
            // Check if timeout has passed to half-open
            if ($state['last_failure_time'] !== null) {
                $elapsed = time() - $state['last_failure_time'];
                if ($elapsed >= self::CIRCUIT_TIMEOUT_MINUTES * 60) {
                    // Transition to half-open and allow one request
                    $state['state'] = 'half-open';
                    $state['is_closed'] = false;
                    Cache::put(self::CIRCUIT_CACHE_KEY, $state, now()->addMinutes(self::CIRCUIT_TIMEOUT_MINUTES + 1));

                    return;
                }
            }

            throw new \RuntimeException('Circuit breaker is OPEN: too many failures');
        }
    }

    /**
     * Check if Cloud API calls should be made.
     * Returns false when tunnel is skipped or not configured.
     */
    private function shouldMakeApiCalls(): bool
    {
        $tunnelConfig = TunnelConfig::current();

        // Skip API calls if tunnel was explicitly skipped or no config exists
        if ($tunnelConfig === null || $tunnelConfig->isSkipped()) {
            return false;
        }

        return true;
    }

    public function getDeviceStatus(string $deviceId): DeviceStatusResult
    {
        $this->checkCircuit();

        try {
            $response = $this->http()
                ->get("/api/devices/{$deviceId}/status");

            $response->throw();
            $this->recordSuccess();

            return DeviceStatusResult::fromArray($response->json());
        } catch (ConnectionException|RequestException $e) {
            $this->recordFailure();
            throw $e;
        }
    }

    public function registerDevice(array $deviceInfo): void
    {
        $this->checkCircuit();

        try {
            $response = $this->http()
                ->post('/api/devices/register', $deviceInfo);

            $response->throw();
            $this->recordSuccess();
        } catch (ConnectionException|RequestException $e) {
            $this->recordFailure();
            throw $e;
        }
    }

    public function checkSubdomainAvailability(string $subdomain): bool
    {
        $this->checkCircuit();

        try {
            $response = $this->http()
                ->get("/api/subdomains/{$subdomain}/availability");

            $response->throw();
            $this->recordSuccess();

            return $response->json('available', false);
        } catch (ConnectionException|RequestException $e) {
            $this->recordFailure();
            throw $e;
        }
    }

    /**
     * Provision a Cloudflare tunnel via the cloud API.
     *
     * @return array{tunnel_id: string, tunnel_token: string}
     */
    public function provisionTunnel(string $deviceId, string $subdomain): array
    {
        if (! $this->shouldMakeApiCalls()) {
            throw new \RuntimeException('Cannot provision tunnel: tunnel configuration is skipped or not set up');
        }

        $response = $this->authenticatedHttp()
            ->post("/api/devices/{$deviceId}/tunnel/provision", [
                'subdomain' => $subdomain,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "HTTP request returned status code {$response->status()}: {$response->body()}"
            );
        }

        return $response->json();
    }

    /**
     * Register a tunnel URL (quick or permanent) with the cloud.
     */
    public function registerTunnelUrl(string $deviceId, string $tunnelUrl): void
    {
        if (! $this->shouldMakeApiCalls()) {
            Log::debug('Skipped registering tunnel URL: tunnel is skipped or not configured');

            return;
        }

        $this->authenticatedHttp()
            ->post("/api/devices/{$deviceId}/tunnel/register", [
                'tunnel_url' => $tunnelUrl,
            ])
            ->throw();
    }

    public function reconfigureTunnel(string $deviceId, int $port): void
    {
        if (! $this->shouldMakeApiCalls()) {
            Log::debug('Skipped reconfiguring tunnel: tunnel is skipped or not configured');

            return;
        }

        $this->authenticatedHttp()
            ->post("/api/devices/{$deviceId}/tunnel/reconfigure", [
                'port' => $port,
            ])
            ->throw();
    }

    /**
     * Push a full set of ingress rules to the remote tunnel configuration.
     *
     * @param  array<int, array{service: string, path?: string}>  $ingress
     */
    public function reconfigureTunnelIngress(string $deviceId, array $ingress): void
    {
        if (! $this->shouldMakeApiCalls()) {
            Log::debug('Skipped reconfiguring tunnel ingress: tunnel is skipped or not configured');

            return;
        }

        $this->authenticatedHttp()
            ->post("/api/devices/{$deviceId}/tunnel/reconfigure", [
                'ingress' => $ingress,
            ])
            ->throw();
    }

    /**
     * @param  array{cpu_percent: float, ram_used_mb: int, ram_total_mb: int, disk_used_gb: float, disk_total_gb: float, temperature_c: float|null, running_projects: int, tunnel_active: bool, firmware_version: string, quick_tunnels?: array, analytics?: array<string, int>}  $metrics
     */
    public function sendHeartbeat(string $deviceId, array $metrics): void
    {
        if (! $this->shouldMakeApiCalls()) {
            Log::debug('Skipped sending heartbeat: tunnel is skipped or not configured');

            return;
        }

        // Check circuit breaker - skip silently if open
        $state = $this->getCircuitBreakerState();
        if ($state['state'] === 'open') {
            Log::debug('Skipped sending heartbeat: circuit breaker is open');

            return;
        }

        try {
            $payload = [
                'cpu_percent' => $metrics['cpu_percent'],
                'cpu_temp' => $metrics['temperature_c'],
                'ram_used_mb' => $metrics['ram_used_mb'],
                'ram_total_mb' => $metrics['ram_total_mb'],
                'disk_used_gb' => $metrics['disk_used_gb'],
                'disk_total_gb' => $metrics['disk_total_gb'],
                'running_projects' => $metrics['running_projects'],
                'tunnel_active' => $metrics['tunnel_active'],
                'firmware_version' => $metrics['firmware_version'],
            ];

            if (! empty($metrics['quick_tunnels'])) {
                $payload['quick_tunnels'] = $metrics['quick_tunnels'];
            }

            if (! empty($metrics['analytics'])) {
                $payload['analytics'] = $metrics['analytics'];
            }

            $this->authenticatedHttp()
                ->post("/api/devices/{$deviceId}/heartbeat", $payload)
                ->throw();

            $this->recordSuccess();
        } catch (ConnectionException|RequestException $e) {
            $this->recordFailure();
            Log::warning('Heartbeat failed: '.$e->getMessage());
        }
    }

    /**
     * @return array{period: string, routes: array<int, array{project: string, requests: int, avg_response_time_ms: int}>}|null
     */
    public function fetchTrafficStats(string $deviceId): ?array
    {
        if (! $this->shouldMakeApiCalls()) {
            return null;
        }

        try {
            $response = $this->authenticatedHttp()
                ->get("/api/devices/{$deviceId}/stats");

            if ($response->successful()) {
                return $response->json();
            }
        } catch (ConnectionException|RequestException $e) {
            Log::warning('Failed to fetch traffic stats: '.$e->getMessage());
        }

        return null;
    }

    /**
     * @return array{config_version: int, subdomain: string|null, tunnel_token?: string}|null
     */
    public function getDeviceConfig(string $deviceId): ?array
    {
        if (! $this->shouldMakeApiCalls()) {
            return null;
        }

        try {
            $response = $this->authenticatedHttp()
                ->get("/api/devices/{$deviceId}/config");

            if ($response->successful()) {
                return $response->json('config');
            }
        } catch (ConnectionException|RequestException $e) {
            Log::warning('Failed to fetch device config: '.$e->getMessage());
        }

        return null;
    }

    private function http(): PendingRequest
    {
        $retryConfig = $this->getRetryConfig();
        $request = Http::baseUrl($this->cloudUrl)
            ->acceptJson()
            ->timeout(config('vibecodepc.http_client.timeout.default'))
            ->retry(
                times: $retryConfig['times'],
                sleepMilliseconds: $retryConfig['sleepMilliseconds'],
                when: $retryConfig['when'],
                throw: $retryConfig['throw']
            );

        if (config('app.env') === 'local') {
            $request->withoutVerifying();
        }

        return $request;
    }

    private function authenticatedHttp(): PendingRequest
    {
        $credential = CloudCredential::current();

        $request = $this->http()->timeout(config('vibecodepc.http_client.timeout.authenticated'));

        if ($credential) {
            $request->withToken($credential->getToken());
        }

        return $request;
    }
}
