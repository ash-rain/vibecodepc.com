<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Services\AiToolConfigService;
use App\Services\DevicePairingService;
use App\Services\Tunnel\TunnelService;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.dashboard', ['title' => 'Environment Variables'])]
#[Title('Environment Variables — VibeCodePC')]
class BashrcEditor extends Component
{
    public string $bashrcContent = '';

    public string $originalContent = '';

    public bool $isDirty = false;

    public bool $isPaired = false;

    public bool $isTunnelRunning = false;

    public bool $isReadOnly = false;

    public ?string $readOnlyReason = null;

    public bool $isPairingRequired = true;

    public string $statusMessage = '';

    public string $statusType = 'success';

    public bool $isSaving = false;

    /** @var array<string, string> */
    public array $envVars = [];

    /** @var array<int, array<string, string>> */
    public array $envVarList = [];

    public string $extraPath = '';

    public function mount(AiToolConfigService $aiToolConfigService, TunnelService $tunnelService, DevicePairingService $pairingService): void
    {
        $this->isPaired = $pairingService->isPaired();
        $this->isTunnelRunning = $tunnelService->isRunning();
        $this->isPairingRequired = $pairingService->isPairingRequired();
        $this->isReadOnly = $pairingService->isReadOnly();
        $this->readOnlyReason = $pairingService->getReadOnlyReason();
        $this->loadBashrc($aiToolConfigService);
    }

    private function loadBashrc(AiToolConfigService $aiToolConfigService): void
    {
        try {
            $this->envVars = $aiToolConfigService->getEnvVars();
            $this->extraPath = $this->envVars['_extra_path'] ?? '';
            unset($this->envVars['_extra_path']);

            // Convert to list for Livewire iteration
            $this->envVarList = [];
            foreach ($this->envVars as $key => $value) {
                $this->envVarList[] = ['key' => $key, 'value' => $value];
            }

            // Add empty row for new entries
            $this->envVarList[] = ['key' => '', 'value' => ''];

            // Store original content for dirty checking
            $this->originalContent = $this->serializeEnvVars();
        } catch (\Exception $e) {
            Log::error('Failed to load bashrc', ['error' => $e->getMessage()]);
            $this->statusMessage = 'Failed to load environment variables: '.$e->getMessage();
            $this->statusType = 'error';
        }
    }

    /**
     * Serialize current env vars for comparison.
     */
    private function serializeEnvVars(): string
    {
        $data = ['vars' => $this->envVarList, 'extraPath' => $this->extraPath];

        return json_encode($data);
    }

    public function updated(): void
    {
        $this->isDirty = $this->serializeEnvVars() !== $this->originalContent;
    }

    /**
     * Add a new environment variable row.
     */
    public function addEnvVar(): void
    {
        $this->envVarList[] = ['key' => '', 'value' => ''];
    }

    /**
     * Remove an environment variable at the given index.
     */
    public function removeEnvVar(int $index): void
    {
        if (isset($this->envVarList[$index])) {
            unset($this->envVarList[$index]);
            $this->envVarList = array_values($this->envVarList);
            $this->updated();
        }
    }

    /**
     * Save the environment variables to .bashrc.
     */
    public function save(AiToolConfigService $aiToolConfigService, DevicePairingService $pairingService): void
    {
        $this->isSaving = true;

        // Log unpaired save actions when pairing is optional
        if (! $pairingService->isPaired() && ! $pairingService->isPairingRequired()) {
            $pairingService->logUnpairedAction('bashrc_save', null, ['action' => 'update_env_vars']);
        }

        try {
            // Build vars array from list, filtering out empty keys
            $vars = [];
            foreach ($this->envVarList as $item) {
                if (! empty($item['key']) && preg_match('/^[A-Z_][A-Z0-9_]*$/', $item['key'])) {
                    $vars[$item['key']] = $item['value'];
                }
            }

            // Add extra path if provided
            if (! empty($this->extraPath)) {
                $vars['_extra_path'] = $this->extraPath;
            }

            $aiToolConfigService->setEnvVars($vars);

            // Reload to get actual persisted state
            $this->loadBashrc($aiToolConfigService);

            $this->statusMessage = 'Environment variables saved successfully. Run `source ~/.bashrc` in your terminal to apply changes.';
            $this->statusType = 'success';
            $this->isDirty = false;
        } catch (\Exception $e) {
            Log::error('Failed to save bashrc', ['error' => $e->getMessage()]);
            $this->statusMessage = 'Failed to save environment variables: '.$e->getMessage();
            $this->statusType = 'error';
        } finally {
            $this->isSaving = false;
        }
    }

    /**
     * Reset to defaults by removing the VibeCodePC section.
     */
    public function resetToDefaults(AiToolConfigService $aiToolConfigService): void
    {
        try {
            // Set empty vars to remove the section
            $aiToolConfigService->setEnvVars([]);

            // Reload
            $this->loadBashrc($aiToolConfigService);

            $this->statusMessage = 'Environment variables reset. All VibeCodePC-managed variables have been removed.';
            $this->statusType = 'success';
            $this->isDirty = false;
        } catch (\Exception $e) {
            Log::error('Failed to reset bashrc', ['error' => $e->getMessage()]);
            $this->statusMessage = 'Failed to reset: '.$e->getMessage();
            $this->statusType = 'error';
        }
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.dashboard.bashrc-editor');
    }
}
