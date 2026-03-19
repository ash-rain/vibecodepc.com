<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\QuickTunnel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuickTunnel> */
class QuickTunnelFactory extends Factory
{
    protected $model = QuickTunnel::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'project_id' => null,
            'container_name' => fake()->unique()->word(),
            'container_id' => fake()->optional()->sha1(),
            'local_port' => fake()->numberBetween(3000, 9000),
            'tunnel_url' => fake()->optional()->url(),
            'status' => fake()->randomElement(['starting', 'running', 'stopped', 'error']),
            'started_at' => fake()->optional()->dateTimeBetween('-1 month', 'now'),
            'stopped_at' => fake()->optional()->dateTimeBetween('-1 month', 'now'),
        ];
    }

    public function running(): static
    {
        return $this->state(fn () => [
            'status' => 'running',
            'started_at' => now(),
            'stopped_at' => null,
        ]);
    }

    public function starting(): static
    {
        return $this->state(fn () => [
            'status' => 'starting',
            'started_at' => now(),
            'stopped_at' => null,
        ]);
    }

    public function stopped(): static
    {
        return $this->state(fn () => [
            'status' => 'stopped',
            'stopped_at' => now(),
        ]);
    }

    public function error(): static
    {
        return $this->state(fn () => [
            'status' => 'error',
            'stopped_at' => now(),
        ]);
    }

    public function forProject(?Project $project = null): static
    {
        return $this->state(fn () => [
            'project_id' => $project?->id ?? Project::factory(),
        ]);
    }

    public function dashboard(): static
    {
        return $this->state(fn () => [
            'project_id' => null,
        ]);
    }
}
