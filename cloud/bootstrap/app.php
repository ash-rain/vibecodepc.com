<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [
            \App\Http\Middleware\TunnelProxyMiddleware::class,
        ]);

        $middleware->alias([
            'device.owner' => \App\Http\Middleware\VerifyDeviceOwnership::class,
            'subdomain.rate' => \App\Http\Middleware\SubdomainRateLimiter::class,
            'tunnel.log' => \App\Http\Middleware\LogTunnelRequest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) {
            // On CSRF token expiry, redirect back with input so the form
            // reloads with a fresh token. The _token_retried flag prevents
            // an infinite loop if the token keeps failing.
            if (! $request->session()->pull('_token_retried')) {
                $request->session()->flash('_token_retried', true);

                return redirect()->back()->withInput(
                    $request->except('_token')
                );
            }
        });
    })->create();
