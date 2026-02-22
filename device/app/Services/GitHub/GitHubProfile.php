<?php

declare(strict_types=1);

namespace App\Services\GitHub;

final readonly class GitHubProfile
{
    public function __construct(
        public string $username,
        public ?string $name,
        public ?string $email,
        public ?string $avatarUrl,
        public string $plan = 'free',
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            username: $data['login'],
            name: $data['name'] ?? null,
            email: $data['email'] ?? null,
            avatarUrl: $data['avatar_url'] ?? null,
            plan: $data['plan']['name'] ?? 'free',
        );
    }

    /**
     * All GitHub users have Copilot access since Dec 2024 (Free tier).
     * Pro/Team/Enterprise plans include Copilot Pro/Business with unlimited completions.
     */
    public function hasCopilotAccess(): bool
    {
        return true;
    }

    public function copilotTier(): string
    {
        return match ($this->plan) {
            'pro' => 'pro',
            'team', 'enterprise' => 'business',
            default => 'free',
        };
    }
}
