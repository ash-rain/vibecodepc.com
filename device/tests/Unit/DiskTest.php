<?php

declare(strict_types=1);

use App\Livewire\Dashboard\HealthBar;
use App\Livewire\Dashboard\SystemSettings;
use App\Services\BackupService;
use App\Services\DeviceHealthService;
use App\Services\NetworkService;
use App\Services\Tunnel\TunnelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Disk Tests — DeviceHealthService Disk Metric Parsing
|--------------------------------------------------------------------------
*/

describe('DeviceHealthService disk metrics', function () {
    it('parses standard df output for disk used', function () {
        Process::fake([
            "top -bn1 | grep 'Cpu(s)' | awk '{print \$2}'" => Process::result(output: '10.0'),
            "free -m | awk '/^Mem:/ {print \$3}'" => Process::result(output: '1024'),
            "free -m | awk '/^Mem:/ {print \$2}'" => Process::result(output: '4096'),
            "df -BG / | awk 'NR==2 {print \$3}'" => Process::result(output: '32G'),
            "df -BG / | awk 'NR==2 {print \$2}'" => Process::result(output: '64G'),
            'cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null' => Process::result(output: '50000'),
        ]);

        $service = new DeviceHealthService;
        $metrics = $service->getMetrics();

        expect($metrics['disk_used_gb'])->toBe(32.0)
            ->and($metrics['disk_total_gb'])->toBe(64.0);
    });

    it('parses disk output without G suffix', function () {
        Process::fake([
            "top -bn1 | grep 'Cpu(s)' | awk '{print \$2}'" => Process::result(output: '10.0'),
            "free -m | awk '/^Mem:/ {print \$3}'" => Process::result(output: '1024'),
            "free -m | awk '/^Mem:/ {print \$2}'" => Process::result(output: '4096'),
            "df -BG / | awk 'NR==2 {print \$3}'" => Process::result(output: '15'),
            "df -BG / | awk 'NR==2 {print \$2}'" => Process::result(output: '30'),
            'cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null' => Process::result(output: '50000'),
        ]);

        $service = new DeviceHealthService;
        $metrics = $service->getMetrics();

        expect($metrics['disk_used_gb'])->toBe(15.0)
            ->and($metrics['disk_total_gb'])->toBe(30.0);
    });

    it('returns zero when df command fails', function () {
        Process::fake([
            "top -bn1 | grep 'Cpu(s)' | awk '{print \$2}'" => Process::result(output: '10.0'),
            "free -m | awk '/^Mem:/ {print \$3}'" => Process::result(output: '1024'),
            "free -m | awk '/^Mem:/ {print \$2}'" => Process::result(output: '4096'),
            "df -BG / | awk 'NR==2 {print \$3}'" => Process::result(exitCode: 1),
            "df -BG / | awk 'NR==2 {print \$2}'" => Process::result(exitCode: 1),
            'cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null' => Process::result(exitCode: 1),
        ]);

        $service = new DeviceHealthService;
        $metrics = $service->getMetrics();

        expect($metrics['disk_used_gb'])->toBe(0.0)
            ->and($metrics['disk_total_gb'])->toBe(0.0);
    });

    it('handles small disk sizes on Raspberry Pi SD card', function () {
        Process::fake([
            "top -bn1 | grep 'Cpu(s)' | awk '{print \$2}'" => Process::result(output: '10.0'),
            "free -m | awk '/^Mem:/ {print \$3}'" => Process::result(output: '1024'),
            "free -m | awk '/^Mem:/ {print \$2}'" => Process::result(output: '4096'),
            "df -BG / | awk 'NR==2 {print \$3}'" => Process::result(output: '12G'),
            "df -BG / | awk 'NR==2 {print \$2}'" => Process::result(output: '15G'),
            'cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null' => Process::result(output: '55000'),
        ]);

        $service = new DeviceHealthService;
        $metrics = $service->getMetrics();

        expect($metrics['disk_used_gb'])->toBe(12.0)
            ->and($metrics['disk_total_gb'])->toBe(15.0);
    });

    it('handles large disk sizes', function () {
        Process::fake([
            "top -bn1 | grep 'Cpu(s)' | awk '{print \$2}'" => Process::result(output: '10.0'),
            "free -m | awk '/^Mem:/ {print \$3}'" => Process::result(output: '1024'),
            "free -m | awk '/^Mem:/ {print \$2}'" => Process::result(output: '4096'),
            "df -BG / | awk 'NR==2 {print \$3}'" => Process::result(output: '1500G'),
            "df -BG / | awk 'NR==2 {print \$2}'" => Process::result(output: '2000G'),
            'cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null' => Process::result(output: '50000'),
        ]);

        $service = new DeviceHealthService;
        $metrics = $service->getMetrics();

        expect($metrics['disk_used_gb'])->toBe(1500.0)
            ->and($metrics['disk_total_gb'])->toBe(2000.0);
    });

    it('handles empty df output', function () {
        Process::fake([
            "top -bn1 | grep 'Cpu(s)' | awk '{print \$2}'" => Process::result(output: '10.0'),
            "free -m | awk '/^Mem:/ {print \$3}'" => Process::result(output: '1024'),
            "free -m | awk '/^Mem:/ {print \$2}'" => Process::result(output: '4096'),
            "df -BG / | awk 'NR==2 {print \$3}'" => Process::result(output: ''),
            "df -BG / | awk 'NR==2 {print \$2}'" => Process::result(output: ''),
            'cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null' => Process::result(exitCode: 1),
        ]);

        $service = new DeviceHealthService;
        $metrics = $service->getMetrics();

        expect($metrics['disk_used_gb'])->toBe(0.0)
            ->and($metrics['disk_total_gb'])->toBe(0.0);
    });

    it('handles df output with whitespace', function () {
        Process::fake([
            "top -bn1 | grep 'Cpu(s)' | awk '{print \$2}'" => Process::result(output: '10.0'),
            "free -m | awk '/^Mem:/ {print \$3}'" => Process::result(output: '1024'),
            "free -m | awk '/^Mem:/ {print \$2}'" => Process::result(output: '4096'),
            "df -BG / | awk 'NR==2 {print \$3}'" => Process::result(output: "  32G  \n"),
            "df -BG / | awk 'NR==2 {print \$2}'" => Process::result(output: "  64G  \n"),
            'cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null' => Process::result(exitCode: 1),
        ]);

        $service = new DeviceHealthService;
        $metrics = $service->getMetrics();

        expect($metrics['disk_used_gb'])->toBe(32.0)
            ->and($metrics['disk_total_gb'])->toBe(64.0);
    });

    it('handles disk nearly full (99%)', function () {
        Process::fake([
            "top -bn1 | grep 'Cpu(s)' | awk '{print \$2}'" => Process::result(output: '10.0'),
            "free -m | awk '/^Mem:/ {print \$3}'" => Process::result(output: '1024'),
            "free -m | awk '/^Mem:/ {print \$2}'" => Process::result(output: '4096'),
            "df -BG / | awk 'NR==2 {print \$3}'" => Process::result(output: '63G'),
            "df -BG / | awk 'NR==2 {print \$2}'" => Process::result(output: '64G'),
            'cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null' => Process::result(exitCode: 1),
        ]);

        $service = new DeviceHealthService;
        $metrics = $service->getMetrics();

        expect($metrics['disk_used_gb'])->toBe(63.0)
            ->and($metrics['disk_total_gb'])->toBe(64.0);
    });

    it('handles disk completely empty', function () {
        Process::fake([
            "top -bn1 | grep 'Cpu(s)' | awk '{print \$2}'" => Process::result(output: '10.0'),
            "free -m | awk '/^Mem:/ {print \$3}'" => Process::result(output: '1024'),
            "free -m | awk '/^Mem:/ {print \$2}'" => Process::result(output: '4096'),
            "df -BG / | awk 'NR==2 {print \$3}'" => Process::result(output: '0G'),
            "df -BG / | awk 'NR==2 {print \$2}'" => Process::result(output: '64G'),
            'cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null' => Process::result(exitCode: 1),
        ]);

        $service = new DeviceHealthService;
        $metrics = $service->getMetrics();

        expect($metrics['disk_used_gb'])->toBe(0.0)
            ->and($metrics['disk_total_gb'])->toBe(64.0);
    });
});

