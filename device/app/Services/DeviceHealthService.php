<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Process;

class DeviceHealthService
{
    /**
     * @return array{
     *     cpu_percent: float,
     *     ram_used_mb: int,
     *     ram_total_mb: int,
     *     disk_used_gb: float,
     *     disk_total_gb: float,
     *     temperature_c: float|null,
     * }
     */
    public function getMetrics(): array
    {
        return [
            'cpu_percent' => $this->getCpuPercent(),
            'ram_used_mb' => $this->getRamUsedMb(),
            'ram_total_mb' => $this->getRamTotalMb(),
            'disk_used_gb' => $this->getDiskUsedGb(),
            'disk_total_gb' => $this->getDiskTotalGb(),
            'temperature_c' => $this->getTemperature(),
        ];
    }

    private function getCpuPercent(): float
    {
        $result = Process::run("top -bn1 | grep 'Cpu(s)' | awk '{print $2}'");

        if ($result->successful() && is_numeric(trim($result->output()))) {
            return round((float) trim($result->output()), 1);
        }

        // macOS fallback
        $result = Process::run("ps -A -o %cpu | awk '{s+=$1} END {print s}'");

        if ($result->successful() && is_numeric(trim($result->output()))) {
            return min(100.0, round((float) trim($result->output()), 1));
        }

        return 0.0;
    }

    private function getRamUsedMb(): int
    {
        $result = Process::run("free -m | awk '/^Mem:/ {print $3}'");

        if ($result->successful() && is_numeric(trim($result->output()))) {
            return (int) trim($result->output());
        }

        return 0;
    }

    private function getRamTotalMb(): int
    {
        $result = Process::run("free -m | awk '/^Mem:/ {print $2}'");

        if ($result->successful() && is_numeric(trim($result->output()))) {
            return (int) trim($result->output());
        }

        return 0;
    }

    private function getDiskUsedGb(): float
    {
        $result = Process::run("df -BG / | awk 'NR==2 {print $3}'");

        if ($result->successful()) {
            return (float) str_replace('G', '', trim($result->output()));
        }

        return 0.0;
    }

    private function getDiskTotalGb(): float
    {
        $result = Process::run("df -BG / | awk 'NR==2 {print $2}'");

        if ($result->successful()) {
            return (float) str_replace('G', '', trim($result->output()));
        }

        return 0.0;
    }

    private function getTemperature(): ?float
    {
        $result = Process::run('cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null');

        if ($result->successful() && is_numeric(trim($result->output()))) {
            return round((float) trim($result->output()) / 1000, 1);
        }

        return null;
    }
}
