<?php

declare(strict_types=1);

namespace App\Services\GitHub;

use App\Services\CircuitBreaker;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class GitHubDeviceFlowService
{
    private const string CIRCUIT_BREAKER_NAME = 'github_oauth';

    private const int FAILURE_THRESHOLD = 5;

    private const int RECOVERY_TIMEOUT = 60;

    private readonly CircuitBreaker $circuitBreaker;

    public function __construct(
        private readonly string $clientId,
    ) {
        $this->circuitBreaker = new CircuitBreaker(
            self::CIRCUIT_BREAKER_NAME,
            self::FAILURE_THRESHOLD,
            self::RECOVERY_TIMEOUT
        );
    }

    /**
     * Get the current circuit breaker state for monitoring.
     *
     * @return array{state: string, failure_count: int, success_count: int, is_closed: bool}
     */
    public function getCircuitBreakerState(): array
    {
        return [
            'state' => $this->circuitBreaker->getCurrentState(),
            'failure_count' => $this->circuitBreaker->getFailureCount(),
            'success_count' => $this->circuitBreaker->getSuccessCount(),
            'is_closed' => $this->circuitBreaker->isClosed(),
        ];
    }

    /**
     * Manually reset the circuit breaker to closed state.
     */
    public function resetCircuitBreaker(): void
    {
        $this->circuitBreaker->reset();
    }

    /**
     * Initiate the GitHub device flow.
     *
     * @throws \RuntimeException When circuit breaker is open
     * @throws ConnectionException When network request fails
     * @throws RequestException When HTTP request returns error status
     */
    public function initiateDeviceFlow(): DeviceFlowResult
    {
        if (! $this->circuitBreaker->isClosed()) {
            throw new \RuntimeException('Circuit breaker is OPEN: GitHub OAuth device flow temporarily unavailable');
        }

        try {
            $response = Http::withHeaders(['Accept' => 'application/json'])
                ->timeout(config('vibecodepc.http_client.timeout.default'))
                ->post('https://github.com/login/device/code', [
                    'client_id' => $this->clientId,
                    'scope' => 'repo user read:org',
                ]);

            $response->throw();
            $this->circuitBreaker->recordSuccess();

            return DeviceFlowResult::fromArray($response->json());
        } catch (ConnectionException $e) {
            $this->circuitBreaker->recordFailure();
            Log::warning('GitHub device flow initiation failed', ['error' => $e->getMessage()]);
            throw $e;
        } catch (RequestException $e) {
            $this->circuitBreaker->recordFailure();
            Log::warning('GitHub device flow initiation failed', ['error' => $e->getMessage(), 'status' => $e->response?->status()]);
            throw $e;
        }
    }

    public const SLOW_DOWN = 'slow_down';

    /**
     * @return GitHubTokenResult|string|null Token result, error string (terminal), self::SLOW_DOWN, or null if pending.
     *
     * @throws \RuntimeException When circuit breaker is open
     * @throws ConnectionException When network request fails
     * @throws RequestException When HTTP request returns error status
     */
    public function pollForToken(string $deviceCode): GitHubTokenResult|string|null
    {
        if (! $this->circuitBreaker->isClosed()) {
            throw new \RuntimeException('Circuit breaker is OPEN: GitHub OAuth token exchange temporarily unavailable');
        }

        try {
            $response = Http::withHeaders(['Accept' => 'application/json'])
                ->timeout(config('vibecodepc.http_client.timeout.default'))
                ->post('https://github.com/login/oauth/access_token', [
                    'client_id' => $this->clientId,
                    'device_code' => $deviceCode,
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:device_code',
                ]);

            // Check for HTTP errors (non-2xx status codes)
            if ($response->failed()) {
                throw new RequestException($response);
            }

            $data = $response->json();

            Log::debug('GitHub token poll response', ['status' => $response->status(), 'data' => $data]);

            if (isset($data['access_token'])) {
                $this->circuitBreaker->recordSuccess();

                return GitHubTokenResult::fromArray($data);
            }

            $error = $data['error'] ?? null;

            if ($error === 'slow_down') {
                // slow_down is a rate limit response, not a failure
                return self::SLOW_DOWN;
            }

            // These are terminal errors — stop polling
            if (in_array($error, ['expired_token', 'access_denied', 'unsupported_grant_type', 'incorrect_client_credentials', 'incorrect_device_code'])) {
                return $data['error_description'] ?? $error;
            }

            // authorization_pending — keep polling (not a failure)
            return null;
        } catch (ConnectionException $e) {
            $this->circuitBreaker->recordFailure();
            Log::warning('GitHub OAuth token exchange failed', ['error' => $e->getMessage()]);
            throw $e;
        } catch (RequestException $e) {
            $this->circuitBreaker->recordFailure();
            Log::warning('GitHub OAuth token exchange failed', ['error' => $e->getMessage(), 'status' => $e->response?->status()]);
            throw $e;
        }
    }

    public function getUserProfile(string $token): GitHubProfile
    {
        $response = Http::withToken($token)
            ->timeout(config('vibecodepc.http_client.timeout.default'))
            ->get('https://api.github.com/user');

        $response->throw();

        return GitHubProfile::fromArray($response->json());
    }

    /**
     * Detect Copilot access from the user's GitHub profile.
     *
     * All GitHub users have Copilot Free since Dec 2024.
     * The old `copilot_internal/v2/token` endpoint only works with the
     * official Copilot OAuth app's client_id, not custom apps.
     */
    public function checkCopilotAccess(GitHubProfile $profile): bool
    {
        return $profile->hasCopilotAccess();
    }

    public function configureGitIdentity(string $name, string $email): void
    {
        Process::run(sprintf('git config --global user.name %s', escapeshellarg($name)));
        Process::run(sprintf('git config --global user.email %s', escapeshellarg($email)));
    }
}
