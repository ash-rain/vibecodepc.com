<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CloudCredential;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use VibecodePC\Common\DTOs\DeviceStatusResult;

class CloudApiClient
{
    public function __construct(
        private readonly string $cloudUrl,
    ) {}

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

    public function reconfigureTunnel(string $deviceId, int $port): void
    {
        $this->authenticatedHttp()
            ->post("/api/devices/{$deviceId}/tunnel/reconfigure", [
                'port' => $port,
            ])
            ->throw();
    }

    /**
     * @param  array{cpu_percent: float, ram_used_mb: int, ram_total_mb: int, disk_used_gb: float, disk_total_gb: float, temperature_c: float|null, running_projects: int, tunnel_active: bool, firmware_version: string}  $metrics
     */
    public function sendHeartbeat(string $deviceId, array $metrics): void
    {
        try {
            $this->authenticatedHttp()
                ->post("/api/devices/{$deviceId}/heartbeat", [
                    'cpu_percent' => $metrics['cpu_percent'],
                    'cpu_temp' => $metrics['temperature_c'],
                    'ram_used_mb' => $metrics['ram_used_mb'],
                    'ram_total_mb' => $metrics['ram_total_mb'],
                    'disk_used_gb' => $metrics['disk_used_gb'],
                    'disk_total_gb' => $metrics['disk_total_gb'],
                    'running_projects' => $metrics['running_projects'],
                    'tunnel_active' => $metrics['tunnel_active'],
                    'firmware_version' => $metrics['firmware_version'],
                ])
                ->throw();
        } catch (\Throwable $e) {
            Log::warning('Heartbeat failed: '.$e->getMessage());
        }
    }

    /**
     * @return array{period: string, routes: array<int, array{project: string, requests: int, avg_response_time_ms: int}>}|null
     */
    public function fetchTrafficStats(string $deviceId): ?array
    {
        try {
            $response = $this->authenticatedHttp()
                ->get("/api/devices/{$deviceId}/stats");

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch traffic stats: '.$e->getMessage());
        }

        return null;
    }

    /**
     * @return array{config_version: int, subdomain: string|null, tunnel_token?: string}|null
     */
    public function getDeviceConfig(string $deviceId): ?array
    {
        try {
            $response = $this->authenticatedHttp()
                ->get("/api/devices/{$deviceId}/config");

            if ($response->successful()) {
                return $response->json('config');
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch device config: '.$e->getMessage());
        }

        return null;
    }

    private function http(): PendingRequest
    {
        $request = Http::baseUrl($this->cloudUrl)
            ->acceptJson()
            ->timeout(10);

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
