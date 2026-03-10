<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        api: __DIR__.'/../routes/api.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust all proxies — cloudflared terminates TLS and forwards
        // requests as HTTP with X-Forwarded-* headers. Without this,
        // Laravel generates http:// URLs causing Mixed Content errors.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'tunnel.auth' => \App\Http\Middleware\RequireTunnelAuth::class,
            'tunnel.auth.optional' => \App\Http\Middleware\OptionalTunnelAuth::class,
            'api.rate_limit' => \App\Http\Middleware\RateLimitMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
