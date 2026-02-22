<?php

namespace Database\Factories;

use App\Models\TunnelRoute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TunnelRequestLog>
 */
class TunnelRequestLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tunnel_route_id' => TunnelRoute::factory(),
            'status_code' => fake()->randomElement([200, 200, 200, 201, 301, 404, 500]),
            'response_time_ms' => fake()->numberBetween(10, 2000),
            'created_at' => fake()->dateTimeBetween('-7 days'),
        ];
    }
}
