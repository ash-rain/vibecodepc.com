<?php

declare(strict_types=1);

use App\Livewire\Dashboard\HealthBar;
use App\Services\DeviceHealthService;
use Livewire\Livewire;

// Test rendering with initial metrics
it('renders the health bar component', function () {
    $mockMetrics = [
        'cpu_percent' => 45.5,
        'ram_used_mb' => 4096,
        'ram_total_mb' => 16384,
        'disk_used_gb' => 120.5,
        'disk_total_gb' => 500.0,
        'temperature_c' => 55.0,
    ];

    $this->mock(DeviceHealthService::class, function ($mock) use ($mockMetrics) {
        $mock->shouldReceive('getMetrics')->andReturn($mockMetrics);
    });

    Livewire::test(HealthBar::class)
        ->assertSuccessful();
});

// Test CPU metric display
it('displays cpu percentage', function () {
    $mockMetrics = [
        'cpu_percent' => 45.5,
        'ram_used_mb' => 4096,
        'ram_total_mb' => 16384,
        'disk_used_gb' => 120.5,
        'disk_total_gb' => 500.0,
        'temperature_c' => null,
    ];

    $this->mock(DeviceHealthService::class, function ($mock) use ($mockMetrics) {
        $mock->shouldReceive('getMetrics')->andReturn($mockMetrics);
    });

    Livewire::test(HealthBar::class)
        ->assertSee('CPU')
        ->assertSee('45.5%');
});

// Test RAM metrics display
it('displays ram usage', function () {
    $mockMetrics = [
        'cpu_percent' => 30.0,
        'ram_used_mb' => 8192,
        'ram_total_mb' => 16384,
        'disk_used_gb' => 120.5,
        'disk_total_gb' => 500.0,
        'temperature_c' => null,
    ];

    $this->mock(DeviceHealthService::class, function ($mock) use ($mockMetrics) {
        $mock->shouldReceive('getMetrics')->andReturn($mockMetrics);
    });

    Livewire::test(HealthBar::class)
        ->assertSee('RAM')
        ->assertSee('8192/16384M');
});

// Test Disk metrics display
it('displays disk usage', function () {
    $mockMetrics = [
        'cpu_percent' => 30.0,
        'ram_used_mb' => 4096,
        'ram_total_mb' => 16384,
        'disk_used_gb' => 250.0,
        'disk_total_gb' => 500.0,
        'temperature_c' => null,
    ];

    $this->mock(DeviceHealthService::class, function ($mock) use ($mockMetrics) {
        $mock->shouldReceive('getMetrics')->andReturn($mockMetrics);
    });

    Livewire::test(HealthBar::class)
        ->assertSee('Disk')
        ->assertSee('250/500G');
});

// Test temperature display when available
it('displays temperature when available', function () {
    $mockMetrics = [
        'cpu_percent' => 30.0,
        'ram_used_mb' => 4096,
        'ram_total_mb' => 16384,
        'disk_used_gb' => 120.5,
        'disk_total_gb' => 500.0,
        'temperature_c' => 65.5,
    ];

    $this->mock(DeviceHealthService::class, function ($mock) use ($mockMetrics) {
        $mock->shouldReceive('getMetrics')->andReturn($mockMetrics);
    });

    Livewire::test(HealthBar::class)
        ->assertSee('Temp')
        ->assertSeeHtml('65.5&deg;C');
});

// Test temperature not shown when null
it('does not display temperature when not available', function () {
    $mockMetrics = [
        'cpu_percent' => 30.0,
        'ram_used_mb' => 4096,
        'ram_total_mb' => 16384,
        'disk_used_gb' => 120.5,
        'disk_total_gb' => 500.0,
        'temperature_c' => null,
    ];

    $this->mock(DeviceHealthService::class, function ($mock) use ($mockMetrics) {
        $mock->shouldReceive('getMetrics')->andReturn($mockMetrics);
    });

    Livewire::test(HealthBar::class)
        ->assertDontSee('Temp');
});

