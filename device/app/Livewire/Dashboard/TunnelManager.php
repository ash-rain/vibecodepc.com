<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\CloudCredential;
use App\Models\DeviceState;
use App\Models\Project;
use App\Models\TunnelConfig;
use App\Services\CloudApiClient;
use App\Services\DeviceRegistry\DeviceIdentityService;
use App\Services\Tunnel\TunnelService;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.dashboard', ['title' => 'Tunnels'])]
#[Title('Tunnels — VibeCodePC')]
class TunnelManager extends Component
{
    public bool $tunnelInstalled = false;

    public bool $tunnelRunning = false;

    public bool $tunnelConfigured = false;

    public ?string $subdomain = null;

    public string $error = '';

    public string $newSubdomain = '';

    public bool $subdomainAvailable = false;

    public string $provisionStatus = '';

    public bool $isProvisioning = false;

    /** @var array<int, array{id: int, name: string, slug: string, port: int|null, tunnel_enabled: bool}> */
    public array $projects = [];

    /** @var array<int, array{project: string, requests: int, avg_response_time_ms: int}> */
    public array $trafficStats = [];

    public function mount(TunnelService $tunnelService): void
    {
        $status = $tunnelService->getStatus();
        $this->tunnelInstalled = $status['installed'];
        $this->tunnelRunning = $status['running'];
        $this->tunnelConfigured = $status['configured'];

        $tunnelConfig = TunnelConfig::current();
        $this->subdomain = $tunnelConfig?->subdomain;

        if (! $this->tunnelConfigured) {
            $username = CloudCredential::current()?->cloud_username;

            if ($username) {
                $this->newSubdomain = $username;
                $this->subdomainAvailable = true;
            }
        }

        $this->loadProjects();
        $this->loadTrafficStats();
    }

    public function toggleProjectTunnel(int $projectId): void
    {
        $project = Project::findOrFail($projectId);
        $project->update([
            'tunnel_enabled' => ! $project->tunnel_enabled,
            'tunnel_subdomain_path' => ! $project->tunnel_enabled ? $project->slug : null,
        ]);

        $this->loadProjects();
    }

    public function restartTunnel(
        TunnelService $tunnelService,
        CloudApiClient $cloudApi,
        DeviceIdentityService $identity,
    ): void {
        $this->error = '';

        $this->syncIngressConfig($cloudApi, $identity);

        $stopError = $tunnelService->stop();

        if ($stopError !== null) {
            $tunnelService->cleanup();
            $this->error = $stopError;
            $this->tunnelRunning = $tunnelService->isRunning();

            return;
        }

        $startError = $tunnelService->start();

        if ($startError !== null) {
            $this->error = $startError;
        }

        $this->tunnelRunning = $tunnelService->isRunning();
        $this->tunnelConfigured = $tunnelService->hasCredentials();
    }

    public function checkAvailability(CloudApiClient $cloudApi): void
    {
        $this->error = '';
        $this->subdomainAvailable = false;
        $this->provisionStatus = '';

        $this->validate([
            'newSubdomain' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z][a-z0-9-]*[a-z0-9]$/'],
        ], [
            'newSubdomain.regex' => 'Subdomain must start with a letter, use lowercase alphanumeric and hyphens only.',
        ]);

