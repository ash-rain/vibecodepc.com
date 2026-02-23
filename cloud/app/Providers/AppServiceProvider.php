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
        // Force HTTPS when APP_URL uses https — this covers both
        // production and local dev behind Herd/Valet's Nginx proxy,
        // where PHP-FPM sees HTTP but the browser is on HTTPS.
        URL::forceHttps(
            str_starts_with(config('app.url', ''), 'https')
        );

        RateLimiter::for('device-heartbeat', function (Request $request) {
            return Limit::perMinute(2)->by($request->route('uuid'));
        });

        $this->ensureTunnelTokenFile();
    }

    /**
     * Write the cloud tunnel token to the shared volume so the
     * cloudflared container can connect immediately on boot.
     */
    private function ensureTunnelTokenFile(): void
    {
        $token = config('cloudflare.tunnel_token');
        $tokenFilePath = config('cloudflare.tunnel_token_file_path');

        if (empty($token) || empty($tokenFilePath)) {
            return;
        }

        if (file_exists($tokenFilePath) && filesize($tokenFilePath) > 0) {
            return;
        }

        $dir = dirname($tokenFilePath);

        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        if (is_writable($dir)) {
            file_put_contents($tokenFilePath, $token);
        }
    }
}
