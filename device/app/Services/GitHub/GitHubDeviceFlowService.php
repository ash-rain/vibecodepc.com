<?php

declare(strict_types=1);

namespace App\Services\GitHub;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

    public const SLOW_DOWN = 'slow_down';

    /**
     * @return GitHubTokenResult|string|null Token result, error string (terminal), self::SLOW_DOWN, or null if pending.
     */
    public function pollForToken(string $deviceCode): GitHubTokenResult|string|null
    {
        $response = Http::withHeaders(['Accept' => 'application/json'])
            ->timeout(10)
            ->post('https://github.com/login/oauth/access_token', [
                'client_id' => $this->clientId,
                'device_code' => $deviceCode,
                'grant_type' => 'urn:ietf:params:oauth:grant-type:device_code',
            ]);

        $data = $response->json();

        Log::debug('GitHub token poll response', ['status' => $response->status(), 'data' => $data]);

        if (isset($data['access_token'])) {
            return GitHubTokenResult::fromArray($data);
        }

        $error = $data['error'] ?? null;

        if ($error === 'slow_down') {
            return self::SLOW_DOWN;
        }

        // These are terminal errors — stop polling
        if (in_array($error, ['expired_token', 'access_denied', 'unsupported_grant_type', 'incorrect_client_credentials', 'incorrect_device_code'])) {
            return $data['error_description'] ?? $error;
        }

        // authorization_pending — keep polling
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