        try {
            $this->subdomainAvailable = $cloudApi->checkSubdomainAvailability($this->newSubdomain);
            $this->provisionStatus = $this->subdomainAvailable
                ? "{$this->newSubdomain}." . config('vibecodepc.cloud_domain') . ' is available!'
                : 'This subdomain is taken. Try another.';
        } catch (\Throwable $e) {
            $this->provisionStatus = 'Could not check availability. Is the device online?';
            Log::warning('Subdomain availability check failed', ['error' => $e->getMessage()]);
        }
    }

    public function provisionTunnel(
        CloudApiClient $cloudApi,
        DeviceIdentityService $identity,
        TunnelService $tunnelService,
    ): void {
        if (! $this->subdomainAvailable) {
            return;
        }

        $this->error = '';
        $this->isProvisioning = true;
        $this->provisionStatus = 'Provisioning tunnel...';

        try {
            $deviceId = $identity->getDeviceInfo()->id;
            $result = $cloudApi->provisionTunnel($deviceId, $this->newSubdomain);
        } catch (\Throwable $e) {
            $this->isProvisioning = false;
            $this->error = 'Failed to provision tunnel: ' . $e->getMessage();
            $this->provisionStatus = '';

            return;
        }

        TunnelConfig::updateOrCreate(
            ['subdomain' => $this->newSubdomain],
            [
                'tunnel_id' => $result['tunnel_id'],
                'tunnel_token_encrypted' => $result['tunnel_token'],
                'status' => 'active',
            ],
        );

        $startError = $tunnelService->start();

        if ($startError !== null) {
            $tunnelService->cleanup();
            $this->isProvisioning = false;
            $this->error = 'Tunnel provisioned but failed to start: ' . $startError;

            return;
        }

        $this->subdomain = $this->newSubdomain;
        $this->newSubdomain = '';
        $this->subdomainAvailable = false;
        $this->isProvisioning = false;
        $this->provisionStatus = '';
        $this->tunnelRunning = $tunnelService->isRunning();
        $this->tunnelConfigured = $tunnelService->hasCredentials();
    }

    public function reprovisionTunnel(
        CloudApiClient $cloudApi,
        DeviceIdentityService $identity,
        TunnelService $tunnelService,
    ): void {
        $this->error = '';
        $this->isProvisioning = true;
        $this->provisionStatus = 'Re-provisioning tunnel...';

        $tunnelConfig = TunnelConfig::current();

        if (! $tunnelConfig) {
            $this->isProvisioning = false;
            $this->error = 'No tunnel configuration found. Use the setup form instead.';
            $this->provisionStatus = '';

            return;
        }

        $tunnelService->stop();

        try {
            $deviceId = $identity->getDeviceInfo()->id;
            $result = $cloudApi->provisionTunnel($deviceId, $tunnelConfig->subdomain);
        } catch (\Throwable $e) {
            $this->isProvisioning = false;
            $this->error = 'Failed to re-provision tunnel: ' . $e->getMessage();
            $this->provisionStatus = '';

            return;
        }

        $tunnelConfig->update([
            'tunnel_id' => $result['tunnel_id'],
            'tunnel_token_encrypted' => $result['tunnel_token'],
            'status' => 'active',
            'verified_at' => null,
        ]);

        $startError = $tunnelService->start();

        if ($startError !== null) {
            $tunnelService->cleanup();
            $this->isProvisioning = false;
            $this->error = 'Tunnel re-provisioned but failed to start: ' . $startError;

            return;
        }

        $this->isProvisioning = false;
        $this->provisionStatus = '';
        $this->tunnelRunning = $tunnelService->isRunning();
        $this->tunnelConfigured = $tunnelService->hasCredentials();
    }

    private function syncIngressConfig(CloudApiClient $cloudApi, DeviceIdentityService $identity): void
    {
        if (! $identity->hasIdentity()) {
            return;
        }

        $port = (int) config('vibecodepc.tunnel.device_app_port');

        try {
            $cloudApi->reconfigureTunnel($identity->getDeviceInfo()->id, $port);
        } catch (\Throwable $e) {
            Log::warning('Failed to sync tunnel ingress config', ['error' => $e->getMessage()]);
        }
    }

    public function render()
    {
        return view('livewire.dashboard.tunnel-manager');
    }

    private function loadProjects(): void
    {
        $this->projects = Project::all()->map(fn (Project $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'port' => $p->port,
            'tunnel_enabled' => $p->tunnel_enabled,
        ])->all();
    }

    private function loadTrafficStats(): void
    {
        $deviceId = DeviceState::getValue('device_uuid');

        if (! $deviceId) {
            return;
        }

        $cloudApi = app(CloudApiClient::class);
        $stats = $cloudApi->fetchTrafficStats($deviceId);

        $this->trafficStats = $stats['routes'] ?? [];
    }
}
