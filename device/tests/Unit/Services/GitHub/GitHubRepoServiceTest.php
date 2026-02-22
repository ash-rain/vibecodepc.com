<?php

declare(strict_types=1);

use App\Services\GitHub\GitHubRepoService;
use Illuminate\Support\Facades\Http;

it('lists user repos sorted by updated', function () {
    Http::fake([
        'api.github.com/user/repos*' => Http::response([
            [
                'full_name' => 'user/repo-one',
                'name' => 'repo-one',
                'description' => 'First repo',
                'private' => false,
                'default_branch' => 'main',
                'language' => 'PHP',
                'updated_at' => '2026-02-20T10:00:00Z',
            ],
            [
                'full_name' => 'user/repo-two',
                'name' => 'repo-two',
                'description' => null,
                'private' => true,
                'default_branch' => 'master',
                'language' => 'JavaScript',
                'updated_at' => '2026-02-19T10:00:00Z',
            ],
        ]),
    ]);

    $service = new GitHubRepoService;
    $repos = $service->listUserRepos('gho_test_token');

    expect($repos)->toHaveCount(2)
        ->and($repos[0]->fullName)->toBe('user/repo-one')
        ->and($repos[0]->isPrivate)->toBeFalse()
        ->and($repos[0]->language)->toBe('PHP')
        ->and($repos[1]->fullName)->toBe('user/repo-two')
        ->and($repos[1]->isPrivate)->toBeTrue();
});

it('searches user repos', function () {
    Http::fake([
        'api.github.com/user' => Http::response([
            'login' => 'testuser',
        ]),
        'api.github.com/search/repositories*' => Http::response([
            'items' => [
                [
                    'full_name' => 'testuser/my-app',
                    'name' => 'my-app',
                    'description' => 'My application',
                    'private' => false,
                    'default_branch' => 'main',
                    'language' => 'PHP',
                    'updated_at' => '2026-02-20T10:00:00Z',
                ],
            ],
        ]),
    ]);

    $service = new GitHubRepoService;
    $repos = $service->searchUserRepos('gho_test_token', 'my-app');

    expect($repos)->toHaveCount(1)
        ->and($repos[0]->fullName)->toBe('testuser/my-app')
        ->and($repos[0]->name)->toBe('my-app');
});

it('builds authenticated clone URL', function () {
    $service = new GitHubRepoService;
    $url = $service->authenticatedCloneUrl('gho_test_token', 'user/repo');

    expect($url)->toBe('https://x-access-token:gho_test_token@github.com/user/repo.git');
});
