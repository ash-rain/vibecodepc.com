<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CloudflareTunnelService
{
    private readonly string $apiToken;

    private readonly string $accountId;

    private readonly string $zoneId;

    public function __construct()
    {
        $this->apiToken = config('cloudflare.api_token');
        $this->accountId = config('cloudflare.account_id');
        $this->zoneId = config('cloudflare.zone_id');
    }

    /**
     * Find an existing tunnel by name, or return null.
     *
     * @return array{id: string, name: string}|null
     */
    public function findTunnelByName(string $name): ?array
    {
        $response = $this->http()
            ->get("accounts/{$this->accountId}/cfd_tunnel", [
                'name' => $name,
                'is_deleted' => 'false',
            ]);

        if (! $response->successful()) {
            return null;
        }

        $tunnels = $response->json('result', []);

        foreach ($tunnels as $tunnel) {
            if ($tunnel['name'] === $name) {
                return [
                    'id' => $tunnel['id'],
                    'name' => $tunnel['name'],
                ];
            }
        }

        return null;
    }

    /**
     * Create a new Cloudflare tunnel, or return an existing one with the same name.
     *
     * @return array{id: string, name: string}
     */
    public function createTunnel(string $name): array
    {
        $existing = $this->findTunnelByName($name);

        if ($existing !== null) {
            return $existing;
        }

        $response = $this->http()
            ->post("accounts/{$this->accountId}/cfd_tunnel", [
                'name' => $name,
                'config_src' => 'cloudflare',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to create Cloudflare tunnel: ' . $response->body());
        }

        $result = $response->json('result');

        return [
            'id' => $result['id'],
            'name' => $result['name'],
        ];
    }

    /**
     * Get the connector token for a tunnel.
     */
    public function getTunnelToken(string $tunnelId): string
    {
        $response = $this->http()
            ->get("accounts/{$this->accountId}/cfd_tunnel/{$tunnelId}/token");

        if (! $response->successful()) {
            throw new RuntimeException('Failed to get tunnel token: ' . $response->body());
        }

        return $response->json('result');
    }

    /**
     * Configure tunnel ingress rules remotely.
     */
    public function configureTunnelIngress(string $tunnelId, string $hostname, int $port = 8001): void
    {
        $response = $this->http()
            ->put("accounts/{$this->accountId}/cfd_tunnel/{$tunnelId}/configurations", [
                'config' => [
                    'ingress' => [
                        [
                            'hostname' => $hostname,
                            'service' => "http://localhost:{$port}",
                        ],
                        [
                            'service' => 'http_status:404',
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to configure tunnel ingress: ' . $response->body());
        }
    }

    /**
     * Create or update a CNAME DNS record pointing to the tunnel.
     */
    public function createDnsRecord(string $subdomain, string $tunnelId): void
    {
        $fqdn = "{$subdomain}.vibecodepc.com";
        $content = "{$tunnelId}.cfargotunnel.com";

        $existingId = $this->findDnsRecord($fqdn);

        if ($existingId !== null) {
            $response = $this->http()
                ->put("zones/{$this->zoneId}/dns_records/{$existingId}", [
                    'type' => 'CNAME',
                    'name' => $fqdn,
                    'content' => $content,
                    'proxied' => true,
                ]);
        } else {
            $response = $this->http()
                ->post("zones/{$this->zoneId}/dns_records", [
                    'type' => 'CNAME',
                    'name' => $fqdn,
                    'content' => $content,
                    'proxied' => true,
                ]);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Failed to create DNS record: ' . $response->body());
        }
    }

    /**
     * Find an existing DNS record by name, returning its ID or null.
     */
    public function findDnsRecord(string $name): ?string
    {
        $response = $this->http()
            ->get("zones/{$this->zoneId}/dns_records", [
                'name' => $name,
                'type' => 'CNAME',
            ]);

        if (! $response->successful()) {
            return null;
        }

        $records = $response->json('result', []);

        return ! empty($records) ? $records[0]['id'] : null;
    }

    /**
     * Delete a Cloudflare tunnel.
     */
    public function deleteTunnel(string $tunnelId): void
    {
        $response = $this->http()
            ->delete("accounts/{$this->accountId}/cfd_tunnel/{$tunnelId}");

        if (! $response->successful()) {
            throw new RuntimeException('Failed to delete tunnel: ' . $response->body());
        }
    }

    /**
     * Delete a DNS record by ID.
     */
    public function deleteDnsRecord(string $recordId): void
    {
        $response = $this->http()
            ->delete("zones/{$this->zoneId}/dns_records/{$recordId}");

        if (! $response->successful()) {
            throw new RuntimeException('Failed to delete DNS record: ' . $response->body());
        }
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl('https://api.cloudflare.com/client/v4/')
            ->withToken($this->apiToken)
            ->acceptJson()
            ->asJson()
            ->timeout(15);
    }
}