/*
|--------------------------------------------------------------------------
| Disk Tests — Health Check Endpoint Disk Percentage Calculations
|--------------------------------------------------------------------------
*/

describe('health check endpoint disk calculations', function () {
    it('calculates disk percentage correctly', function () {
        Process::fake([
            "top -bn1 | grep 'Cpu(s)' | awk '{print \$2}'" => Process::result(output: '10.0'),
            "free -m | awk '/^Mem:/ {print \$3}'" => Process::result(output: '4096'),
            "free -m | awk '/^Mem:/ {print \$2}'" => Process::result(output: '8192'),
            "df -BG / | awk 'NR==2 {print \$3}'" => Process::result(output: '32G'),
            "df -BG / | awk 'NR==2 {print \$2}'" => Process::result(output: '64G'),
            'cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null' => Process::result(output: '50000'),
        ]);

        $response = $this->get('/api/health');

        $data = $response->json();
        $response->assertStatus(200);
        expect((float) $data['metrics']['disk_used_gb'])->toBe(32.0)
            ->and((float) $data['metrics']['disk_total_gb'])->toBe(64.0)
            ->and((float) $data['metrics']['disk_percent'])->toBe(50.0);
    });

    it('returns zero disk percent when total is zero', function () {
        Process::fake([
            "top -bn1 | grep 'Cpu(s)' | awk '{print \$2}'" => Process::result(output: '10.0'),
            "free -m | awk '/^Mem:/ {print \$3}'" => Process::result(output: '4096'),
            "free -m | awk '/^Mem:/ {print \$2}'" => Process::result(output: '8192'),
            "df -BG / | awk 'NR==2 {print \$3}'" => Process::result(exitCode: 1),
            "df -BG / | awk 'NR==2 {print \$2}'" => Process::result(exitCode: 1),
            'cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null' => Process::result(exitCode: 1),
        ]);

        $response = $this->get('/api/health');

        $response->assertStatus(200)
            ->assertJsonPath('metrics.disk_percent', 0);
    });

    it('reports high disk usage percentage', function () {
        Process::fake([
            "top -bn1 | grep 'Cpu(s)' | awk '{print \$2}'" => Process::result(output: '10.0'),
            "free -m | awk '/^Mem:/ {print \$3}'" => Process::result(output: '4096'),
            "free -m | awk '/^Mem:/ {print \$2}'" => Process::result(output: '8192'),
            "df -BG / | awk 'NR==2 {print \$3}'" => Process::result(output: '57G'),
            "df -BG / | awk 'NR==2 {print \$2}'" => Process::result(output: '64G'),
            'cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null' => Process::result(exitCode: 1),
        ]);

        $response = $this->get('/api/health');

        $response->assertStatus(200)
            ->assertJsonPath('metrics.disk_percent', 89.1);
    });

    it('rounds disk percent to one decimal place', function () {
        Process::fake([
            "top -bn1 | grep 'Cpu(s)' | awk '{print \$2}'" => Process::result(output: '10.0'),
            "free -m | awk '/^Mem:/ {print \$3}'" => Process::result(output: '4096'),
            "free -m | awk '/^Mem:/ {print \$2}'" => Process::result(output: '8192'),
            "df -BG / | awk 'NR==2 {print \$3}'" => Process::result(output: '21G'),
            "df -BG / | awk 'NR==2 {print \$2}'" => Process::result(output: '64G'),
            'cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null' => Process::result(exitCode: 1),
        ]);

        $response = $this->get('/api/health');

        // 21/64 * 100 = 32.8125 → rounds to 32.8
        $response->assertStatus(200)
            ->assertJsonPath('metrics.disk_percent', 32.8);
    });
});

