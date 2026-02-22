<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\TunnelRoute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TunnelRoute>
 */
class TunnelRouteFactory extends Factory
{
    protected $model = TunnelRoute::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => Device::factory()->claimed(),
            'subdomain' => fake()->userName(),
            'path' => '/',
            'target_port' => fake()->randomElement([80, 3000, 4321, 5173, 8000, 8080]),
            'project_name' => fake()->words(2, true),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
