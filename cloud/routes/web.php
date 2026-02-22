<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DevicePairingController;
use App\Services\LandingContentService;
use App\SupportedLocale;
use Illuminate\Support\Facades\Route;

Route::get('/', function (LandingContentService $contentService) {
    return view('welcome', [
        'content' => $contentService->load('en'),
        'locale' => 'en',
        'locales' => SupportedLocale::values(),
    ]);
})->name('landing');

Route::get('/{locale}', function (string $locale, LandingContentService $contentService) {
    app()->setLocale($locale);

    return view('welcome', [
        'content' => $contentService->load($locale),
        'locale' => $locale,
        'locales' => SupportedLocale::values(),
    ]);
})->where('locale', SupportedLocale::routePattern())->name('landing.locale');

// Health check endpoint for monitoring and CI/CD
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'service' => 'VibeCodePC Cloud',
    ]);
})->name('health');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/devices/{device}', [DashboardController::class, 'showDevice'])->name('dashboard.devices.show');
});

// Device pairing flow (QR code entry point)
Route::get('/id/{uuid}', [DevicePairingController::class, 'show'])->name('pairing.show');
Route::post('/id/{uuid}/claim', [DevicePairingController::class, 'claim'])->name('pairing.claim');
Route::get('/id/{uuid}/success', [DevicePairingController::class, 'success'])->name('pairing.success');

require __DIR__.'/auth.php';
