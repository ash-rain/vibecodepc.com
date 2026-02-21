<?php

declare(strict_types=1);

use App\Services\DeviceHealthService;
use Illuminate\Support\Facades\Process;

it('returns health metrics', function () {
    Process::fake([
        "top -bn1 | grep 'Cpu(s)' | awk '{print \$2}'" => Process::result(output: '25.3'),
        "free -m | awk '/^Mem:/ {print \$3}'" => Process::result(output: '4096'),
        "free -m | awk '/^Mem:/ {print \$2}'" => Process::result(output: '8192'),
        "df -BG / | awk 'NR==2 {print \$3}'" => Process::result(output: '32G'),
        "df -BG / | awk 'NR==2 {print \$2}'" => Process::result(output: '64G'),
        'cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null' => Process::result(output: '52000'),
    ]);

    $service = new DeviceHealthService;
    $metrics = $service->getMetrics();

    expect($metrics)->toHaveKeys(['cpu_percent', 'ram_used_mb', 'ram_total_mb', 'disk_used_gb', 'disk_total_gb', 'temperature_c'])
        ->and($metrics['cpu_percent'])->toBe(25.3)
        ->and($metrics['ram_used_mb'])->toBe(4096)
        ->and($metrics['ram_total_mb'])->toBe(8192)
        ->and($metrics['disk_used_gb'])->toBe(32.0)
        ->and($metrics['disk_total_gb'])->toBe(64.0)
        ->and($metrics['temperature_c'])->toBe(52.0);
});

it('returns null temperature when not available', function () {
    Process::fake([
        '*' => Process::result(exitCode: 1),
    ]);

    $service = new DeviceHealthService;
    $metrics = $service->getMetrics();

    expect($metrics['temperature_c'])->toBeNull();
});
