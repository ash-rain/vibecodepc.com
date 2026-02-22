<?php

namespace App\Providers;

use App\Services\DeviceTelemetryService;
use App\Services\TunnelRoutingService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DeviceTelemetryService::class);
        $this->app->singleton(TunnelRoutingService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        URL::forceHttps(
            app()->environment(['production', 'staging'])
        );

        RateLimiter::for('device-heartbeat', function (Request $request) {
            return Limit::perMinute(2)->by($request->route('uuid'));
        });
    }
}
