<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Services\ConfigAuditLogService;
use App\Services\ConfigFileService;
use App\Services\ConfigReloadService;
use App\Services\DevicePairingService;
use App\Services\Tunnel\TunnelService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.dashboard', ['title' => 'Barx Environment Config'])]
#[Title('Barx Environment Config — VibeCodePC')]
class BarxEditor extends Component
{
    public bool $isPaired = false;

    public bool $isTunnelRunning = false;

    public bool $isReadOnly = false;

    public ?string $readOnlyReason = null;

    public bool $isPairingRequired = true;

    public string $statusMessage = '';

    public string $statusType = 'success';

    public bool $isSaving = false;

    public bool $isDirty = false;

    public string $originalContent = '';

    /** @var array<int, array<string, string>> */
    public array $envVarList = [];

    public string $extraPath = '';

    /** @var array<int, array<string, string>> */
    public array $backups = [];

    public string $selectedBackup = '';

    public ?string $contentHash = null;

    private const SECTION_START = '# === VibeCodePC Barx ===';

    private const SECTION_END = '# === END VibeCodePC Barx ===';

    private const ENCRYPTED_PREFIX = 'ENC:';

    private const SENSITIVE_PATTERNS = ['_API_KEY', '_TOKEN', '_SECRET', '_PASSWORD', '_AUTH'];

    public function mount(ConfigFileService $configFileService, TunnelService $tunnelService, DevicePairingService $pairingService): void
    {
        $this->isPaired = $pairingService->isPaired();
        $this->isTunnelRunning = $tunnelService->isRunning();
        $this->isPairingRequired = $pairingService->isPairingRequired();
        $this->isReadOnly = $pairingService->isReadOnly();
        $this->readOnlyReason = $pairingService->getReadOnlyReason();
        $this->loadBarx($configFileService);
    }

    /**
     * Load environment variables from the .barx file.
     */
    private function loadBarx(ConfigFileService $configFileService): void
    {
        try {
            $content = $configFileService->getContent('barx', null);
            $this->parseBarxContent($content);
            $this->originalContent = $this->serializeContent();
            $this->isDirty = false;
            $this->contentHash = $content !== '' ? $configFileService->getContentHash($content) : null;
            $this->backups = $configFileService->listBackups('barx', null);
        } catch (\Exception $e) {
            Log::error('Failed to load .barx file', ['error' => $e->getMessage()]);
            $this->statusMessage = 'Failed to load Barx config: '.$e->getMessage();
            $this->statusType = 'error';
            $this->envVarList = [['key' => '', 'value' => '']];
            $this->extraPath = '';
        }
    }

    /**
     * Parse the .barx file content into env vars and PATH.
     */
    private function parseBarxContent(string $content): void
    {
        $this->envVarList = [];
        $this->extraPath = '';

        $start = strpos($content, self::SECTION_START);
        $end = strpos($content, self::SECTION_END);

        if ($start === false || $end === false || $start >= $end) {
            // No managed section found, start with empty list
            $this->envVarList = [['key' => '', 'value' => '']];

            return;
        }

        $section = substr($content, $start + strlen(self::SECTION_START), $end - $start - strlen(self::SECTION_START));

        // Parse regular export statements (skip PATH — handled separately)
        preg_match_all('/^export (?!PATH=)([A-Z_][A-Z0-9_]*)="([^"]*)"$/m', $section, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $this->envVarList[] = [
                'key' => $match[1],
                'value' => $this->decryptIfEncrypted($match[2]),
            ];
        }

        // Parse the PATH extra prefix line
        if (preg_match('/^export PATH="(.+?):\$PATH"$/m', $section, $pathMatch)) {
            $this->extraPath = $pathMatch[1];
        }

        // Add empty row for new entries if needed
        if (empty($this->envVarList)) {
            $this->envVarList = [['key' => '', 'value' => '']];
        } else {
            $this->envVarList[] = ['key' => '', 'value' => ''];
        }
    }

    /**
     * Serialize current content for comparison.
     */
    private function serializeContent(): string
    {
        $data = ['vars' => $this->envVarList, 'extraPath' => $this->extraPath];

        return json_encode($data);
    }