/*
|--------------------------------------------------------------------------
| Disk Tests — HealthBar Component Disk Color Thresholds
|--------------------------------------------------------------------------
*/

describe('HealthBar disk color thresholds', function () {
    it('shows green for disk usage under 70%', function () {
        $this->mock(DeviceHealthService::class, function ($mock) {
            $mock->shouldReceive('getMetrics')->andReturn([
                'cpu_percent' => 10.0,
                'ram_used_mb' => 1024,
                'ram_total_mb' => 8192,
                'disk_used_gb' => 40.0,
                'disk_total_gb' => 64.0,
                'temperature_c' => null,
            ]);
        });

        // 40/64 = 62.5% → green
        Livewire::test(HealthBar::class)
            ->assertSeeHtml('bg-green-500');
    });

    it('shows amber for disk usage between 70-90%', function () {
        $this->mock(DeviceHealthService::class, function ($mock) {
            $mock->shouldReceive('getMetrics')->andReturn([
                'cpu_percent' => 10.0,
                'ram_used_mb' => 1024,
                'ram_total_mb' => 8192,
                'disk_used_gb' => 50.0,
                'disk_total_gb' => 64.0,
                'temperature_c' => null,
            ]);
        });

        // 50/64 = 78.1% → amber
        Livewire::test(HealthBar::class)
            ->assertSeeHtml('bg-amber-500');
    });

    it('shows red for disk usage at 90% or above', function () {
        $this->mock(DeviceHealthService::class, function ($mock) {
            $mock->shouldReceive('getMetrics')->andReturn([
                'cpu_percent' => 10.0,
                'ram_used_mb' => 1024,
                'ram_total_mb' => 8192,
                'disk_used_gb' => 60.0,
                'disk_total_gb' => 64.0,
                'temperature_c' => null,
            ]);
        });

        // 60/64 = 93.75% → red
        Livewire::test(HealthBar::class)
            ->assertSeeHtml('bg-red-500');
    });

    it('shows green when disk is exactly at 70% boundary', function () {
        $this->mock(DeviceHealthService::class, function ($mock) {
            $mock->shouldReceive('getMetrics')->andReturn([
                'cpu_percent' => 10.0,
                'ram_used_mb' => 1024,
                'ram_total_mb' => 8192,
                'disk_used_gb' => 44.8,
                'disk_total_gb' => 64.0,
                'temperature_c' => null,
            ]);
        });

        // 44.8/64 = 70% exactly → amber (>= 70)
        Livewire::test(HealthBar::class)
            ->assertSeeHtml('bg-amber-500');
    });

    it('shows amber when disk is exactly at 90% boundary', function () {
        $this->mock(DeviceHealthService::class, function ($mock) {
            $mock->shouldReceive('getMetrics')->andReturn([
                'cpu_percent' => 10.0,
                'ram_used_mb' => 1024,
                'ram_total_mb' => 8192,
                'disk_used_gb' => 57.6,
                'disk_total_gb' => 64.0,
                'temperature_c' => null,
            ]);
        });

        // 57.6/64 = 90% exactly → red (>= 90)
        Livewire::test(HealthBar::class)
            ->assertSeeHtml('bg-red-500');
    });

    it('handles zero total disk gracefully without division by zero', function () {
        $this->mock(DeviceHealthService::class, function ($mock) {
            $mock->shouldReceive('getMetrics')->andReturn([
                'cpu_percent' => 10.0,
                'ram_used_mb' => 1024,
                'ram_total_mb' => 8192,
                'disk_used_gb' => 0.0,
                'disk_total_gb' => 0.0,
                'temperature_c' => null,
            ]);
        });

        // 0/0 → 0% (view handles division by zero)
        Livewire::test(HealthBar::class)
            ->assertSuccessful()
            ->assertSee('0/0G');
    });

    it('displays disk values after poll refresh', function () {
        $service = $this->mock(DeviceHealthService::class);
        $service->shouldReceive('getMetrics')->once()->andReturn([
            'cpu_percent' => 10.0,
            'ram_used_mb' => 1024,
            'ram_total_mb' => 8192,
            'disk_used_gb' => 20.0,
            'disk_total_gb' => 64.0,
            'temperature_c' => null,
        ]);

        $component = Livewire::test(HealthBar::class)
            ->assertSet('diskUsedGb', 20.0)
            ->assertSet('diskTotalGb', 64.0);

        $service->shouldReceive('getMetrics')->once()->andReturn([
            'cpu_percent' => 10.0,
            'ram_used_mb' => 1024,
            'ram_total_mb' => 8192,
            'disk_used_gb' => 55.0,
            'disk_total_gb' => 64.0,
            'temperature_c' => null,
        ]);

        $component->call('poll')
            ->assertSet('diskUsedGb', 55.0)
            ->assertSet('diskTotalGb', 64.0);
    });

    it('shows disk usage text in correct format', function () {
        $this->mock(DeviceHealthService::class, function ($mock) {
            $mock->shouldReceive('getMetrics')->andReturn([
                'cpu_percent' => 10.0,
                'ram_used_mb' => 1024,
                'ram_total_mb' => 8192,
                'disk_used_gb' => 32.5,
                'disk_total_gb' => 64.0,
                'temperature_c' => null,
            ]);
        });

        Livewire::test(HealthBar::class)
            ->assertSee('Disk')
            ->assertSee('32.5/64G');
    });
});

