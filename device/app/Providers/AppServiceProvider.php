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
use App\Services\NetworkService;
use App\Services\Projects\PortAllocatorService;
use App\Services\Projects\ProjectScaffoldService;
use App\Services\SystemService;
use App\Services\Tunnel\TunnelService;
use App\Services\WizardProgressService;
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
                configPath: config('vibecodepc.tunnel.config_path'),
            );
        });

        $this->app->singleton(DeviceHealthService::class);
        $this->app->singleton(NetworkService::class);
        $this->app->singleton(ProjectContainerService::class);
        $this->app->singleton(PortAllocatorService::class);

        $this->app->singleton(ProjectScaffoldService::class, function () {
            return new ProjectScaffoldService(
                basePath: config('vibecodepc.projects.base_path'),
                portAllocator: app(PortAllocatorService::class),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
