<?php

declare(strict_types=1);

namespace App\Services\GitHub;

readonly class GitHubRepo
{
    public function __construct(
        public string $fullName,
        public string $name,
        public ?string $description,
        public bool $isPrivate,
        public string $defaultBranch,
        public ?string $language,
        public string $updatedAt,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            fullName: $data['full_name'],
            name: $data['name'],
            description: $data['description'] ?? null,
            isPrivate: $data['private'] ?? false,
            defaultBranch: $data['default_branch'] ?? 'main',
            language: $data['language'] ?? null,
            updatedAt: $data['updated_at'] ?? '',
        );
    }
}
