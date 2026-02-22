<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Services\BackupService;
use App\Services\DeviceHealthService;
use App\Services\NetworkService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.dashboard', ['title' => 'Settings'])]
#[Title('Settings — VibeCodePC')]
class SystemSettings extends Component
{
    use WithFileUploads;

    public ?string $localIp = null;

    public bool $hasEthernet = false;

    public bool $hasWifi = false;

    public float $diskUsedGb = 0;

    public float $diskTotalGb = 0;

    public bool $sshEnabled = false;

    public string $statusMessage = '';

    /** @var TemporaryUploadedFile|null */
    public $backupFile = null;

    public function mount(NetworkService $networkService, DeviceHealthService $healthService): void
    {
        $this->localIp = $networkService->getLocalIp();
        $this->hasEthernet = $networkService->hasEthernet();
        $this->hasWifi = $networkService->hasWifi();

        $metrics = $healthService->getMetrics();
        $this->diskUsedGb = $metrics['disk_used_gb'];
        $this->diskTotalGb = $metrics['disk_total_gb'];

        $this->sshEnabled = $this->isSshRunning();
    }

    public function toggleSsh(): void
    {
        if ($this->sshEnabled) {
            Process::run('sudo systemctl stop ssh && sudo systemctl disable ssh');
        } else {
            Process::run('sudo systemctl enable ssh && sudo systemctl start ssh');
        }

        $this->sshEnabled = $this->isSshRunning();
        $this->statusMessage = $this->sshEnabled ? 'SSH enabled.' : 'SSH disabled.';
    }

    public function checkForUpdates(): void
    {
        $result = Process::timeout(60)->run('sudo apt-get update -qq');

        $this->statusMessage = $result->successful()
            ? 'Package list updated. Check for upgradable packages.'
            : 'Failed to check for updates.';
    }

    public function factoryReset(): void
    {
        Artisan::call('device:factory-reset', ['--force' => true]);

        $this->redirect('/');
    }

    public function restartDevice(): void
    {
        $this->statusMessage = 'Restarting device...';
        Process::run('sudo reboot');
    }

    public function shutdownDevice(): void
    {
        $this->statusMessage = 'Shutting down...';
        Process::run('sudo shutdown now');
    }

    public function createBackup(BackupService $backupService): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $path = $backupService->createBackup();

        return response()->streamDownload(function () use ($path): void {
            echo file_get_contents($path);
            @unlink($path);
        }, basename($path), ['Content-Type' => 'application/zip']);
    }

    public function restoreBackup(BackupService $backupService): void
    {
        $this->validate([
            'backupFile' => ['required', 'file', 'mimes:zip', 'max:50000'],
        ]);

        try {
            $backupService->restoreBackup($this->backupFile->getRealPath());
            $this->statusMessage = 'Backup restored successfully. Some settings may require a restart to take effect.';
        } catch (\RuntimeException $e) {
            $this->statusMessage = 'Restore failed: '.$e->getMessage();
        }

        $this->backupFile = null;
    }

    public function render()
    {
        return view('livewire.dashboard.system-settings');
    }

    private function isSshRunning(): bool
    {
        $result = Process::run('systemctl is-active ssh');

        return $result->successful() && str_contains(trim($result->output()), 'active');
    }
}
