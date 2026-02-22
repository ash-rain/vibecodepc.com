<?php

declare(strict_types=1);

namespace App\Livewire\Wizard;

use App\Models\GitHubCredential;
use App\Services\GitHub\GitHubDeviceFlowService;
use App\Services\WizardProgressService;
use Livewire\Component;
use VibecodePC\Common\Enums\WizardStep;

class GitHub extends Component
{
    public string $status = 'idle';

    public string $userCode = '';

    public string $verificationUri = '';

    public string $deviceCode = '';

    public string $githubUsername = '';

    public string $githubName = '';

    public string $githubEmail = '';

    public bool $hasCopilot = false;

    public string $error = '';

    public int $pollInterval = 10;

    public function mount(): void
    {
        $existing = GitHubCredential::current();

        if ($existing) {
            $this->status = 'connected';
            $this->githubUsername = $existing->github_username;
            $this->githubName = $existing->github_name ?? '';
            $this->githubEmail = $existing->github_email ?? '';
            $this->hasCopilot = $existing->has_copilot;
        }
    }

    public function startDeviceFlow(GitHubDeviceFlowService $githubService): void
    {
        try {
            $result = $githubService->initiateDeviceFlow();

            $this->deviceCode = $result->deviceCode;
            $this->userCode = $result->userCode;
            $this->verificationUri = $result->verificationUri;
            $this->pollInterval = max($result->interval + 1, 6);
            $this->status = 'polling';
            $this->error = '';
        } catch (\Exception $e) {
            $this->error = 'Could not start GitHub authentication: '.$e->getMessage();
        }
    }

    public function checkAuthStatus(GitHubDeviceFlowService $githubService): void
    {
        if ($this->status !== 'polling' || ! $this->deviceCode) {
            return;
        }

        try {
            $result = $githubService->pollForToken($this->deviceCode);

            if ($result === null) {
                return;
            }

            if ($result === GitHubDeviceFlowService::SLOW_DOWN) {
                $this->pollInterval = min($this->pollInterval + 5, 30);

                return;
            }

            if (is_string($result)) {
                $this->error = "GitHub authorization failed: {$result}";
                $this->status = 'idle';

                return;
            }

            $profile = $githubService->getUserProfile($result->accessToken);
            $hasCopilot = $githubService->checkCopilotAccess($result->accessToken);

            GitHubCredential::updateOrCreate(
                ['github_username' => $profile->username],
                [
                    'access_token_encrypted' => $result->accessToken,
                    'github_email' => $profile->email,
                    'github_name' => $profile->name,
                    'has_copilot' => $hasCopilot,
                ],
            );

            $githubService->configureGitIdentity(
                $profile->name ?? $profile->username,
                $profile->email ?? "{$profile->username}@users.noreply.github.com",
            );

            $this->status = 'connected';
            $this->githubUsername = $profile->username;
            $this->githubName = $profile->name ?? '';
            $this->githubEmail = $profile->email ?? '';
            $this->hasCopilot = $hasCopilot;
        } catch (\Exception $e) {
            $this->error = 'Authentication error: '.$e->getMessage();
            $this->status = 'idle';
        }
    }

    public function complete(WizardProgressService $progressService): void
    {
        $progressService->completeStep(WizardStep::GitHub, [
            'username' => $this->githubUsername,
            'has_copilot' => $this->hasCopilot,
        ]);

        $this->dispatch('step-completed');
    }

    public function skip(WizardProgressService $progressService): void
    {
        $progressService->skipStep(WizardStep::GitHub);
        $this->dispatch('step-skipped');
    }

    public function render()
    {
        return view('livewire.wizard.github');
    }
}
