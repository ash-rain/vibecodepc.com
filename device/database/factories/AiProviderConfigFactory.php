<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AiProviderConfig;
use Illuminate\Database\Eloquent\Factories\Factory;
use VibecodePC\Common\Enums\AiProvider;

/** @extends Factory<AiProviderConfig> */
class AiProviderConfigFactory extends Factory
{
    protected $model = AiProviderConfig::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'provider' => fake()->randomElement(AiProvider::cases()),
            'api_key_encrypted' => 'sk-'.fake()->sha256(),
            'display_name' => null,
            'base_url' => null,
            'status' => 'pending',
            'validated_at' => null,
        ];
    }

    public function validated(): static
    {
        return $this->state(fn () => [
            'status' => 'validated',
            'validated_at' => now(),
        ]);
    }

    public function forProvider(AiProvider $provider): static
    {
        return $this->state(fn () => [
            'provider' => $provider,
        ]);
    }
}
