<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\AiProviders\AiProviderResolverService;
use App\Services\CloudApiClient;
use App\Services\CodeServer\CodeServerService;
use App\Services\DeviceRegistry\DeviceIdentityService;
use App\Services\DeviceStateService;
use App\Services\GitHub\GitHubDeviceFlowService;
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
            return new CodeServerService(
                port: config('vibecodepc.code_server.port'),
                configPath: config('vibecodepc.code_server.config_path'),
            );
        });

        $this->app->singleton(TunnelService::class, function () {
            return new TunnelService(
                configPath: config('vibecodepc.tunnel.config_path'),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
