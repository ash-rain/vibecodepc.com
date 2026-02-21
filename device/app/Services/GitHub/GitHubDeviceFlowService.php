<?php

declare(strict_types=1);

namespace App\Services\GitHub;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

class GitHubDeviceFlowService
{
    public function __construct(
        private readonly string $clientId,
    ) {}

    public function initiateDeviceFlow(): DeviceFlowResult
    {
        $response = Http::withHeaders(['Accept' => 'application/json'])
            ->timeout(10)
            ->post('https://github.com/login/device/code', [
                'client_id' => $this->clientId,
                'scope' => 'repo user read:org',
            ]);

        $response->throw();

        return DeviceFlowResult::fromArray($response->json());
    }

    public function pollForToken(string $deviceCode): ?GitHubTokenResult
    {
        $response = Http::withHeaders(['Accept' => 'application/json'])
            ->timeout(10)
            ->post('https://github.com/login/oauth/access_token', [
                'client_id' => $this->clientId,
                'device_code' => $deviceCode,
                'grant_type' => 'urn:ietf:params:oauth:grant-type:device_code',
            ]);

        $data = $response->json();

        if (isset($data['access_token'])) {
            return GitHubTokenResult::fromArray($data);
        }

        return null;
    }

    public function getUserProfile(string $token): GitHubProfile
    {
        $response = Http::withToken($token)
            ->timeout(10)
            ->get('https://api.github.com/user');

        $response->throw();

        return GitHubProfile::fromArray($response->json());
    }

    public function checkCopilotAccess(string $token): bool
    {
        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->get('https://api.github.com/copilot_internal/v2/token');

            return $response->successful();
        } catch (\Exception) {
            return false;
        }
    }

    public function configureGitIdentity(string $name, string $email): void
    {
        Process::run(sprintf('git config --global user.name %s', escapeshellarg($name)));
        Process::run(sprintf('git config --global user.email %s', escapeshellarg($email)));
    }
}