// Test polling updates metrics
it('updates metrics when poll method is called', function () {
    $initialMetrics = [
        'cpu_percent' => 30.0,
        'ram_used_mb' => 4096,
        'ram_total_mb' => 16384,
        'disk_used_gb' => 120.5,
        'disk_total_gb' => 500.0,
        'temperature_c' => 55.0,
    ];

    $updatedMetrics = [
        'cpu_percent' => 75.0,
        'ram_used_mb' => 12288,
        'ram_total_mb' => 16384,
        'disk_used_gb' => 350.0,
        'disk_total_gb' => 500.0,
        'temperature_c' => 70.0,
    ];

    $service = $this->mock(DeviceHealthService::class);
    $service->shouldReceive('getMetrics')->once()->andReturn($initialMetrics);

    $component = Livewire::test(HealthBar::class)
        ->assertSet('cpuPercent', 30.0)
        ->assertSet('ramUsedMb', 4096);

    // Update the mock for the poll call
    $service->shouldReceive('getMetrics')->once()->andReturn($updatedMetrics);

    $component->call('poll')
        ->assertSet('cpuPercent', 75.0)
        ->assertSet('ramUsedMb', 12288)
        ->assertSet('temperatureC', 70.0);
});

// Test metrics state after mount
it('initializes metrics on mount', function () {
    $mockMetrics = [
        'cpu_percent' => 50.0,
        'ram_used_mb' => 8192,
        'ram_total_mb' => 16384,
        'disk_used_gb' => 200.0,
        'disk_total_gb' => 500.0,
        'temperature_c' => 60.0,
    ];

    $this->mock(DeviceHealthService::class, function ($mock) use ($mockMetrics) {
        $mock->shouldReceive('getMetrics')->andReturn($mockMetrics);
    });

    Livewire::test(HealthBar::class)
        ->assertSet('cpuPercent', 50.0)
        ->assertSet('ramUsedMb', 8192)
        ->assertSet('ramTotalMb', 16384)
        ->assertSet('diskUsedGb', 200.0)
        ->assertSet('diskTotalGb', 500.0)
        ->assertSet('temperatureC', 60.0);
});

// Test edge case: zero values
it('handles zero values gracefully', function () {
    $mockMetrics = [
        'cpu_percent' => 0.0,
        'ram_used_mb' => 0,
        'ram_total_mb' => 0,
        'disk_used_gb' => 0.0,
        'disk_total_gb' => 0.0,
        'temperature_c' => null,
    ];

    $this->mock(DeviceHealthService::class, function ($mock) use ($mockMetrics) {
        $mock->shouldReceive('getMetrics')->andReturn($mockMetrics);
    });

    Livewire::test(HealthBar::class)
        ->assertSuccessful()
        ->assertSee('0%')
        ->assertSee('0/0M')
        ->assertSee('0/0G');
});

// Test high values
it('displays high metric values correctly', function () {
    $mockMetrics = [
        'cpu_percent' => 99.9,
        'ram_used_mb' => 64000,
        'ram_total_mb' => 65536,
        'disk_used_gb' => 1995.5,
        'disk_total_gb' => 2000.0,
        'temperature_c' => 85.5,
    ];

    $this->mock(DeviceHealthService::class, function ($mock) use ($mockMetrics) {
        $mock->shouldReceive('getMetrics')->andReturn($mockMetrics);
    });

    Livewire::test(HealthBar::class)
        ->assertSee('99.9%')
        ->assertSee('64000/65536M')
        ->assertSee('1995.5/2000G')
        ->assertSeeHtml('85.5&deg;C');
});

