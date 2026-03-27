<?php

use App\Livewire\Dashboard\AiAgentConfigs;
use App\Livewire\Dashboard\AiServicesHub;
use App\Livewire\Dashboard\AiToolsConfig;
use App\Livewire\Dashboard\AnalyticsDashboard;
use App\Livewire\Dashboard\BarxEditor;
use App\Livewire\Dashboard\CodeEditor;
use App\Livewire\Dashboard\ContainerMonitor;
use App\Livewire\Dashboard\Overview;
use App\Livewire\Dashboard\ProjectCreate;
use App\Livewire\Dashboard\ProjectDetail;
use App\Livewire\Dashboard\ProjectList;
use App\Livewire\Dashboard\SystemSettings;
use App\Livewire\Dashboard\TunnelManager;
use App\Livewire\Pairing\PairingScreen;
use App\Livewire\TunnelLogin;
use App\Livewire\Wizard\WizardController;
use App\Services\DeviceStateService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

Route::get('/', function (DeviceStateService $stateService) {
    return match ($stateService->getMode()) {
        DeviceStateService::MODE_PAIRING => redirect()->route('pairing'),
        DeviceStateService::MODE_WIZARD => redirect()->route('wizard'),
        DeviceStateService::MODE_DASHBOARD => redirect()->route('dashboard'),
        default => redirect()->route('pairing'),
    };
})->name('home');

// Tunnel authentication gate (password prompt when accessing via tunnel)
Route::get('/tunnel/login', TunnelLogin::class)->name('tunnel.login');

// Pairing screen
Route::get('/pairing', PairingScreen::class)->name('pairing');

// Setup wizard
Route::get('/wizard', WizardController::class)->name('wizard');

// Dashboard — optional tunnel auth (local access passes through freely, tunnel access can prompt for auth)
Route::middleware('tunnel.auth.optional')->group(function () {
    Route::get('/dashboard', Overview::class)->name('dashboard');
    Route::get('/dashboard/overview', fn () => redirect()->route('dashboard'))->name('dashboard.overview');
    Route::get('/dashboard/projects', ProjectList::class)->name('dashboard.projects');
    Route::get('/dashboard/projects/create', ProjectCreate::class)->name('dashboard.projects.create');
    Route::get('/dashboard/projects/{project}', ProjectDetail::class)->name('dashboard.projects.show');
    Route::get('/dashboard/ai-services', AiServicesHub::class)->name('dashboard.ai-services');
    Route::get('/dashboard/ai-tools', AiToolsConfig::class)->name('dashboard.ai-tools');
    Route::get('/dashboard/ai-agents', AiAgentConfigs::class)->name('dashboard.ai-agents');
    Route::get('/dashboard/barx', BarxEditor::class)->name('dashboard.barx');
    Route::get('/dashboard/code-editor', CodeEditor::class)->name('dashboard.code-editor');
    Route::get('/dashboard/tunnels', TunnelManager::class)->name('dashboard.tunnels');
    Route::get('/dashboard/containers', ContainerMonitor::class)->name('dashboard.containers');
    Route::get('/dashboard/settings', SystemSettings::class)->name('dashboard.settings');
    Route::get('/dashboard/analytics', AnalyticsDashboard::class)->name('dashboard.analytics');
});

// JSON Schema routes for Monaco editor
Route::get('/schemas/{name}.json', function (string $name) {
    $schemaPath = storage_path("schemas/{$name}.json");

    if (! File::exists($schemaPath)) {
        return response()->json(['error' => 'Schema not found'], 404);
    }

    $content = File::get($schemaPath);

    return response($content, 200, [
        'Content-Type' => 'application/json',
        'Access-Control-Allow-Origin' => '*',
    ]);
})->where('name', '[a-zA-Z0-9_-]+')->name('schemas.json');
