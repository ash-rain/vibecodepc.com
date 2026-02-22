<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use VibecodePC\Common\Enums\DeviceStatus;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Device>
 */
class DeviceFactory extends Factory
{
    protected $model = Device::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'status' => DeviceStatus::Unclaimed,
            'hardware_serial' => fake()->regexify('[0-9a-f]{16}'),
            'firmware_version' => '1.0.0',
        ];
    }

    public function claimed(?User $user = null): static
    {
        return $this->state(fn () => [
            'status' => DeviceStatus::Claimed,
            'user_id' => $user?->id ?? User::factory(),
            'paired_at' => now(),
        ]);
    }

    public function deactivated(): static
    {
        return $this->state(fn () => [
            'status' => DeviceStatus::Deactivated,
        ]);
    }

    public function online(): static
    {
        return $this->claimed()->state(fn () => [
            'is_online' => true,
            'last_heartbeat_at' => now(),
            'cpu_percent' => fake()->randomFloat(1, 5, 85),
            'cpu_temp' => fake()->randomFloat(1, 35, 70),
            'ram_used_mb' => fake()->numberBetween(1024, 6144),
            'ram_total_mb' => 8192,
            'disk_used_gb' => fake()->randomFloat(2, 10, 200),
            'disk_total_gb' => 256,
            'os_version' => 'Debian 12.8',
            'tunnel_url' => 'https://'.fake()->userName().'.vibecodepc.com',
        ]);
    }

    public function offline(): static
    {
        return $this->claimed()->state(fn () => [
            'is_online' => false,
            'last_heartbeat_at' => now()->subMinutes(10),
        ]);
    }
}
