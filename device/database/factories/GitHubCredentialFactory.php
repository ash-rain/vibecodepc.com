<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GitHubCredential;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GitHubCredential> */
class GitHubCredentialFactory extends Factory
{
    protected $model = GitHubCredential::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'access_token_encrypted' => 'gho_'.fake()->sha256(),
            'github_username' => fake()->userName(),
            'github_email' => fake()->safeEmail(),
            'github_name' => fake()->name(),
            'has_copilot' => false,
            'token_expires_at' => null,
        ];
    }

    public function withCopilot(): static
    {
        return $this->state(fn () => [
            'has_copilot' => true,
        ]);
    }
}