    public function updated(): void
    {
        $this->isDirty = $this->serializeContent() !== $this->originalContent;
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
     * Save the environment variables to the .barx file.
     */
    public function save(ConfigFileService $configFileService, ConfigReloadService $reloadService, DevicePairingService $pairingService): void
    {
        $this->isSaving = true;

        // Log unpaired save actions when pairing is optional
        if (! $pairingService->isPaired() && ! $pairingService->isPairingRequired()) {
            $pairingService->logUnpairedAction('barx_save', null, ['action' => 'update_barx_config']);
        }

        try {
            // Build content
            $lines = [];

            if (! empty($this->extraPath)) {
                $lines[] = 'export PATH="'.addslashes($this->extraPath).':$PATH"';
            }

            foreach ($this->envVarList as $item) {
                if (! empty($item['key']) && preg_match('/^[A-Z_][A-Z0-9_]*$/', $item['key'])) {
                    $encryptedValue = $this->encryptIfSensitive($item['key'], $item['value']);
                    $lines[] = 'export '.$item['key'].'="'.addslashes($encryptedValue).'"';
                }
            }

            if (empty($lines)) {
                // Remove the section entirely
                $content = '';
            } else {
                array_unshift($lines, self::SECTION_START);
                $lines[] = self::SECTION_END;
                $content = implode("\n", $lines)."\n";
            }

            // Get expected hash for conflict detection
            $expectedHash = $this->contentHash;

            $configFileService->putContent('barx', $content, null, $expectedHash);

            // Update hash after successful save
            $this->contentHash = $configFileService->getContentHash($content);

            // Reload to get actual persisted state
            $this->loadBarx($configFileService);

            // Get reload status
            $reloadStatus = $reloadService->getReloadStatus('barx');

            $this->statusMessage = 'Barx config saved successfully.';
            if ($reloadStatus['requires_manual'] ?? false) {
                $this->statusMessage .= ' '.$reloadStatus['instructions'];
            }
            $this->statusType = 'success';
            $this->isDirty = false;
        } catch (\RuntimeException $e) {
            // Handle conflict detection
            if (str_contains($e->getMessage(), 'modified by another user')) {
                $this->statusMessage = 'Conflict detected: '.$e->getMessage().' Please reload the file before saving.';
                $this->statusType = 'error';
            } else {
                Log::error('Failed to save .barx file', ['error' => $e->getMessage()]);
                $this->statusMessage = 'Failed to save Barx config: '.$e->getMessage();
                $this->statusType = 'error';
            }
        } catch (\Exception $e) {
            Log::error('Failed to save .barx file', ['error' => $e->getMessage()]);
            $this->statusMessage = 'Failed to save Barx config: '.$e->getMessage();
            $this->statusType = 'error';
        } finally {
            $this->isSaving = false;
        }
    }

    /**
     * Restore from a selected backup.
     */
    public function restore(ConfigFileService $configFileService): void
    {
        if (empty($this->selectedBackup)) {
            $this->statusMessage = 'Please select a backup to restore.';
            $this->statusType = 'error';

            return;
        }

        try {
            $configFileService->restore('barx', $this->selectedBackup, null);
            $this->loadBarx($configFileService);
            $this->selectedBackup = '';
            $this->statusMessage = 'Barx config restored from backup.';
            $this->statusType = 'success';
        } catch (\Exception $e) {
            Log::error('Failed to restore .barx from backup', ['error' => $e->getMessage()]);
            $this->statusMessage = 'Restore failed: '.$e->getMessage();
            $this->statusType = 'error';
        }
    }

    /**
     * Reset to defaults by removing the VibeCodePC section.
     */
    public function resetToDefaults(ConfigFileService $configFileService, ConfigAuditLogService $auditLogService): void
    {
        try {
            // Log the reset action
            $oldContent = $configFileService->getContent('barx', null);
            if ($oldContent !== '') {
                $path = $configFileService->resolvePath('barx', null);
                $auditLogService->log('barx', 'reset', $path, $oldContent, null, null, null);
            }

            // Save empty content to remove the section
            $configFileService->putContent('barx', '', null);

            // Reload
            $this->loadBarx($configFileService);

            $this->statusMessage = 'Barx config reset. All environment variables have been removed.';
            $this->statusType = 'success';
            $this->isDirty = false;
        } catch (\Exception $e) {
            Log::error('Failed to reset .barx file', ['error' => $e->getMessage()]);
            $this->statusMessage = 'Failed to reset: '.$e->getMessage();
            $this->statusType = 'error';
        }
    }

    /**
     * Determine if a key represents sensitive data.
     */
    private function isSensitiveKey(string $key): bool
    {
        foreach (self::SENSITIVE_PATTERNS as $pattern) {
            if (str_contains($key, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Encrypt a value if it's sensitive.
     */
    private function encryptIfSensitive(string $key, string $value): string
    {
        if (! $this->isSensitiveKey($key)) {
            return $value;
        }

        return self::ENCRYPTED_PREFIX.Crypt::encryptString($value);
    }

    /**
     * Decrypt a value if it's encrypted.
     */
    private function decryptIfEncrypted(string $value): string
    {
        if (! str_starts_with($value, self::ENCRYPTED_PREFIX)) {
            return $value;
        }

        try {
            $encrypted = substr($value, strlen(self::ENCRYPTED_PREFIX));

            return Crypt::decryptString($encrypted);
        } catch (\Throwable $e) {
            return $value;
        }
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.dashboard.barx-editor', [
            'barxPath' => config('vibecodepc.config_files.barx.path'),
        ]);
    }
}
