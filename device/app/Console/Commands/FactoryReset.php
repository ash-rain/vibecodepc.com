<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AiProviderConfig;
use App\Models\GitHubCredential;
use App\Models\Project;
use App\Models\ProjectLog;
use App\Models\TunnelConfig;
use App\Services\DeviceStateService;
use App\Services\Tunnel\TunnelService;
use App\Services\WizardProgressService;
use Illuminate\Console\Command;

class FactoryReset extends Command
{
    protected $signature = 'device:factory-reset
    {--force : Skip confirmation prompt}
    {--confirm-code= : Confirmation code for non-interactive execution}';

    protected $description = 'Erase all settings and return the device to its initial state';

    private const CONFIRMATION_LENGTH = 6;

    public function handle(TunnelService $tunnelService, WizardProgressService $wizardService, DeviceStateService $stateService): int
    {
        if ($this->option('force')) {
            // Proceed with reset when --force is used
        } elseif ($this->option('confirm-code')) {
            // Non-interactive mode: verify provided confirmation code
            if (! $this->validateConfirmationCode($this->option('confirm-code'))) {
                $this->error('Invalid confirmation code. Factory reset aborted.');

                return self::FAILURE;
            }
        } else {
            // Interactive mode: generate and require confirmation code
            $code = $this->generateConfirmationCode();

            $this->warn('⚠️  FACTORY RESET - DANGEROUS OPERATION  ⚠️');
            $this->warn('This will permanently erase ALL data, projects, and settings.');
            $this->warn('This action cannot be undone.');
            $this->newLine();
            $this->info("To proceed, type the confirmation code: {$code}");

            $userInput = $this->ask('Enter confirmation code');

            if ($userInput !== $code) {
                $this->error('Confirmation code mismatch. Factory reset aborted.');

                return self::FAILURE;
            }
        }

        $this->info('Stopping tunnel...');
        $tunnelService->stop();

        $this->info('Clearing database...');
        TunnelConfig::truncate();
        AiProviderConfig::truncate();
        GitHubCredential::truncate();
        ProjectLog::truncate();
        Project::truncate();

        $this->info('Resetting wizard...');
        $wizardService->resetWizard();
        $stateService->setMode(DeviceStateService::MODE_WIZARD);

        $this->info('Factory reset complete. The setup wizard will appear on next visit.');

        return self::SUCCESS;
    }

    private function generateConfirmationCode(): string
    {
        return strtoupper(substr(str_shuffle(str_repeat('ABCDEFGHJKLMNPQRSTUVWXYZ23456789', self::CONFIRMATION_LENGTH)), 0, self::CONFIRMATION_LENGTH));
    }

    private function validateConfirmationCode(string $code): bool
    {
        if (strlen($code) !== self::CONFIRMATION_LENGTH) {
            return false;
        }

        // Must contain only allowed characters (alphanumeric, uppercase, no ambiguous chars)
        $allowedChars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $allowedPattern = '/^['.preg_quote($allowedChars, '/').']+$/D';

        return preg_match($allowedPattern, $code) === 1;
    }
}
