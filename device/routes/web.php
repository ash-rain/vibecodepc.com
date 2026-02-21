<?php

use App\Livewire\Pairing\PairingScreen;
use App\Livewire\Wizard\WizardController;
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

// Setup wizard
Route::get('/wizard', WizardController::class)->name('wizard');

// Placeholder route for dashboard (to be built in later phases)
Route::get('/dashboard', function () {
    return view('welcome');
})->name('dashboard');
