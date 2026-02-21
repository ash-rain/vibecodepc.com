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
}
