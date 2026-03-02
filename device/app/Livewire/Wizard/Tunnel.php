<?php

declare(strict_types=1);

namespace App\Livewire\Wizard;

use App\Models\CloudCredential;
use App\Models\TunnelConfig;
use App\Services\AnalyticsService;
use App\Services\CloudApiClient;
use App\Services\DeviceRegistry\DeviceIdentityService;
use App\Services\Tunnel\TunnelService;
use App\Services\WizardProgressService;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use VibecodePC\Common\Enums\WizardStep;

class Tunnel extends Component
{
    public bool $tunnelInstalled = false;

    public bool $tunnelRunning = false;

    public bool $tunnelConfigured = false;

    public ?string $subdomain = null;

    public string $error = '';

    public string $newSubdomain = '';

    public bool $subdomainAvailable = false;

    public string $provisionStatus = '';

    public bool $isProvisioning = false;

    public function mount(): void
    {
        $tunnelService = app(TunnelService::class);
        $status = $tunnelService->getStatus();
        $this->tunnelInstalled = $status['installed'];
        $this->tunnelRunning = $status['running'];
        $this->tunnelConfigured = $status['configured'];

        $tunnelConfig = TunnelConfig::current();
        $this->subdomain = $tunnelConfig?->subdomain;

        if (! $this->tunnelConfigured) {
            $username = CloudCredential::current()?->cloud_username;

            if ($username) {
                $this->newSubdomain = $username;
                $this->subdomainAvailable = true;
            }
        }
    }

    public function checkAvailability(CloudApiClient $cloudApi): void
    {
        $this->error = '';
        $this->subdomainAvailable = false;
        $this->provisionStatus = '';

        if (empty($this->newSubdomain)) {
            $this->error = 'Please enter a subdomain.';

            return;
        }

        if (! preg_match('/^[a-z][a-z0-9-]*[a-z0-9]$/', $this->newSubdomain)) {
            $this->error = 'Subdomain must start with a letter, use lowercase alphanumeric and hyphens only.';

            return;
        }

        try {
            $this->subdomainAvailable = $cloudApi->checkSubdomainAvailability($this->newSubdomain);
            $this->provisionStatus = $this->subdomainAvailable
                ? "{$this->newSubdomain}.".config('vibecodepc.cloud_domain').' is available!'
                : 'This subdomain is taken. Try another.';
        } catch (\Throwable $e) {
            $this->provisionStatus = 'Could not check availability. Is the device online?';
            Log::warning('Subdomain availability check failed', ['error' => $e->getMessage()]);
        }
    }

    public function provisionTunnel(
        CloudApiClient $cloudApi,
        DeviceIdentityService $identity,
        TunnelService $tunnelService,
    ): void {
        if (! $this->subdomainAvailable) {
            return;
        }

        $this->error = '';
        $this->isProvisioning = true;
        $this->provisionStatus = 'Provisioning tunnel...';

        try {
            $deviceId = $identity->getDeviceInfo()->id;
            $result = $cloudApi->provisionTunnel($deviceId, $this->newSubdomain);
        } catch (\Throwable $e) {
            $this->isProvisioning = false;
            $this->error = 'Failed to provision tunnel: '.$e->getMessage();
            $this->provisionStatus = '';

            return;
        }

        TunnelConfig::updateOrCreate(
            ['subdomain' => $this->newSubdomain],
            [
                'tunnel_id' => $result['tunnel_id'],
                'tunnel_token_encrypted' => $result['tunnel_token'],
                'status' => 'active',
            ],
        );

        $startError = $tunnelService->start();

        if ($startError !== null) {
            $tunnelService->cleanup();
            $this->isProvisioning = false;
            $this->error = 'Tunnel provisioned but failed to start: '.$startError;

            return;
        }

        $this->subdomain = $this->newSubdomain;
        $this->newSubdomain = '';
        $this->subdomainAvailable = false;
        $this->isProvisioning = false;
        $this->provisionStatus = '';
        $this->tunnelRunning = $tunnelService->isRunning();
        $this->tunnelConfigured = $tunnelService->hasCredentials();

        $this->complete(app(WizardProgressService::class));
    }

    public function complete(WizardProgressService $progressService): void
    {
        app(AnalyticsService::class)->trackTunnelEvent('completed', [
            'subdomain' => $this->subdomain,
        ]);

        $progressService->completeStep(WizardStep::Tunnel, [
            'subdomain' => $this->subdomain,
        ]);

        $this->dispatch('step-completed');
    }

    public function skip(WizardProgressService $progressService): void
    {
        app(AnalyticsService::class)->trackTunnelEvent('skipped', [
            'reason' => 'user_choice',
        ]);

        $progressService->skipStep(WizardStep::Tunnel);
        $this->dispatch('step-skipped');
    }

    public function render()
    {
        return view('livewire.wizard.tunnel');
    }
}