// Test wire:poll attribute exists in rendered view
it('has poll attribute in rendered html', function () {
    $mockMetrics = [
        'cpu_percent' => 30.0,
        'ram_used_mb' => 4096,
        'ram_total_mb' => 16384,
        'disk_used_gb' => 120.5,
        'disk_total_gb' => 500.0,
        'temperature_c' => null,
    ];

    $this->mock(DeviceHealthService::class, function ($mock) use ($mockMetrics) {
        $mock->shouldReceive('getMetrics')->andReturn($mockMetrics);
    });

    Livewire::test(HealthBar::class)
        ->assertSeeHtml('wire:poll.10s="poll"');
});

// Test CPU color thresholds - green (< 60%)
it('applies green color for low cpu usage', function () {
    $mockMetrics = [
        'cpu_percent' => 45.0,
        'ram_used_mb' => 4096,
        'ram_total_mb' => 16384,
        'disk_used_gb' => 120.5,
        'disk_total_gb' => 500.0,
        'temperature_c' => null,
    ];

    $this->mock(DeviceHealthService::class, function ($mock) use ($mockMetrics) {
        $mock->shouldReceive('getMetrics')->andReturn($mockMetrics);
    });

    Livewire::test(HealthBar::class)
        ->assertSeeHtml('bg-green-500');
});

// Test CPU color threshold - amber (60-85%)
it('applies amber color for medium cpu usage', function () {
    $mockMetrics = [
        'cpu_percent' => 70.0,
        'ram_used_mb' => 4096,
        'ram_total_mb' => 16384,
        'disk_used_gb' => 120.5,
        'disk_total_gb' => 500.0,
        'temperature_c' => null,
    ];

    $this->mock(DeviceHealthService::class, function ($mock) use ($mockMetrics) {
        $mock->shouldReceive('getMetrics')->andReturn($mockMetrics);
    });

    Livewire::test(HealthBar::class)
        ->assertSeeHtml('bg-amber-500');
});

// Test CPU color threshold - red (>= 85%)
it('applies red color for high cpu usage', function () {
    $mockMetrics = [
        'cpu_percent' => 90.0,
        'ram_used_mb' => 4096,
        'ram_total_mb' => 16384,
        'disk_used_gb' => 120.5,
        'disk_total_gb' => 500.0,
        'temperature_c' => null,
    ];

    $this->mock(DeviceHealthService::class, function ($mock) use ($mockMetrics) {
        $mock->shouldReceive('getMetrics')->andReturn($mockMetrics);
    });

    Livewire::test(HealthBar::class)
        ->assertSeeHtml('bg-red-500');
});

// Test RAM color threshold - red (>= 85%)
it('applies red color for high ram usage', function () {
    $mockMetrics = [
        'cpu_percent' => 30.0,
        'ram_used_mb' => 15000,
        'ram_total_mb' => 16384,
        'disk_used_gb' => 120.5,
        'disk_total_gb' => 500.0,
        'temperature_c' => null,
    ];

    $this->mock(DeviceHealthService::class, function ($mock) use ($mockMetrics) {
        $mock->shouldReceive('getMetrics')->andReturn($mockMetrics);
    });

    Livewire::test(HealthBar::class)
        ->assertSeeHtml('bg-red-500');
});

// Test Disk color thresholds - green (< 70%)
it('applies green color for low disk usage', function () {
    $mockMetrics = [
        'cpu_percent' => 30.0,
        'ram_used_mb' => 4096,
        'ram_total_mb' => 16384,
        'disk_used_gb' => 300.0,
        'disk_total_gb' => 500.0,
        'temperature_c' => null,
    ];

    $this->mock(DeviceHealthService::class, function ($mock) use ($mockMetrics) {
        $mock->shouldReceive('getMetrics')->andReturn($mockMetrics);
    });

    Livewire::test(HealthBar::class)
        ->assertSeeHtml('bg-green-500');
});

