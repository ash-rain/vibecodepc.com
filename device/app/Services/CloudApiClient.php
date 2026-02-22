<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
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
}
