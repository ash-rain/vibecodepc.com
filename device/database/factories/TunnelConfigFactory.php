<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TunnelConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TunnelConfig> */
class TunnelConfigFactory extends Factory
{
    protected $model = TunnelConfig::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'subdomain' => fake()->userName(),
            'tunnel_token_encrypted' => null,
            'tunnel_id' => null,
            'status' => 'pending',
            'verified_at' => null,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn () => [
            'status' => 'active',
            'tunnel_id' => fake()->uuid(),
            'tunnel_token_encrypted' => fake()->sha256(),
            'verified_at' => now(),
        ]);
    }
}
