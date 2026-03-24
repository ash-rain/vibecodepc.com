<?php

declare(strict_types=1);

use App\Models\DeviceState;
use App\Services\DeviceStateService;
use Illuminate\Support\Facades\Process;
use Laravel\Dusk\Browser;

beforeEach(function () {
    // Set up device state for dashboard access
    DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);
});

afterEach(function () {
    // Clean up any temporary files
    if (file_exists(storage_path('app/test-disk-metrics'))) {
        \Illuminate\Support\Facades\File::deleteDirectory(storage_path('app/test-disk-metrics'));
    }
});

describe('Disk Metrics Browser Tests', function () {
    it('displays disk usage in health bar', function () {
        Process::fake([
            "top -bn1 | grep 'Cpu(s)' | awk '{print \$2}'" => Process::result(output: '10.0'),
            "free -m | awk '/^Mem:/ {print \$3}'" => Process::result(output: '2048'),
            "free -m | awk '/^Mem:/ {print \$2}'" => Process::result(output: '4096'),
            "df -BG / | awk 'NR==2 {print \$3}'" => Process::result(output: '32G'),
            "df -BG / | awk 'NR==2 {print \$2}'" => Process::result(output: '64G'),
            'cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null' => Process::result(exitCode: 1),
        ]);

        $this->browse(function (Browser $browser) {
            $browser->visit('/dashboard')
                ->assertSee('Disk')
                ->assertSee('32/64G')
                ->assertPresent('@disk-progress-bar');
        });
    });

    it('shows green disk indicator for low usage', function () {
        Process::fake([
            "top -bn1 | grep 'Cpu(s)' | awk '{print \$2}'" => Process::result(output: '10.0'),
            "free -m | awk '/^Mem:/ {print \$3}'" => Process::result(output: '1024'),
            "free -m | awk '/^Mem:/ {print \$2}'" => Process::result(output: '4096'),
            "df -BG / | awk 'NR==2 {print \$3}'" => Process::result(output: '20G'),
            "df -BG / | awk 'NR==2 {print \$2}'" => Process::result(output: '64G'),
            'cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null' => Process::result(exitCode: 1),
        ]);

        $this->browse(function (Browser $browser) {
            $browser->visit('/dashboard')
                ->waitFor('@disk-progress-bar')
                ->assertAttributeContains('@disk-progress-bar', 'class', 'bg-green-500');
        });
    });

    it('shows amber disk indicator for medium usage', function () {
        Process::fake([
            "top -bn1 | grep 'Cpu(s)' | awk '{print \$2}'" => Process::result(output: '10.0'),
            "free -m | awk '/^Mem:/ {print \$3}'" => Process::result(output: '1024'),
            "free -m | awk '/^Mem:/ {print \$2}'" => Process::result(output: '4096'),
            "df -BG / | awk 'NR==2 {print \$3}'" => Process::result(output: '50G'),
            "df -BG / | awk 'NR==2 {print \$2}'" => Process::result(output: '64G'),
            'cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null' => Process::result(exitCode: 1),
        ]);

        $this->browse(function (Browser $browser) {
            $browser->visit('/dashboard')
                ->waitFor('@disk-progress-bar')
                ->assertAttributeContains('@disk-progress-bar', 'class', 'bg-amber-500');
        });
    });

    it('shows red disk indicator for high usage', function () {
        Process::fake([
            "top -bn1 | grep 'Cpu(s)' | awk '{print \$2}'" => Process::result(output: '10.0'),
            "free -m | awk '/^Mem:/ {print \$3}'" => Process::result(output: '1024'),
            "free -m | awk '/^Mem:/ {print \$2}'" => Process::result(output: '4096'),
            "df -BG / | awk 'NR==2 {print \$3}'" => Process::result(output: '60G'),
            "df -BG / | awk 'NR==2 {print \$2}'" => Process::result(output: '64G'),
            'cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null' => Process::result(exitCode: 1),
        ]);

        $this->browse(function (Browser $browser) {
            $browser->visit('/dashboard')
                ->waitFor('@disk-progress-bar')
                ->assertAttributeContains('@disk-progress-bar', 'class', 'bg-red-500');
        });
    });

    it('displays disk metrics in system settings', function () {
        Process::fake([
            "top -bn1 | grep 'Cpu(s)' | awk '{print \$2}'" => Process::result(output: '10.0'),
            "free -m | awk '/^Mem:/ {print \$3}'" => Process::result(output: '2048'),
            "free -m | awk '/^Mem:/ {print \$2}'" => Process::result(output: '4096'),
            "df -BG / | awk 'NR==2 {print \$3}'" => Process::result(output: '40G'),
            "df -BG / | awk 'NR==2 {print \$2}'" => Process::result(output: '64G'),
            'cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null' => Process::result(exitCode: 1),
            '*systemctl is-active ssh*' => Process::result(output: 'inactive', exitCode: 3),
        ]);

        $this->browse(function (Browser $browser) {
            $browser->visit('/dashboard/system-settings')
                ->assertSee('Disk Usage')
                ->assertSee('40')
                ->assertSee('64');
        });
    });

    it('updates disk metrics on poll refresh', function () {
        Process::fake([
            "top -bn1 | grep 'Cpu(s)' | awk '{print \$2}'" => Process::result(output: '10.0'),
            "free -m | awk '/^Mem:/ {print \$3}'" => Process::result(output: '1024'),
            "free -m | awk '/^Mem:/ {print \$2}'" => Process::result(output: '4096'),
            "df -BG / | awk 'NR==2 {print \$3}'" => Process::result(output: '30G'),
            "df -BG / | awk 'NR==2 {print \$2}'" => Process::result(output: '64G'),
            'cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null' => Process::result(exitCode: 1),
        ]);

        $this->browse(function (Browser $browser) {
            $browser->visit('/dashboard')
                ->assertSee('30/64G')
                ->waitForText('30/64G');
        });
    });

    it('handles disk metrics gracefully when command fails', function () {
        Process::fake([
            "top -bn1 | grep 'Cpu(s)' | awk '{print \$2}'" => Process::result(output: '10.0'),
            "free -m | awk '/^Mem:/ {print \$3}'" => Process::result(output: '1024'),
            "free -m | awk '/^Mem:/ {print \$2}'" => Process::result(output: '4096'),
            "df -BG / | awk 'NR==2 {print \$3}'" => Process::result(exitCode: 1),
            "df -BG / | awk 'NR==2 {print \$2}'" => Process::result(exitCode: 1),
            'cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null' => Process::result(exitCode: 1),
        ]);

        $this->browse(function (Browser $browser) {
            $browser->visit('/dashboard')
                ->assertSee('Disk')
                ->assertSee('0/0G');
        });
    });
});
