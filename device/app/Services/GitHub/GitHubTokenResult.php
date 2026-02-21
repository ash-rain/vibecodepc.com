<?php

declare(strict_types=1);

namespace App\Services\GitHub;

final readonly class GitHubTokenResult
{
    public function __construct(
        public string $accessToken,
        public string $tokenType,
        public string $scope,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            accessToken: $data['access_token'],
            tokenType: $data['token_type'],
            scope: $data['scope'] ?? '',
        );
    }
}
