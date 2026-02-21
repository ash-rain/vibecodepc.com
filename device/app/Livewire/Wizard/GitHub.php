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
            $tokenResult = $githubService->pollForToken($this->deviceCode);

            if (! $tokenResult) {
                return;
            }

            $profile = $githubService->getUserProfile($tokenResult->accessToken);
            $hasCopilot = $githubService->checkCopilotAccess($tokenResult->accessToken);

            GitHubCredential::updateOrCreate(
                ['github_username' => $profile->username],
                [
                    'access_token_encrypted' => $tokenResult->accessToken,
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
