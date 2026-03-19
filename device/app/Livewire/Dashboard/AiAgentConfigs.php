<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\Project;
use App\Models\TunnelConfig;
use App\Services\ConfigAuditLogService;
use App\Services\ConfigFileService;
use App\Services\ConfigReloadService;
use App\Services\Tunnel\TunnelService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.dashboard', ['title' => 'AI Agent Configs'])]
#[Title('AI Agent Configs — VibeCodePC')]
class AiAgentConfigs extends Component
{
    public string $activeTab = 'boost';

    public ?int $selectedProjectId = null;

    public bool $isPaired = false;

    public bool $isTunnelRunning = false;

    /** @var array<string, string> */
    public array $fileContent = [];

    /** @var array<string, string> */
    public array $originalContent = [];

    /** @var array<string, bool> */
    public array $isDirty = [];

    /** @var array<string, bool> */
    public array $isValid = [];

    /** @var array<string, string> */
    public array $validationErrors = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    public array $backups = [];

    /** @var array<string, string> */
    public array $selectedBackup = [];

    public string $statusMessage = '';

    public string $statusType = 'success';

    /** @var array<string, bool> */
    public array $isSaving = [];

    /** @var array<string, bool> */
    public array $fileExists = [];

    /** @var array<string, array<string, mixed>> */
    public array $reloadStatus = [];

    /** @var array<string, string> */
    public array $contentHash = [];

    public function mount(ConfigFileService $configFileService, ConfigReloadService $reloadService, TunnelService $tunnelService): void
    {
        $this->isPaired = TunnelConfig::current()?->verified_at !== null;
        $this->isTunnelRunning = $tunnelService->isRunning();
        $this->loadAllFiles($configFileService, $reloadService);
    }

    public function updatedSelectedProjectId(int $value): void
    {
        $this->loadAllFiles(app(ConfigFileService::class), app(ConfigReloadService::class));
    }

    public function loadAllFiles(ConfigFileService $configFileService, ConfigReloadService $reloadService): void
    {
        $project = $this->getSelectedProject();
        $configKeys = array_keys(config('vibecodepc.config_files', []));

        foreach ($configKeys as $key) {
            $this->isDirty[$key] = false;
            $this->isValid[$key] = true;
            $this->validationErrors[$key] = '';
            $this->isSaving[$key] = false;
            $this->selectedBackup[$key] = '';

            try {
                $content = $configFileService->getContent($key, $project);
                $this->fileContent[$key] = $content;
                $this->originalContent[$key] = $content;
                $this->fileExists[$key] = $configFileService->exists($key, $project);
                $this->backups[$key] = $configFileService->listBackups($key, $project);

                // Store content hash for conflict detection
                $this->contentHash[$key] = $content !== '' ? $configFileService->getContentHash($content) : '';

                // Load reload status for this config file
                $path = $configFileService->resolvePath($key, $project);
                $this->reloadStatus[$key] = $reloadService->getReloadStatus($key, $path);
            } catch (\Exception $e) {
                Log::error("Failed to load config file: {$key}", ['error' => $e->getMessage()]);
                $this->fileContent[$key] = '';
                $this->originalContent[$key] = '';
                $this->fileExists[$key] = false;
                $this->backups[$key] = [];
                $this->contentHash[$key] = '';
                $this->reloadStatus[$key] = $reloadService->getReloadStatus($key);
            }
        }
    }

    public function updatedFileContent(string $value, string $key): void
    {
        $this->isDirty[$key] = $value !== $this->originalContent[$key];
        $this->validateContent($key, $value);
    }

    public function validateContent(string $key, string $content): void
    {
        if ($content === '') {
            $this->isValid[$key] = true;
            $this->validationErrors[$key] = '';

            return;
        }

        if ($key === 'copilot_instructions') {
            $this->isValid[$key] = true;
            $this->validationErrors[$key] = '';

            return;
        }

        try {
            $configFileService = app(ConfigFileService::class);
            $configFileService->validateJson($content, $key);
            $this->isValid[$key] = true;
            $this->validationErrors[$key] = '';
        } catch (\InvalidArgumentException $e) {
            $this->isValid[$key] = false;
            $this->validationErrors[$key] = $e->getMessage();
        } catch (\Exception $e) {
            $this->isValid[$key] = false;
            $this->validationErrors[$key] = 'Invalid JSON: '.$e->getMessage();
        }
    }

