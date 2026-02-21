<?php

use App\Livewire\Pairing\PairingScreen;
use App\Services\DeviceStateService;
use Illuminate\Support\Facades\Route;

Route::get('/', function (DeviceStateService $stateService) {
    return match ($stateService->getMode()) {
        DeviceStateService::MODE_PAIRING => redirect()->route('pairing'),
        DeviceStateService::MODE_WIZARD => redirect()->route('wizard'),
        DeviceStateService::MODE_DASHBOARD => redirect()->route('dashboard'),
        default => redirect()->route('pairing'),
    };
})->name('home');

// Pairing screen
Route::get('/pairing', PairingScreen::class)->name('pairing');

// Placeholder routes for wizard and dashboard (to be built in later phases)
Route::get('/wizard', function () {
    return view('welcome');
})->name('wizard');

Route::get('/dashboard', function () {
    return view('welcome');
})->name('dashboard');