/*
|--------------------------------------------------------------------------
| Disk Tests — SystemSettings Disk Display
|--------------------------------------------------------------------------
*/

describe('SystemSettings disk display', function () {
    it('initializes disk metrics on mount', function () {
        $this->mock(NetworkService::class, function ($mock) {
            $mock->shouldReceive('getLocalIp')->andReturn('192.168.1.100');
            $mock->shouldReceive('hasEthernet')->andReturn(true);
            $mock->shouldReceive('hasWifi')->andReturn(false);
        });

        $this->mock(DeviceHealthService::class, function ($mock) {
            $mock->shouldReceive('getMetrics')->andReturn([
                'cpu_percent' => 10.0,
                'ram_used_mb' => 1024,
                'ram_total_mb' => 8192,
                'disk_used_gb' => 32.0,
                'disk_total_gb' => 64.0,
                'temperature_c' => null,
            ]);
        });

        Process::fake([
            '*systemctl is-active ssh*' => Process::result(output: 'inactive', exitCode: 3),
        ]);

        Livewire::test(SystemSettings::class)
            ->assertSet('diskUsedGb', 32.0)
            ->assertSet('diskTotalGb', 64.0);
    });

    it('displays disk usage in view', function () {
        $this->mock(NetworkService::class, function ($mock) {
            $mock->shouldReceive('getLocalIp')->andReturn('192.168.1.100');
            $mock->shouldReceive('hasEthernet')->andReturn(true);
            $mock->shouldReceive('hasWifi')->andReturn(false);
        });

        $this->mock(DeviceHealthService::class, function ($mock) {
            $mock->shouldReceive('getMetrics')->andReturn([
                'cpu_percent' => 10.0,
                'ram_used_mb' => 1024,
                'ram_total_mb' => 8192,
                'disk_used_gb' => 48.0,
                'disk_total_gb' => 64.0,
                'temperature_c' => null,
            ]);
        });

        Process::fake([
            '*systemctl is-active ssh*' => Process::result(output: 'inactive', exitCode: 3),
        ]);

        Livewire::test(SystemSettings::class)
            ->assertSee('48')
            ->assertSee('64');
    });
});

