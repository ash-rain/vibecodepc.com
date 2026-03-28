<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CloudCredential;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CloudCredential> */
class CloudCredentialFactory extends Factory
{
    protected $model = CloudCredential::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'pairing_token_encrypted' => 'fake-token-'.fake()->uuid(),
            'cloud_username' => null,
            'cloud_email' => null,
            'cloud_url' => fake()->url(),
            'is_paired' => false,
            'paired_at' => null,
        ];
    }

    public function paired(): static
    {
        return $this->state(fn () => [
            'is_paired' => true,
            'paired_at' => now(),
            'cloud_username' => fake()->userName(),
            'cloud_email' => fake()->email(),
            'cloud_url' => fake()->url(),
            'pairing_token_encrypted' => 'fake-token-'.fake()->uuid(),
        ]);
    }

    public function unpaired(): static
    {
        return $this->state(fn () => [
            'is_paired' => false,
            'paired_at' => null,
            'cloud_username' => null,
            'cloud_email' => null,
        ]);
    }

    public function withToken(string $token): static
    {
        return $this->state(fn () => [
            'pairing_token_encrypted' => $token,
        ]);
    }
}
