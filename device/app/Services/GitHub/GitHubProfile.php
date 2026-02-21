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
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            username: $data['login'],
            name: $data['name'] ?? null,
            email: $data['email'] ?? null,
            avatarUrl: $data['avatar_url'] ?? null,
        );
    }
}
