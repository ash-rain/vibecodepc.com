<?php

use App\Livewire\Dashboard\AiServicesHub;
use App\Livewire\Dashboard\CodeEditor;
use App\Livewire\Dashboard\ContainerMonitor;
use App\Livewire\Dashboard\Overview;
use App\Livewire\Dashboard\ProjectCreate;
use App\Livewire\Dashboard\ProjectDetail;
use App\Livewire\Dashboard\ProjectList;
use App\Livewire\Dashboard\SystemSettings;
use App\Livewire\Dashboard\TunnelManager;
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

// Dashboard
Route::get('/dashboard', Overview::class)->name('dashboard');
Route::get('/dashboard/projects', ProjectList::class)->name('dashboard.projects');
Route::get('/dashboard/projects/create', ProjectCreate::class)->name('dashboard.projects.create');
Route::get('/dashboard/projects/{project}', ProjectDetail::class)->name('dashboard.projects.show');
Route::get('/dashboard/ai-services', AiServicesHub::class)->name('dashboard.ai-services');
Route::get('/dashboard/code-editor', CodeEditor::class)->name('dashboard.code-editor');
Route::get('/dashboard/tunnels', TunnelManager::class)->name('dashboard.tunnels');
Route::get('/dashboard/containers', ContainerMonitor::class)->name('dashboard.containers');
Route::get('/dashboard/settings', SystemSettings::class)->name('dashboard.settings');
