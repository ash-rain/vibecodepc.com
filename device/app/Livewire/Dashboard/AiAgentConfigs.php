<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Services\ConfigFileService;
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

    public function mount(ConfigFileService $configFileService): void
    {
        $this->loadAllFiles($configFileService);
    }

    public function loadAllFiles(ConfigFileService $configFileService): void
    {
        $configKeys = array_keys(config('vibecodepc.config_files', []));

        foreach ($configKeys as $key) {
            $this->isDirty[$key] = false;
            $this->isValid[$key] = true;
            $this->validationErrors[$key] = '';
            $this->isSaving[$key] = false;
            $this->selectedBackup[$key] = '';

            try {
                $content = $configFileService->getContent($key);
                $this->fileContent[$key] = $content;
                $this->originalContent[$key] = $content;
                $this->fileExists[$key] = $configFileService->exists($key);
                $this->backups[$key] = $configFileService->listBackups($key);
            } catch (\Exception $e) {
                Log::error("Failed to load config file: {$key}", ['error' => $e->getMessage()]);
                $this->fileContent[$key] = '';
                $this->originalContent[$key] = '';
                $this->fileExists[$key] = false;
                $this->backups[$key] = [];
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

    public function save(string $key, ConfigFileService $configFileService): void
    {
        $this->isSaving[$key] = true;
        $content = $this->fileContent[$key] ?? '';

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

            $configFileService->putContent($key, $content);

            $this->originalContent[$key] = $content;
            $this->isDirty[$key] = false;
            $this->fileExists[$key] = true;
            $this->backups[$key] = $configFileService->listBackups($key);

            $this->statusMessage = config("vibecodepc.config_files.{$key}.label").' saved successfully.';
            $this->statusType = 'success';
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

        try {
            $configFileService->restore($key, $backupPath);

            $content = $configFileService->getContent($key);
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
        try {
            if ($key === 'boost') {
                $this->resetBoostJson();
            } else {
                $config = config("vibecodepc.config_files.{$key}");
                if ($config && isset($config['path'])) {
                    File::delete($config['path']);
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

    private function resetBoostJson(): void
    {
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

            $formatted = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $this->fileContent[$key] = $formatted;
            $this->updatedFileContent($formatted, $key);
        } catch (\Exception $e) {
            Log::warning("Failed to format JSON for {$key}", ['error' => $e->getMessage()]);
        }
    }

    public function render(): \Illuminate\View\View
    {
        $configFiles = config('vibecodepc.config_files', []);

        return view('livewire.dashboard.ai-agent-configs', [
            'configFiles' => $configFiles,
        ]);
    }
}
