<?php

use App\Http\Controllers\HealthCheckController;
use Illuminate\Support\Facades\Route;

/**
 * Public API endpoints with rate limiting.
 * Rate limit: 60 requests per minute per IP/user.
 */
Route::middleware(['api.rate_limit:60,1'])->group(function () {
    Route::get('/health', HealthCheckController::class)->name('health');
});
