<?php

use App\Http\Controllers\Api\DeviceConfigController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\DeviceHeartbeatController;
use App\Http\Controllers\Api\DeviceTunnelController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

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

        Route::post('/tunnel/routes', [DeviceTunnelController::class, 'updateRoutes'])
            ->name('tunnel.routes.update');

        Route::get('/tunnel/routes', [DeviceTunnelController::class, 'routes'])
            ->name('tunnel.routes.index');

        Route::get('/config', [DeviceConfigController::class, 'show'])
            ->name('config.show');
    });
