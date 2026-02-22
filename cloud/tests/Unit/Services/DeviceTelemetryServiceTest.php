<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Device;
use App\Models\DeviceHeartbeat;
use App\Services\DeviceTelemetryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceTelemetryServiceTest extends TestCase
{
    use RefreshDatabase;

    private DeviceTelemetryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DeviceTelemetryService;
    }

    public function test_process_heartbeat_creates_heartbeat_record(): void
    {
        $device = Device::factory()->claimed()->create();

        $metrics = [
            'cpu_percent' => 42.5,
            'cpu_temp' => 55.3,
            'ram_used_mb' => 3072,
            'ram_total_mb' => 8192,
            'disk_used_gb' => 64.5,
            'disk_total_gb' => 256,
            'running_projects' => 2,
            'tunnel_active' => true,
            'firmware_version' => '1.0.0',
            'os_version' => 'Debian 12.8',
        ];

        $heartbeat = $this->service->processHeartbeat($device, $metrics);

        $this->assertInstanceOf(DeviceHeartbeat::class, $heartbeat);
        $this->assertTrue($heartbeat->exists);
        $this->assertEquals($device->id, $heartbeat->device_id);
        $this->assertEquals(42.5, $heartbeat->cpu_percent);
        $this->assertEquals(55.3, $heartbeat->cpu_temp);
        $this->assertEquals(3072, $heartbeat->ram_used_mb);
        $this->assertEquals(8192, $heartbeat->ram_total_mb);
        $this->assertEquals(64.5, $heartbeat->disk_used_gb);
        $this->assertEquals(256, $heartbeat->disk_total_gb);
        $this->assertEquals(2, $heartbeat->running_projects);
        $this->assertTrue($heartbeat->tunnel_active);
        $this->assertDatabaseHas('device_heartbeats', [
            'device_id' => $device->id,
            'cpu_percent' => 42.5,
            'firmware_version' => '1.0.0',
        ]);
    }

    public function test_process_heartbeat_updates_device_telemetry(): void
    {
        $device = Device::factory()->claimed()->create([
            'is_online' => false,
            'last_heartbeat_at' => null,
        ]);

        $metrics = [
            'cpu_percent' => 65.2,
            'cpu_temp' => 60.1,
            'ram_used_mb' => 4096,
            'ram_total_mb' => 8192,
            'disk_used_gb' => 100.0,
            'disk_total_gb' => 256,
            'os_version' => 'Debian 12.8',
        ];

        $this->service->processHeartbeat($device, $metrics);

        $device->refresh();
        $this->assertTrue($device->is_online);
        $this->assertNotNull($device->last_heartbeat_at);
        $this->assertEquals(65.2, $device->cpu_percent);
        $this->assertEquals(60.1, $device->cpu_temp);
        $this->assertEquals(4096, $device->ram_used_mb);
        $this->assertEquals(8192, $device->ram_total_mb);
        $this->assertEquals(100.0, $device->disk_used_gb);
        $this->assertEquals(256, $device->disk_total_gb);
        $this->assertEquals('Debian 12.8', $device->os_version);
    }

    public function test_mark_stale_devices_offline(): void
    {
        $staleDevice = Device::factory()->online()->create([
            'last_heartbeat_at' => now()->subMinutes(10),
        ]);

        $freshDevice = Device::factory()->online()->create([
            'last_heartbeat_at' => now(),
        ]);

        $count = $this->service->markStaleDevicesOffline();

        $this->assertEquals(1, $count);

        $staleDevice->refresh();
        $this->assertFalse($staleDevice->is_online);

        $freshDevice->refresh();
        $this->assertTrue($freshDevice->is_online);
    }

    public function test_is_device_online_returns_true_for_recent_heartbeat(): void
    {
        $device = Device::factory()->online()->create([
            'last_heartbeat_at' => now(),
        ]);

        $this->assertTrue($this->service->isDeviceOnline($device));
    }

    public function test_is_device_online_returns_false_for_stale_heartbeat(): void
    {
        $device = Device::factory()->online()->create([
            'last_heartbeat_at' => now()->subMinutes(10),
        ]);

        $this->assertFalse($this->service->isDeviceOnline($device));
    }
}
