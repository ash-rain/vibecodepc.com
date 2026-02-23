<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\CloudCredential;
use App\Models\DeviceState;
use App\Models\Project;
use App\Models\QuickTunnel;
use App\Models\TunnelConfig;
use App\Services\CloudApiClient;
use App\Services\DeviceRegistry\DeviceIdentityService;
use App\Services\Tunnel\QuickTunnelService;
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

    /** @var array<int, array{id: int, name: string, slug: string, port: int|null, tunnel_enabled: bool, tunnel_subdomain_path: string|null}> */
    public array $projects = [];

    /** @var array<int, array{project: string, requests: int, avg_response_time_ms: int}> */
    public array $trafficStats = [];

    public ?int $editingProjectId = null;

    public string $editPath = '';

    public ?int $editPort = null;

    public bool $showConfig = false;

    public string $ingressConfig = '';

    /** @var array<int, array{key: string, name: string, port: int, project_id: int|null, tunnel: array{id: int, url: string|null, status: string}|null}> */
    public array $quickTunnelApps = [];

    public string $quickTunnelError = '';

    public ?string $startingQuickTunnelKey = null;

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
        $this->loadQuickTunnelApps();
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
        $this->refreshIngressConfig();
    }

    public function editProject(int $projectId): void
    {
        $project = Project::findOrFail($projectId);
        $this->editingProjectId = $projectId;
        $this->editPath = $project->tunnel_subdomain_path ?? $project->slug;
        $this->editPort = $project->port;
    }

    public function cancelEdit(): void
    {
        $this->editingProjectId = null;
        $this->editPath = '';
        $this->editPort = null;
        $this->resetValidation(['editPath', 'editPort']);
    }

    public function saveProject(): void
    {
        $this->validate([
            'editPath' => ['required', 'string', 'min:1', 'max:60', 'regex:/^[a-z0-9][a-z0-9-]*[a-z0-9]$/'],
            'editPort' => ['required', 'integer', 'min:1', 'max:65535'],
        ], [
            'editPath.regex' => 'Path must be lowercase alphanumeric and hyphens only.',
        ]);

        $project = Project::findOrFail($this->editingProjectId);
        $project->update([
            'tunnel_subdomain_path' => $this->editPath,
            'port' => $this->editPort,
        ]);

        $this->editingProjectId = null;
        $this->editPath = '';
        $this->editPort = null;
        $this->loadProjects();
        $this->refreshIngressConfig();
    }

    public function openConfig(): void
    {
        $this->refreshIngressConfig();
        $this->showConfig = true;
    }

    public function closeConfig(): void
    {
        $this->showConfig = false;
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

    public function startQuickTunnel(?int $projectId, QuickTunnelService $service): void
    {
        $this->quickTunnelError = '';
        $key = $projectId ? "project_{$projectId}" : 'dashboard';
        $this->startingQuickTunnelKey = $key;

        $port = $projectId
            ? Project::find($projectId)?->port
            : (int) config('vibecodepc.tunnel.device_app_port');

        if (! $port) {
            $this->quickTunnelError = 'No port configured for this app.';
            $this->startingQuickTunnelKey = null;

            return;
        }

        try {
            $service->start($port, $projectId);
        } catch (\Throwable $e) {
            $this->quickTunnelError = 'Failed to start quick tunnel: ' . $e->getMessage();
            Log::warning('Quick tunnel start failed', ['error' => $e->getMessage(), 'project_id' => $projectId]);
        }

        $this->startingQuickTunnelKey = null;
        $this->loadQuickTunnelApps();
    }

    public function stopQuickTunnel(int $quickTunnelId, QuickTunnelService $service): void
    {
        $this->quickTunnelError = '';

        $tunnel = QuickTunnel::find($quickTunnelId);

        if (! $tunnel) {
            return;
        }

        try {
            $service->stop($tunnel);
        } catch (\Throwable $e) {
            $this->quickTunnelError = 'Failed to stop quick tunnel: ' . $e->getMessage();
        }

        $this->loadQuickTunnelApps();
    }

    public function reprovisionQuickTunnel(int $quickTunnelId, QuickTunnelService $service): void
    {
        $tunnel = QuickTunnel::find($quickTunnelId);

        if (! $tunnel) {
            return;
        }

        $projectId = $tunnel->project_id;
        $this->stopQuickTunnel($quickTunnelId, $service);
        $this->startQuickTunnel($projectId, $service);
    }

    public function refreshQuickTunnels(QuickTunnelService $service): void
    {
        $activeTunnels = QuickTunnel::whereIn('status', ['starting', 'running'])->get();

        foreach ($activeTunnels as $tunnel) {
            if (! $service->isHealthy($tunnel)) {
                $service->cleanup($tunnel);

                continue;
            }

            if (! $tunnel->tunnel_url) {
                $service->refreshUrl($tunnel);
            }
        }

        $this->loadQuickTunnelApps();
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
            'tunnel_subdomain_path' => $p->tunnel_subdomain_path,
        ])->all();
    }

    private function refreshIngressConfig(): void
    {
        if (! $this->subdomain) {
            $this->ingressConfig = '';

            return;
        }

        $hostname = "{$this->subdomain}." . config('vibecodepc.cloud_domain');
        $ingress = [];

        foreach ($this->projects as $project) {
            if (! $project['tunnel_enabled'] || ! $project['port']) {
                continue;
            }

            $path = $project['tunnel_subdomain_path'] ?? $project['slug'];
            $ingress[] = [
                'hostname' => $hostname,
                'path' => "/{$path}(/.*)?$",
                'service' => "http://localhost:{$project['port']}",
            ];
        }

        $ingress[] = [
            'hostname' => $hostname,
            'service' => 'http://localhost:' . config('vibecodepc.tunnel.device_app_port'),
        ];

        $ingress[] = ['service' => 'http_status:404'];

        $this->ingressConfig = \Symfony\Component\Yaml\Yaml::dump(
            ['ingress' => $ingress],
            3,
            2,
        );
    }

    private function loadQuickTunnelApps(): void
    {
        $dashboardPort = (int) config('vibecodepc.tunnel.device_app_port');
        $dashboardTunnel = QuickTunnel::forDashboard();

        $apps = [];

        $apps[] = [
            'key' => 'dashboard',
            'name' => 'Dashboard',
            'port' => $dashboardPort,
            'project_id' => null,
            'tunnel' => $dashboardTunnel ? [
                'id' => $dashboardTunnel->id,
                'url' => $dashboardTunnel->tunnel_url,
                'status' => $dashboardTunnel->status,
            ] : null,
        ];

        foreach (Project::all() as $project) {
            $tunnel = $project->port ? QuickTunnel::forProject($project->id) : null;

            $apps[] = [
                'key' => "project_{$project->id}",
                'name' => $project->name,
                'port' => $project->port ?? 0,
                'project_id' => $project->id,
                'tunnel' => $tunnel ? [
                    'id' => $tunnel->id,
                    'url' => $tunnel->tunnel_url,
                    'status' => $tunnel->status,
                ] : null,
            ];
        }

        $this->quickTunnelApps = $apps;
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
