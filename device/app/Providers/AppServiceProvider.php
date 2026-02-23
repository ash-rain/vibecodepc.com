<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\AiProviders\AiProviderResolverService;
use App\Services\CloudApiClient;
use App\Services\CodeServer\CodeServerService;
use App\Services\DeviceHealthService;
use App\Services\DeviceRegistry\DeviceIdentityService;
use App\Services\DeviceStateService;
use App\Services\Docker\ProjectContainerService;
use App\Services\GitHub\GitHubDeviceFlowService;
use App\Services\GitHub\GitHubRepoService;
use App\Services\NetworkService;
use App\Services\Projects\PortAllocatorService;
use App\Services\Projects\ProjectCloneService;
use App\Services\Projects\ProjectLinkService;
use App\Services\Projects\ProjectScaffoldService;
use App\Services\SystemService;
use App\Services\Tunnel\TunnelService;
use App\Services\WizardProgressService;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DeviceIdentityService::class, function () {
            return new DeviceIdentityService(
                deviceJsonPath: config('vibecodepc.device_json_path'),
            );
        });

        $this->app->singleton(CloudApiClient::class, function () {
            return new CloudApiClient(
                cloudUrl: config('vibecodepc.cloud_url'),
            );
        });

        $this->app->singleton(DeviceStateService::class);
        $this->app->singleton(WizardProgressService::class);
        $this->app->singleton(SystemService::class);
        $this->app->singleton(AiProviderResolverService::class);

        $this->app->singleton(GitHubDeviceFlowService::class, function () {
            return new GitHubDeviceFlowService(
                clientId: config('vibecodepc.github.client_id'),
            );
        });

        $this->app->singleton(CodeServerService::class, function () {
            $port = config('vibecodepc.code_server.port');

            return new CodeServerService(
                port: $port ?: null,
                configPath: config('vibecodepc.code_server.config_path'),
                settingsPath: config('vibecodepc.code_server.settings_path'),
            );
        });

        $this->app->singleton(TunnelService::class, function () {
            return new TunnelService(
                deviceAppPort: (int) config('vibecodepc.tunnel.device_app_port'),
                tokenFilePath: config('vibecodepc.tunnel.token_file_path'),
            );
        });

        $this->app->singleton(DeviceHealthService::class);
        $this->app->singleton(NetworkService::class);
        $this->app->singleton(ProjectContainerService::class, function () {
            $hostProjectsPath = config('vibecodepc.docker.host_projects_path');

            return new ProjectContainerService(
                hostProjectsPath: $hostProjectsPath,
                containerProjectsPath: $hostProjectsPath ? config('vibecodepc.projects.base_path') : null,
            );
        });
        $this->app->singleton(PortAllocatorService::class);

        $this->app->singleton(ProjectScaffoldService::class, function () {
            return new ProjectScaffoldService(
                basePath: config('vibecodepc.projects.base_path'),
                portAllocator: app(PortAllocatorService::class),
            );
        });

        $this->app->singleton(GitHubRepoService::class);

        $this->app->singleton(ProjectCloneService::class, function () {
            return new ProjectCloneService(
                basePath: config('vibecodepc.projects.base_path'),
                portAllocator: app(PortAllocatorService::class),
                scaffoldService: app(ProjectScaffoldService::class),
            );
        });

        $this->app->singleton(ProjectLinkService::class, function () {
            return new ProjectLinkService(
                basePath: config('vibecodepc.projects.base_path'),
                portAllocator: app(PortAllocatorService::class),
                cloneService: app(ProjectCloneService::class),
            );
        });
    }

    public function boot(): void
    {
        URL::forceHttps(
            app()->environment(['production', 'staging'])
            || ! app()->environment('local')
        );

        $this->ensureTunnelTokenFile();
    }

    /**
     * Write the tunnel token to the shared file on boot so the
     * cloudflared container can connect immediately after a restart
     * without waiting for the wizard or dashboard to trigger start().
     */
    private function ensureTunnelTokenFile(): void
    {
        $tokenFilePath = config('vibecodepc.tunnel.token_file_path');

        if (file_exists($tokenFilePath) && filesize($tokenFilePath) > 0) {
            return;
        }

        try {
            $config = \App\Models\TunnelConfig::current();
        } catch (\Throwable) {
            // Table may not exist yet (fresh install or tests)
            return;
        }

        if ($config === null || empty($config->tunnel_token_encrypted)) {
            return;
        }

        $dir = dirname($tokenFilePath);

        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        if (! is_writable($dir)) {
            return;
        }

        file_put_contents($tokenFilePath, $config->tunnel_token_encrypted);
    }
}
