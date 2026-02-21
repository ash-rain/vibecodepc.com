<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\CloudApiClient;
use App\Services\DeviceRegistry\DeviceIdentityService;
use App\Services\DeviceStateService;
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
    }

    public function boot(): void
    {
        //
    }
}
