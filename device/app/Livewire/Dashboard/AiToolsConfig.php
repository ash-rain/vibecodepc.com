<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Services\AiToolConfigService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.dashboard', ['title' => 'AI Tools Config'])]
#[Title('AI Tools Config — VibeCodePC')]
class AiToolsConfig extends Component
{
    public string $activeTab = 'environment';

    // --- Environment Tab ---
    public string $geminiApiKey = '';

    public string $claudeApiKey = '';

    public string $ollamaApiKey = '';

    public string $ollamaCloudApiKey = '';

    public string $extraPath = '';

    public bool $opencodeExperimental = false;

    public string $opencodeExperimentalBashTimeoutMs = '';

    public string $opencodeExperimentalBashMaxOutput = '';

    public bool $opencodeEnableExperimentalModels = false;

    public string $composerProcessTimeout = '';

    // --- Opencode Config Tab ---
    public string $opencodeConfigJson = '';

    // --- Opencode Auth Tab ---
    public string $opencodeAuthJson = '';

    // --- Status ---
    public string $statusMessage = '';

    public string $statusType = 'success';

    public function mount(AiToolConfigService $service): void
    {
        $envVars = $service->getEnvVars();

        $this->geminiApiKey = isset($envVars['GEMINI_API_KEY']) && $envVars['GEMINI_API_KEY'] !== '' ? '••••••••' : '';
        $this->claudeApiKey = isset($envVars['CLAUDE_API_KEY']) && $envVars['CLAUDE_API_KEY'] !== '' ? '••••••••' : '';
        $this->ollamaApiKey = isset($envVars['OLLAMA_API_KEY']) && $envVars['OLLAMA_API_KEY'] !== '' ? '••••••••' : '';
        $this->ollamaCloudApiKey = isset($envVars['OLLAMA_CLOUD_API_KEY']) && $envVars['OLLAMA_CLOUD_API_KEY'] !== '' ? '••••••••' : '';
        $this->extraPath = $envVars['_extra_path'] ?? '';
        $this->opencodeExperimental = ($envVars['OPENCODE_EXPERIMENTAL'] ?? '') === '1';
        $this->opencodeExperimentalBashTimeoutMs = $envVars['OPENCODE_EXPERIMENTAL_BASH_DEFAULT_TIMEOUT_MS'] ?? '';
        $this->opencodeExperimentalBashMaxOutput = $envVars['OPENCODE_EXPERIMENTAL_BASH_MAX_OUTPUT_LENGTH'] ?? '';
        $this->opencodeEnableExperimentalModels = ($envVars['OPENCODE_ENABLE_EXPERIMENTAL_MODELS'] ?? '') === '1';
        $this->composerProcessTimeout = $envVars['COMPOSER_PROCESS_TIMEOUT'] ?? '';

        $config = $service->getOpencodeConfig();
        $this->opencodeConfigJson = (string) json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $auth = $service->getOpencodeAuth();
        $this->opencodeAuthJson = (string) json_encode($auth ?: new \stdClass, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function saveEnvironment(AiToolConfigService $service): void
    {
        $existing = $service->getEnvVars();

        $vars = [
            'GEMINI_API_KEY' => $this->geminiApiKey === '••••••••' ? ($existing['GEMINI_API_KEY'] ?? '') : $this->geminiApiKey,
            'CLAUDE_API_KEY' => $this->claudeApiKey === '••••••••' ? ($existing['CLAUDE_API_KEY'] ?? '') : $this->claudeApiKey,
            'OLLAMA_API_KEY' => $this->ollamaApiKey === '••••••••' ? ($existing['OLLAMA_API_KEY'] ?? '') : $this->ollamaApiKey,
            'OLLAMA_CLOUD_API_KEY' => $this->ollamaCloudApiKey === '••••••••' ? ($existing['OLLAMA_CLOUD_API_KEY'] ?? '') : $this->ollamaCloudApiKey,
            '_extra_path' => $this->extraPath,
            'OPENCODE_EXPERIMENTAL' => $this->opencodeExperimental ? '1' : '',
            'OPENCODE_EXPERIMENTAL_BASH_DEFAULT_TIMEOUT_MS' => $this->opencodeExperimentalBashTimeoutMs,
            'OPENCODE_EXPERIMENTAL_BASH_MAX_OUTPUT_LENGTH' => $this->opencodeExperimentalBashMaxOutput,
            'OPENCODE_ENABLE_EXPERIMENTAL_MODELS' => $this->opencodeEnableExperimentalModels ? '1' : '',
            'COMPOSER_PROCESS_TIMEOUT' => $this->composerProcessTimeout,
        ];

        $service->setEnvVars($vars);

        $this->statusMessage = 'Environment variables saved successfully.';
        $this->statusType = 'success';
    }

    public function saveOpencodeConfig(AiToolConfigService $service): void
    {
        $config = json_decode($this->opencodeConfigJson, true);

        if (! is_array($config)) {
            $this->statusMessage = 'Invalid JSON — please fix syntax errors before saving.';
            $this->statusType = 'error';

            return;
        }

        $service->setOpencodeConfig($config);

        $this->opencodeConfigJson = (string) json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->statusMessage = 'Opencode config saved successfully.';
        $this->statusType = 'success';
    }

    public function saveOpencodeAuth(AiToolConfigService $service): void
    {
        $auth = json_decode($this->opencodeAuthJson, true);

        if (! is_array($auth)) {
            $this->statusMessage = 'Invalid JSON — please fix syntax errors before saving.';
            $this->statusType = 'error';

            return;
        }

        $service->setOpencodeAuth($auth);

        $this->opencodeAuthJson = (string) json_encode($auth, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->statusMessage = 'Opencode auth saved successfully.';
        $this->statusType = 'success';
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.dashboard.ai-tools-config');
    }
}