// Test Disk color threshold - red (>= 90%)
it('applies red color for high disk usage', function () {
    $mockMetrics = [
        'cpu_percent' => 30.0,
        'ram_used_mb' => 4096,
        'ram_total_mb' => 16384,
        'disk_used_gb' => 480.0,
        'disk_total_gb' => 500.0,
        'temperature_c' => null,
    ];

    $this->mock(DeviceHealthService::class, function ($mock) use ($mockMetrics) {
        $mock->shouldReceive('getMetrics')->andReturn($mockMetrics);
    });

    Livewire::test(HealthBar::class)
        ->assertSeeHtml('bg-red-500');
});

// Test temperature color thresholds
test('temperature color thresholds', function (float $temp, string $expectedClass) {
    $mockMetrics = [
        'cpu_percent' => 30.0,
        'ram_used_mb' => 4096,
        'ram_total_mb' => 16384,
        'disk_used_gb' => 120.5,
        'disk_total_gb' => 500.0,
        'temperature_c' => $temp,
    ];

    $this->mock(DeviceHealthService::class, function ($mock) use ($mockMetrics) {
        $mock->shouldReceive('getMetrics')->andReturn($mockMetrics);
    });

    Livewire::test(HealthBar::class)
        ->assertSeeHtml($expectedClass);
})->with([
    [45.0, 'text-green-400'],
    [65.0, 'text-amber-400'],
    [80.0, 'text-red-400'],
]);

// Test poll method returns updated metrics
test('poll method refreshes all metrics', function () {
    $initialMetrics = [
        'cpu_percent' => 25.0,
        'ram_used_mb' => 2048,
        'ram_total_mb' => 8192,
        'disk_used_gb' => 100.0,
        'disk_total_gb' => 500.0,
        'temperature_c' => 40.0,
    ];

    $updatedMetrics = [
        'cpu_percent' => 55.0,
        'ram_used_mb' => 6144,
        'ram_total_mb' => 8192,
        'disk_used_gb' => 300.0,
        'disk_total_gb' => 500.0,
        'temperature_c' => 65.0,
    ];

    $service = $this->mock(DeviceHealthService::class);
    $service->shouldReceive('getMetrics')->once()->andReturn($initialMetrics);

    $component = Livewire::test(HealthBar::class)
        ->assertSet('cpuPercent', 25.0)
        ->assertSet('ramUsedMb', 2048)
        ->assertSet('diskUsedGb', 100.0)
        ->assertSet('temperatureC', 40.0);

    $service->shouldReceive('getMetrics')->once()->andReturn($updatedMetrics);

    $component->call('poll')
        ->assertSet('cpuPercent', 55.0)
        ->assertSet('ramUsedMb', 6144)
        ->assertSet('diskUsedGb', 300.0)
        ->assertSet('temperatureC', 65.0);
});

// Test polling multiple times
test('polling multiple times updates metrics progressively', function () {
    $metricsSequence = [
        ['cpu_percent' => 10.0, 'ram_used_mb' => 1024, 'ram_total_mb' => 8192, 'disk_used_gb' => 50.0, 'disk_total_gb' => 500.0, 'temperature_c' => 35.0],
        ['cpu_percent' => 20.0, 'ram_used_mb' => 2048, 'ram_total_mb' => 8192, 'disk_used_gb' => 100.0, 'disk_total_gb' => 500.0, 'temperature_c' => 40.0],
        ['cpu_percent' => 30.0, 'ram_used_mb' => 3072, 'ram_total_mb' => 8192, 'disk_used_gb' => 150.0, 'disk_total_gb' => 500.0, 'temperature_c' => 45.0],
    ];

    $service = $this->mock(DeviceHealthService::class);
    $service->shouldReceive('getMetrics')->once()->andReturn($metricsSequence[0]);

    $component = Livewire::test(HealthBar::class)
        ->assertSet('cpuPercent', 10.0);

    foreach (array_slice($metricsSequence, 1) as $metrics) {
        $service->shouldReceive('getMetrics')->once()->andReturn($metrics);
        $component->call('poll');
    }

    $component
        ->assertSet('cpuPercent', 30.0)
        ->assertSet('ramUsedMb', 3072)
        ->assertSet('diskUsedGb', 150.0);
});
