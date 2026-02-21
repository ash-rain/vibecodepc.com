<?php

use App\Http\Controllers\Api\DeviceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Device pairing API
Route::get('/devices/{uuid}/status', [DeviceController::class, 'status'])
    ->name('api.devices.status');

Route::post('/devices/{uuid}/claim', [DeviceController::class, 'claim'])
    ->middleware('auth:sanctum')
    ->name('api.devices.claim');