    public function save(string $key, ConfigFileService $configFileService, ConfigReloadService $reloadService): void
    {
        $this->isSaving[$key] = true;
        $content = $this->fileContent[$key] ?? '';
        $project = $this->getSelectedProject();

        try {
            if ($content === '') {
                $this->statusMessage = 'Cannot save empty content.';
                $this->statusType = 'error';
                $this->isSaving[$key] = false;

                return;
            }

            if (! $this->isValid[$key]) {
                $this->statusMessage = "Cannot save: {$this->validationErrors[$key]}";
                $this->statusType = 'error';
                $this->isSaving[$key] = false;

                return;
            }

            // Pass the expected hash for conflict detection
            $expectedHash = $this->contentHash[$key] ?? null;
            $expectedHash = $expectedHash !== '' ? $expectedHash : null;

            $configFileService->putContent($key, $content, $project, $expectedHash);

            // Update content hash after successful save
            $this->contentHash[$key] = $configFileService->getContentHash($content);

            $this->originalContent[$key] = $content;
            $this->isDirty[$key] = false;
            $this->fileExists[$key] = true;
            $this->backups[$key] = $configFileService->listBackups($key, $project);

            // Trigger reload for affected services
            $reloadResult = $reloadService->triggerReload($key);
            $path = $configFileService->resolvePath($key, $project);
            $this->reloadStatus[$key] = $reloadService->getReloadStatus($key, $path);

            // Build status message with reload info
            $baseMessage = config("vibecodepc.config_files.{$key}.label").' saved successfully.';

            if ($reloadResult['requires_manual'] ?? false) {
                $this->statusMessage = $baseMessage.' '.$reloadResult['instructions'];
            } else {
                $this->statusMessage = $baseMessage;
            }

            $this->statusType = 'success';
        } catch (\RuntimeException $e) {
            // Handle conflict detection specifically
            if (str_contains($e->getMessage(), 'modified by another user')) {
                $this->statusMessage = 'Conflict detected: '.$e->getMessage().' Please reload the file before saving.';
                $this->statusType = 'error';
            } else {
                Log::error("Failed to save config file: {$key}", ['error' => $e->getMessage()]);
                $this->statusMessage = 'Save failed: '.$e->getMessage();
                $this->statusType = 'error';
            }
        } catch (\Exception $e) {
            Log::error("Failed to save config file: {$key}", ['error' => $e->getMessage()]);
            $this->statusMessage = 'Save failed: '.$e->getMessage();
            $this->statusType = 'error';
        } finally {
            $this->isSaving[$key] = false;
        }
    }

    public function restore(string $key, ConfigFileService $configFileService): void
    {
        $backupPath = $this->selectedBackup[$key] ?? '';

        if ($backupPath === '') {
            $this->statusMessage = 'Please select a backup to restore.';
            $this->statusType = 'error';

            return;
        }

        $project = $this->getSelectedProject();

        try {
            $configFileService->restore($key, $backupPath, $project);

            $content = $configFileService->getContent($key, $project);
            $this->fileContent[$key] = $content;
            $this->originalContent[$key] = $content;
            $this->isDirty[$key] = false;
            $this->fileExists[$key] = true;

            $this->statusMessage = config("vibecodepc.config_files.{$key}.label").' restored from backup.';
            $this->statusType = 'success';
        } catch (\Exception $e) {
            Log::error("Failed to restore config file: {$key}", ['error' => $e->getMessage()]);
            $this->statusMessage = 'Restore failed: '.$e->getMessage();
            $this->statusType = 'error';
        }
    }

    public function resetToDefaults(string $key): void
    {
        $project = $this->getSelectedProject();

        try {
            if ($key === 'boost') {
                $this->resetBoostJson($project);
            } else {
                $config = config("vibecodepc.config_files.{$key}");
                if ($config && (isset($config['path']) || isset($config['path_template']))) {
                    $configFileService = app(ConfigFileService::class);
                    $path = $configFileService->resolvePath($key, $project);

                    // Log reset action before deletion for audit
                    if ($configFileService->exists($key, $project)) {
                        $oldContent = $configFileService->getContent($key, $project);
                        $auditLogService = app(ConfigAuditLogService::class);
                        $auditLogService->log($key, 'reset', $path, $oldContent, null, null, $project);
                    }

                    $configFileService->delete($key, $project);
                    $this->fileContent[$key] = '';
                    $this->originalContent[$key] = '';
                    $this->isDirty[$key] = false;
                    $this->fileExists[$key] = false;
                }
            }

            $this->statusMessage = config("vibecodepc.config_files.{$key}.label").' reset to defaults.';
            $this->statusType = 'success';
        } catch (\Exception $e) {
            Log::error("Failed to reset config file: {$key}", ['error' => $e->getMessage()]);
            $this->statusMessage = 'Reset failed: '.$e->getMessage();
            $this->statusType = 'error';
        }
    }