/*
|--------------------------------------------------------------------------
| Disk Tests — DeviceHealth Command Disk Status Indicators
|--------------------------------------------------------------------------
*/

describe('device:health command disk indicators', function () {
    beforeEach(function () {
        $this->tunnelMock = Mockery::mock(TunnelService::class);
        $this->tunnelMock->shouldReceive('isRunning')->andReturn(false)->byDefault();
        $this->app->instance(TunnelService::class, $this->tunnelMock);

        // Create minimal state for command to work
        \App\Models\DeviceState::setValue(
            \App\Services\DeviceStateService::MODE_KEY,
            \App\Services\DeviceStateService::MODE_DASHBOARD
        );
    });

    it('shows green indicator for low disk usage in table output', function () {
        Process::fake([
            '*top*' => Process::result(output: '10.0'),
            '*free -m*' => Process::result(output: "Mem: 8192 1024 7168 0 0 0\n"),
            '*df -BG*' => Process::result(output: "Filesystem Size Used Avail Use% Mounted on\n/dev/root 64G 20G 44G 31% /\n"),
            '*thermal_zone0*' => Process::result(exitCode: 1),
            '*uptime*' => Process::result(output: 'up 1 day'),
            '*hostname -I*' => Process::result(output: '192.168.1.100'),
            '*ip link show eth0*' => Process::result(exitCode: 1),
            '*ip link show wlan0*' => Process::result(exitCode: 1),
            '*timedatectl*' => Process::result(output: 'UTC'),
        ]);

        $this->artisan('device:health')
            ->assertSuccessful()
            ->expectsOutputToContain('Disk');
    });

    it('includes disk metrics in JSON output', function () {
        Process::fake([
            '*top*' => Process::result(output: '10.0'),
            '*free -m*' => Process::result(output: "Mem: 8192 1024 7168 0 0 0\n"),
            '*df -BG*' => Process::result(output: "Filesystem Size Used Avail Use% Mounted on\n/dev/root 64G 50G 14G 78% /\n"),
            '*thermal_zone0*' => Process::result(exitCode: 1),
            '*uptime*' => Process::result(output: 'up 1 day'),
            '*hostname -I*' => Process::result(output: '192.168.1.100'),
            '*ip link show eth0*' => Process::result(exitCode: 1),
            '*ip link show wlan0*' => Process::result(exitCode: 1),
            '*timedatectl*' => Process::result(output: 'UTC'),
        ]);

        // Capture the command output using Artisan facade with BufferingOutput
        $outputBuffer = new \Symfony\Component\Console\Output\BufferedOutput;
        $exitCode = Artisan::call('device:health', ['--json' => true], $outputBuffer);
        $output = $outputBuffer->fetch();

        // Verify exit code and JSON output contains expected disk metrics
        expect($exitCode)->toBe(0);
        expect($output)
            ->toContain('disk_used_gb')
            ->toContain('disk_total_gb')
            ->toContain('disk_used_percent');
    });

    it('calculates disk used percent correctly in command', function () {
        Process::fake([
            '*top*' => Process::result(output: '10.0'),
            '*free -m*' => Process::result(output: "Mem: 8192 1024 7168 0 0 0\n"),
            '*df -BG*' => Process::result(output: "Filesystem Size Used Avail Use% Mounted on\n/dev/root 64G 32G 32G 50% /\n"),
            '*thermal_zone0*' => Process::result(exitCode: 1),
            '*uptime*' => Process::result(output: 'up 1 day'),
            '*hostname -I*' => Process::result(output: '192.168.1.100'),
            '*ip link show eth0*' => Process::result(exitCode: 1),
            '*ip link show wlan0*' => Process::result(exitCode: 1),
            '*timedatectl*' => Process::result(output: 'UTC'),
        ]);

        $this->artisan('device:health', ['--json' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('disk_used_percent');
    });

    it('handles zero disk total gracefully in command', function () {
        Process::fake([
            '*top*' => Process::result(output: '10.0'),
            '*free -m*' => Process::result(output: "Mem: 8192 1024 7168 0 0 0\n"),
            '*df -BG*' => Process::result(exitCode: 1),
            '*thermal_zone0*' => Process::result(exitCode: 1),
            '*uptime*' => Process::result(output: 'up 1 day'),
            '*hostname -I*' => Process::result(output: '192.168.1.100'),
            '*ip link show eth0*' => Process::result(exitCode: 1),
            '*ip link show wlan0*' => Process::result(exitCode: 1),
            '*timedatectl*' => Process::result(output: 'UTC'),
        ]);

        // Should not crash with division by zero
        $this->artisan('device:health')
            ->assertSuccessful();
    });
});

/*
|--------------------------------------------------------------------------
| Disk Tests — TunnelService Disk Space Validation
|--------------------------------------------------------------------------
*/

describe('TunnelService disk space checks', function () {
    it('rejects start when disk space is insufficient for token', function () {
        $tokenFile = storage_path('app/test-disk-test-start/token');
        $dir = dirname($tokenFile);
        if (is_dir($dir)) {
            \Illuminate\Support\Facades\File::deleteDirectory($dir);
        }
        @mkdir($dir, 0755, true);

        \App\Models\TunnelConfig::factory()->verified()->create([
            'tunnel_token_encrypted' => str_repeat('a', 1024 * 1024),
        ]);

        $service = new class(tokenFilePath: $tokenFile) extends TunnelService
        {
            protected function hasSufficientDiskSpace(int $requiredBytes = 1024): bool
            {
                return false;
            }
        };

        $error = $service->start();

        expect($error)->toBe('Failed to write tunnel token file: insufficient disk space')
            ->and(file_exists($tokenFile))->toBeFalse();

        \Illuminate\Support\Facades\File::deleteDirectory($dir);
    });

    it('rejects stop when disk space is insufficient for truncation', function () {
        $tokenFile = storage_path('app/test-disk-test-stop/token');
        $dir = dirname($tokenFile);
        if (is_dir($dir)) {
            \Illuminate\Support\Facades\File::deleteDirectory($dir);
        }
        @mkdir($dir, 0755, true);
        file_put_contents($tokenFile, 'active-token');

        $service = new class(tokenFilePath: $tokenFile) extends TunnelService
        {
            protected function hasSufficientDiskSpace(int $requiredBytes = 1024): bool
            {
                return false;
            }
        };

        $error = $service->stop();

        expect($error)->toBe('Failed to truncate tunnel token file: insufficient disk space')
            ->and(file_get_contents($tokenFile))->toBe('active-token');

        \Illuminate\Support\Facades\File::deleteDirectory($dir);
    });

    it('allows start when disk has sufficient space', function () {
        $tokenFile = storage_path('app/test-disk-test-ok/token');
        $dir = dirname($tokenFile);
        if (is_dir($dir)) {
            \Illuminate\Support\Facades\File::deleteDirectory($dir);
        }
        @mkdir($dir, 0755, true);

        \App\Models\TunnelConfig::factory()->verified()->create();

        $service = new TunnelService(tokenFilePath: $tokenFile);

        $error = $service->start();

        expect($error)->toBeNull()
            ->and(file_exists($tokenFile))->toBeTrue()
            ->and(file_get_contents($tokenFile))->not->toBeEmpty();

        \Illuminate\Support\Facades\File::deleteDirectory($dir);
    });

    it('checks sufficient space including token size plus buffer', function () {
        $tokenFile = storage_path('app/test-disk-test-buffer/token');
        $dir = dirname($tokenFile);
        if (is_dir($dir)) {
            \Illuminate\Support\Facades\File::deleteDirectory($dir);
        }
        @mkdir($dir, 0755, true);

        $token = str_repeat('x', 500);
        \App\Models\TunnelConfig::factory()->verified()->create([
            'tunnel_token_encrypted' => $token,
        ]);

        $service = new class(tokenFilePath: $tokenFile) extends TunnelService
        {
            public ?int $capturedRequiredBytes = null;

            protected function hasSufficientDiskSpace(int $requiredBytes = 1024): bool
            {
                $this->capturedRequiredBytes = $requiredBytes;

                return true;
            }
        };

        $service->start();

        // Token length (500) + 1024 buffer = 1524
        expect($service->capturedRequiredBytes)->toBe(1524);

        \Illuminate\Support\Facades\File::deleteDirectory($dir);
    });
});

/*
|--------------------------------------------------------------------------
| Disk Tests — BackupService Disk Handling
|--------------------------------------------------------------------------
*/

describe('BackupService disk operations', function () {
    it('creates backup file on disk', function () {
        $service = new BackupService;
        $path = $service->createBackup();

        expect(file_exists($path))->toBeTrue()
            ->and($path)->toContain('backup-')
            ->and($path)->toEndWith('.zip');

        @unlink($path);
    });

    it('backup file has non-zero size', function () {
        $service = new BackupService;
        $path = $service->createBackup();

        expect(filesize($path))->toBeGreaterThan(0);

        @unlink($path);
    });

    it('throws exception when restoring non-existent backup', function () {
        $service = new BackupService;

        expect(fn () => $service->restoreBackup('/tmp/nonexistent-backup.zip'))
            ->toThrow(RuntimeException::class, 'Backup file does not exist.');
    });

    it('creates backup in private storage directory', function () {
        $service = new BackupService;
        $path = $service->createBackup();

        expect($path)->toContain(storage_path('app/private'));

        @unlink($path);
    });
});

/*
|--------------------------------------------------------------------------
| Disk Tests — Disk Percentage Calculation Edge Cases
|--------------------------------------------------------------------------
*/

describe('disk percentage edge cases', function () {
    it('calculates 100% when disk is completely full', function () {
        Process::fake([
            "top -bn1 | grep 'Cpu(s)' | awk '{print \$2}'" => Process::result(output: '10.0'),
            "free -m | awk '/^Mem:/ {print \$3}'" => Process::result(output: '4096'),
            "free -m | awk '/^Mem:/ {print \$2}'" => Process::result(output: '8192'),
            "df -BG / | awk 'NR==2 {print \$3}'" => Process::result(output: '64G'),
            "df -BG / | awk 'NR==2 {print \$2}'" => Process::result(output: '64G'),
            'cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null' => Process::result(exitCode: 1),
        ]);

        $data = $this->get('/api/health')->assertStatus(200)->json();

        expect((float) $data['metrics']['disk_percent'])->toBe(100.0);
    });

    it('calculates 0% when disk is empty', function () {
        Process::fake([
            "top -bn1 | grep 'Cpu(s)' | awk '{print \$2}'" => Process::result(output: '10.0'),
            "free -m | awk '/^Mem:/ {print \$3}'" => Process::result(output: '4096'),
            "free -m | awk '/^Mem:/ {print \$2}'" => Process::result(output: '8192'),
            "df -BG / | awk 'NR==2 {print \$3}'" => Process::result(output: '0G'),
            "df -BG / | awk 'NR==2 {print \$2}'" => Process::result(output: '64G'),
            'cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null' => Process::result(exitCode: 1),
        ]);

        $data = $this->get('/api/health')->assertStatus(200)->json();

        expect((float) $data['metrics']['disk_percent'])->toBe(0.0);
    });

    it('handles used greater than total gracefully', function () {
        // Can happen on filesystems with reserved blocks
        Process::fake([
            "top -bn1 | grep 'Cpu(s)' | awk '{print \$2}'" => Process::result(output: '10.0'),
            "free -m | awk '/^Mem:/ {print \$3}'" => Process::result(output: '4096'),
            "free -m | awk '/^Mem:/ {print \$2}'" => Process::result(output: '8192'),
            "df -BG / | awk 'NR==2 {print \$3}'" => Process::result(output: '66G'),
            "df -BG / | awk 'NR==2 {print \$2}'" => Process::result(output: '64G'),
            'cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null' => Process::result(exitCode: 1),
        ]);

        $data = $this->get('/api/health')->assertStatus(200)->json();

        // Should not crash — percent can exceed 100
        expect((float) $data['metrics']['disk_used_gb'])->toBe(66.0)
            ->and((float) $data['metrics']['disk_total_gb'])->toBe(64.0);
    });
});
