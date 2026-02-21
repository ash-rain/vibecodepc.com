<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Services\DeviceHealthService;
use App\Services\NetworkService;
use Illuminate\Support\Facades\Process;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.dashboard', ['title' => 'Settings'])]
#[Title('Settings — VibeCodePC')]
class SystemSettings extends Component
{
    public ?string $localIp = null;

    public bool $hasEthernet = false;

    public bool $hasWifi = false;

    public float $diskUsedGb = 0;

    public float $diskTotalGb = 0;

    public bool $sshEnabled = false;

    public string $statusMessage = '';

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
        Process::run('sudo vibecodepc reset');
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
