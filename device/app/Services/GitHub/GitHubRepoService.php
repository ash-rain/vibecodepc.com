<?php

declare(strict_types=1);

namespace App\Services\GitHub;

use Illuminate\Support\Facades\Http;

class GitHubRepoService
{
    /** @return GitHubRepo[] */
    public function listUserRepos(string $token, int $perPage = 30, int $page = 1): array
    {
        $response = Http::withToken($token)
            ->timeout(10)
            ->get('https://api.github.com/user/repos', [
                'sort' => 'updated',
                'per_page' => $perPage,
                'page' => $page,
                'affiliation' => 'owner',
            ]);

        $response->throw();

        return array_map(
            fn (array $repo) => GitHubRepo::fromArray($repo),
            $response->json(),
        );
    }

    /** @return GitHubRepo[] */
    public function searchUserRepos(string $token, string $query, int $perPage = 15): array
    {
        $username = $this->getUsername($token);

        $response = Http::withToken($token)
            ->timeout(10)
            ->get('https://api.github.com/search/repositories', [
                'q' => "{$query} user:{$username}",
                'per_page' => $perPage,
                'sort' => 'updated',
            ]);

        $response->throw();

        return array_map(
            fn (array $repo) => GitHubRepo::fromArray($repo),
            $response->json('items') ?? [],
        );
    }

    public function authenticatedCloneUrl(string $token, string $fullName): string
    {
        return "https://x-access-token:{$token}@github.com/{$fullName}.git";
    }

    private function getUsername(string $token): string
    {
        $response = Http::withToken($token)
            ->timeout(10)
            ->get('https://api.github.com/user');

        $response->throw();

        return $response->json('login');
    }
}
