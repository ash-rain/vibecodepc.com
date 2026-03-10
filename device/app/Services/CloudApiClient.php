<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CloudCredential;
use App\Models\TunnelConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use VibecodePC\Common\DTOs\DeviceStatusResult;

class CloudApiClient
{
    private const int MAX_RETRIES = 4;

    private const int BASE_DELAY_MS = 100;

    private const int MAX_DELAY_MS = 5000;

    public function __construct(
        private readonly string $cloudUrl,
    ) {}

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
        $response = $this->http()
            ->get("/api/devices/{$deviceId}/status");

        $response->throw();

        return DeviceStatusResult::fromArray($response->json());
    }

    public function registerDevice(array $deviceInfo): void
    {
        $response = $this->http()
            ->post('/api/devices/register', $deviceInfo);

        $response->throw();
    }

    public function checkSubdomainAvailability(string $subdomain): bool
    {
        $response = $this->http()
            ->get("/api/subdomains/{$subdomain}/availability");

        $response->throw();

        return $response->json('available', false);
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
        } catch (ConnectionException|RequestException $e) {
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

    /**
     * Determine if an exception represents a transient failure that should be retried.
     */
    private function shouldRetry(\Throwable $exception): bool
    {
        // Connection errors (network issues, timeouts)
        if ($exception instanceof ConnectionException) {
            return true;
        }

        // HTTP status codes that indicate transient failures
        $retryableStatuses = [408, 429, 500, 502, 503, 504];

        // Request exceptions with retryable status codes
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

        // Add random jitter (±20%) to prevent thundering herd
        $jitter = (int) ($cappedDelay * 0.2 * (mt_rand() / mt_getrandmax() * 2 - 1));

        return max(0, $cappedDelay + $jitter);
    }

    private function http(): PendingRequest
    {
        $request = Http::baseUrl($this->cloudUrl)
            ->acceptJson()
            ->timeout(10)
            ->retry(
                times: self::MAX_RETRIES,
                sleepMilliseconds: fn (int $attempt, \Throwable $exception) => $this->calculateBackoffDelay($attempt),
                when: fn (\Throwable $exception) => $this->shouldRetry($exception),
                throw: false
            );

        if (config('app.env') === 'local') {
            $request->withoutVerifying();
        }

        return $request;
    }

    private function authenticatedHttp(): PendingRequest
    {
        $credential = CloudCredential::current();

        $request = $this->http()->timeout(30);

        if ($credential) {
            $request->withToken($credential->getToken());
        }

        return $request;
    }
}
