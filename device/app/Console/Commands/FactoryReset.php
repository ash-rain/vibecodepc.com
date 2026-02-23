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
        {--force : Skip confirmation prompt}';

    protected $description = 'Erase all settings and return the device to its initial state';

    public function handle(TunnelService $tunnelService, WizardProgressService $wizardService, DeviceStateService $stateService): int
    {
        if (! $this->option('force') && ! $this->confirm('This will erase ALL data, projects, and settings. Continue?')) {
            $this->info('Cancelled.');

            return self::SUCCESS;
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
}
