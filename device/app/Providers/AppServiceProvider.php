<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\DeviceRegistry\DeviceIdentityService;
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
    }

    public function boot(): void
    {
        //
    }
}
