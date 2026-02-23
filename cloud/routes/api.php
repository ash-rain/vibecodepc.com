<?php

use App\Http\Controllers\Api\DeviceConfigController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\DeviceDeregisterController;
use App\Http\Controllers\Api\DeviceHeartbeatController;
use App\Http\Controllers\Api\DeviceStatsController;
use App\Http\Controllers\Api\DeviceTunnelController;
use App\Http\Controllers\Api\SubdomainController;
use App\Http\Controllers\Api\TunnelErrorController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Device registration (public, called by device on boot)
Route::post('/devices/register', [DeviceController::class, 'register'])
    ->name('api.devices.register');

// Subdomain availability check (public, called by device during tunnel setup)
Route::get('/subdomains/{subdomain}/availability', [SubdomainController::class, 'availability'])
    ->name('api.subdomains.availability');

// Tunnel error reporting (public, called by CF Worker or browser on tunnel errors)
Route::post('/tunnel-error', TunnelErrorController::class)
    ->middleware('throttle:10,1')
    ->name('api.tunnel-error');

// Device pairing API (public status, authenticated claim)
Route::get('/devices/{uuid}/status', [DeviceController::class, 'status'])
    ->name('api.devices.status');

Route::post('/devices/{uuid}/claim', [DeviceController::class, 'claim'])
    ->middleware('auth:sanctum')
    ->name('api.devices.claim');

// Device management API (authenticated + ownership verified)
Route::middleware(['auth:sanctum', 'device.owner'])
    ->prefix('devices/{uuid}')
    ->as('api.devices.')
    ->group(function () {
        Route::post('/heartbeat', [DeviceHeartbeatController::class, 'store'])
            ->middleware('throttle:device-heartbeat')
            ->name('heartbeat.store');

        Route::get('/heartbeats', [DeviceHeartbeatController::class, 'index'])
            ->name('heartbeat.index');

        Route::post('/tunnel/register', [DeviceTunnelController::class, 'register'])
            ->name('tunnel.register');

        Route::post('/tunnel/provision', [DeviceTunnelController::class, 'provision'])
            ->name('tunnel.provision');

        Route::post('/tunnel/reconfigure', [DeviceTunnelController::class, 'reconfigure'])
            ->name('tunnel.reconfigure');

        Route::post('/tunnel/routes', [DeviceTunnelController::class, 'updateRoutes'])
            ->name('tunnel.routes.update');

        Route::get('/tunnel/routes', [DeviceTunnelController::class, 'routes'])
            ->name('tunnel.routes.index');

        Route::get('/config', [DeviceConfigController::class, 'show'])
            ->name('config.show');

        Route::get('/stats', DeviceStatsController::class)
            ->name('stats');

        Route::post('/deregister', DeviceDeregisterController::class)
            ->name('deregister');
    });
