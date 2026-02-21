<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Services\DeviceHealthService;
use Livewire\Component;

class HealthBar extends Component
{
    public float $cpuPercent = 0;

    public int $ramUsedMb = 0;

    public int $ramTotalMb = 0;

    public float $diskUsedGb = 0;

    public float $diskTotalGb = 0;

    public ?float $temperatureC = null;

    public function mount(DeviceHealthService $healthService): void
    {
        $this->refreshMetrics($healthService);
    }

    public function poll(DeviceHealthService $healthService): void
    {
        $this->refreshMetrics($healthService);
    }

    public function render()
    {
        return view('livewire.dashboard.health-bar');
    }

    private function refreshMetrics(DeviceHealthService $healthService): void
    {
        $metrics = $healthService->getMetrics();

        $this->cpuPercent = $metrics['cpu_percent'];
        $this->ramUsedMb = $metrics['ram_used_mb'];
        $this->ramTotalMb = $metrics['ram_total_mb'];
        $this->diskUsedGb = $metrics['disk_used_gb'];
        $this->diskTotalGb = $metrics['disk_total_gb'];
        $this->temperatureC = $metrics['temperature_c'];
    }
}
