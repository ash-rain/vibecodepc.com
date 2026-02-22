<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\DeviceHeartbeat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeviceHeartbeat>
 */
class DeviceHeartbeatFactory extends Factory
{
    protected $model = DeviceHeartbeat::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => Device::factory()->claimed(),
            'cpu_percent' => fake()->randomFloat(1, 5, 95),
            'cpu_temp' => fake()->randomFloat(1, 35, 75),
            'ram_used_mb' => fake()->numberBetween(512, 7168),
            'ram_total_mb' => 8192,
            'disk_used_gb' => fake()->randomFloat(2, 10, 200),
            'disk_total_gb' => 256,
            'running_projects' => fake()->numberBetween(0, 5),
            'tunnel_active' => fake()->boolean(70),
            'firmware_version' => '1.0.0',
            'os_version' => 'Debian 12.8',
        ];
    }
}
