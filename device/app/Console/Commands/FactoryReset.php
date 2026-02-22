<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AiProviderConfig;
use App\Models\CloudCredential;
use App\Models\GitHubCredential;
use App\Models\Project;
use App\Models\ProjectLog;
use App\Models\TunnelConfig;
use App\Services\Tunnel\TunnelService;
use App\Services\WizardProgressService;
use Illuminate\Console\Command;

class FactoryReset extends Command
{
    protected $signature = 'device:factory-reset
        {--force : Skip confirmation prompt}';

    protected $description = 'Erase all settings and return the device to its initial state';

    public function handle(TunnelService $tunnelService, WizardProgressService $wizardService): int
    {
        if (! $this->option('force') && ! $this->confirm('This will erase ALL data, projects, and settings. Continue?')) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $this->info('Stopping tunnel...');
        $tunnelService->stop();

        $this->info('Removing tunnel credentials...');
        $envFile = '/etc/cloudflared/tunnel.env';
        if (file_exists($envFile)) {
            @unlink($envFile);
        }

        $this->info('Clearing database...');
        TunnelConfig::truncate();
        CloudCredential::truncate();
        AiProviderConfig::truncate();
        GitHubCredential::truncate();
        ProjectLog::truncate();
        Project::truncate();

        $this->info('Resetting wizard...');
        $wizardService->resetWizard();

        $this->info('Factory reset complete. The setup wizard will appear on next visit.');

        return self::SUCCESS;
    }
}
