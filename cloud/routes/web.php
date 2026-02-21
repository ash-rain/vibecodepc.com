<?php

use App\Http\Controllers\DevicePairingController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Health check endpoint for monitoring and CI/CD
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'service' => 'VibeCodePC Cloud',
    ]);
})->name('health');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});

// Device pairing flow (QR code entry point)
Route::get('/id/{uuid}', [DevicePairingController::class, 'show'])->name('pairing.show');
Route::post('/id/{uuid}/claim', [DevicePairingController::class, 'claim'])->name('pairing.claim');
Route::get('/id/{uuid}/success', [DevicePairingController::class, 'success'])->name('pairing.success');

require __DIR__ . '/auth.php';
