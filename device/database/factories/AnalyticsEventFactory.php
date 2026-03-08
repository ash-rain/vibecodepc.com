<?php

namespace Database\Factories;

use App\Models\AnalyticsEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

class AnalyticsEventFactory extends Factory
{
    protected $model = AnalyticsEvent::class;

    public function definition(): array
    {
        return [
            'event_type' => $this->faker->randomElement(['tunnel.completed', 'tunnel.skipped', 'wizard.completed']),
            'category' => $this->faker->randomElement(['tunnel', 'wizard', 'project']),
            'properties' => null,
            'occurred_at' => now(),
        ];
    }

    public function tunnelCompleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'tunnel.completed',
            'category' => 'tunnel',
            'properties' => ['subdomain' => 'test-device'],
        ]);
    }

    public function tunnelSkipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'tunnel.skipped',
            'category' => 'tunnel',
            'properties' => ['reason' => 'user_choice'],
        ]);
    }
}