    private function resetBoostJson(?Project $project): void
    {
        $configFileService = app(ConfigFileService::class);
        $path = $configFileService->resolvePath('boost', $project);
        $oldContent = $configFileService->exists('boost', $project) ? $configFileService->getContent('boost', $project) : null;

        $defaultBoostJson = <<<'JSON'
{
  "agents": ["claude_code", "copilot"],
  "skills": ["laravel-development", "php-development"],
  "guidelines": {
    "coding_standards": "PSR-12",
    "test_coverage": true
  }
}
JSON;

        $this->fileContent['boost'] = $defaultBoostJson;
        $this->originalContent['boost'] = '';
        $this->isDirty['boost'] = true;
        $this->validateContent('boost', $defaultBoostJson);

        // Log reset action
        $auditLogService = app(ConfigAuditLogService::class);
        $auditLogService->log('boost', 'reset', $path, $oldContent, $defaultBoostJson, null, $project);
    }

    public function formatJson(string $key): void
    {
        $content = $this->fileContent[$key] ?? '';

        if ($content === '') {
            return;
        }

        try {
            $decoded = json_decode($content, true);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                return;
            }

            $formatted = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $this->fileContent[$key] = $formatted;
            $this->updatedFileContent($formatted, $key);
        } catch (\Exception $e) {
            Log::warning("Failed to format JSON for {$key}", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Trigger reload for a specific config file's services.
     */
    public function triggerReload(string $key, ConfigReloadService $reloadService): void
    {
        try {
            $result = $reloadService->triggerReload($key);

            // Update reload status
            $configFileService = app(ConfigFileService::class);
            $project = $this->getSelectedProject();
            $path = $configFileService->resolvePath($key, $project);
            $this->reloadStatus[$key] = $reloadService->getReloadStatus($key, $path);

            if ($result['success']) {
                $this->statusMessage = 'Reload triggered for '.config("vibecodepc.config_files.{$key}.label").'.';
                $this->statusType = 'success';
            } else {
                $serviceMessages = collect($result['services'] ?? [])
                    ->filter(fn ($s) => ! $s['reloaded'])
                    ->map(fn ($s) => $s['name'].': '.$s['message'])
                    ->implode(', ');

                $this->statusMessage = 'Some services may require manual restart: '.$serviceMessages;
                $this->statusType = 'warning';
            }
        } catch (\Exception $e) {
            Log::error("Failed to trigger reload: {$key}", ['error' => $e->getMessage()]);
            $this->statusMessage = 'Reload failed: '.$e->getMessage();
            $this->statusType = 'error';
        }
    }

    public function render(): \Illuminate\View\View
    {
        $configFiles = config('vibecodepc.config_files', []);
        $projects = Project::orderBy('name')->get();

        return view('livewire.dashboard.ai-agent-configs', [
            'configFiles' => $configFiles,
            'projects' => $projects,
            'schemas' => $this->getSchemas(),
            'reloadStatuses' => $this->reloadStatus,
            'isPaired' => $this->isPaired,
            'isTunnelRunning' => $this->isTunnelRunning,
        ]);
    }

    /**
     * Get the currently selected project.
     */
    private function getSelectedProject(): ?Project
    {
        if ($this->selectedProjectId === null) {
            return null;
        }

        return Project::find($this->selectedProjectId);
    }

    /**
     * Get JSON schemas for each config file type.
     *
     * @return array<string, string>
     */
    private function getSchemas(): array
    {
        $schemas = [];
        $schemaPath = storage_path('schemas');

        if (File::isDirectory($schemaPath)) {
            foreach (File::files($schemaPath) as $file) {
                if ($file->getExtension() === 'json') {
                    $key = $file->getFilenameWithoutExtension();
                    $schemas[$key] = route('schemas.json', ['name' => $key]);
                }
            }
        }

        return $schemas;
    }
}
