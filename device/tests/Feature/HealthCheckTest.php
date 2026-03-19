<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

it('returns healthy status when database is connected', function () {
    Process::fake([
        "top -bn1 | grep 'Cpu(s)' | awk '{print \$2}'" => Process::result(output: '25.3'),
        "free -m | awk '/^Mem:/ {print \$3}'" => Process::result(output: '4096'),
        "free -m | awk '/^Mem:/ {print \$2}'" => Process::result(output: '8192'),
        "df -BG / | awk 'NR==2 {print \$3}'" => Process::result(output: '32G'),
        "df -BG / | awk 'NR==2 {print \$2}'" => Process::result(output: '64G'),
        'cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null' => Process::result(output: '52000'),
    ]);

    $response = $this->get('/api/health');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'timestamp',
            'checks' => ['database'],
            'metrics' => [
                'cpu_percent',
                'ram_used_mb',
                'ram_total_mb',
                'ram_percent',
                'disk_used_gb',
                'disk_total_gb',
                'disk_percent',
                'temperature_c',
            ],
        ])
        ->assertJson([
            'status' => 'healthy',
            'checks' => ['database' => 'ok'],
        ]);
});

it('returns unhealthy status when database is not connected', function () {
    Process::fake([
        "top -bn1 | grep 'Cpu(s)' | awk '{print \$2}'" => Process::result(output: '25.3'),
        "free -m | awk '/^Mem:/ {print \$3}'" => Process::result(output: '4096'),
        "free -m | awk '/^Mem:/ {print \$2}'" => Process::result(output: '8192'),
        "df -BG / | awk 'NR==2 {print \$3}'" => Process::result(output: '32G'),
        "df -BG / | awk 'NR==2 {print \$2}'" => Process::result(output: '64G'),
        'cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null' => Process::result(output: '52000'),
    ]);

    DB::shouldReceive('connection')->andThrow(new \Exception('Database unavailable'));

    $response = $this->get('/api/health');

    $response->assertStatus(503)
        ->assertJson([
            'status' => 'unhealthy',
            'checks' => ['database' => 'failed'],
        ]);
});
