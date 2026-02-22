<?php

declare(strict_types=1);

namespace App\Livewire\Wizard;

use App\Models\CloudCredential;
use App\Models\TunnelConfig;
use App\Services\CloudApiClient;
use App\Services\DeviceRegistry\DeviceIdentityService;
use App\Services\Tunnel\TunnelService;
use App\Services\WizardProgressService;
use Livewire\Component;
use VibecodePC\Common\Enums\WizardStep;

class Tunnel extends Component
{
    public string $subdomain = '';

    public string $status = 'idle';

    public string $message = '';

    public bool $subdomainAvailable = false;

    public bool $tunnelActive = false;

    public bool $connectivityVerified = false;

    public function mount(): void
    {
        $credential = CloudCredential::current();
        $this->subdomain = $credential?->cloud_username ?? '';

        $existing = TunnelConfig::current();

        if ($existing) {
            $this->subdomain = $existing->subdomain;
            $this->tunnelActive = $existing->status === 'active';
            $this->connectivityVerified = $existing->verified_at !== null;

            if ($this->tunnelActive) {
                $this->status = 'active';
            }
        }
    }

    public function checkAvailability(CloudApiClient $cloudApi): void
    {
        $this->validate([
            'subdomain' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z][a-z0-9-]*[a-z0-9]$/'],
        ], [
            'subdomain.regex' => 'Subdomain must start with a letter, use lowercase alphanumeric and hyphens only.',
        ]);

        try {
            $result = $cloudApi->checkSubdomainAvailability($this->subdomain);
            $this->subdomainAvailable = $result;
            $this->message = $result
                ? "{$this->subdomain}." . config('vibecodepc.cloud_domain') . ' is available!'
                : 'This subdomain is taken. Try another.';
        } catch (\Exception $e) {
            $this->message = 'Could not check availability: '.$e->getMessage();
            $this->subdomainAvailable = false;
        }
    }

    public function setupTunnel(
        CloudApiClient $cloudApi,
        DeviceIdentityService $identity,
        TunnelService $tunnelService,
    ): void {
        if (! $this->subdomainAvailable) {
            return;
        }

        $this->status = 'provisioning';
        $this->message = 'Provisioning tunnel with cloud...';

        try {
            $deviceId = $identity->getDeviceInfo()->id;
            $result = $cloudApi->provisionTunnel($deviceId, $this->subdomain);
        } catch (\Exception $e) {
            $this->status = 'error';
            $this->message = 'Failed to provision tunnel: '.$e->getMessage();

            return;
        }

        $this->status = 'configuring';
        $this->message = 'Starting tunnel...';

        TunnelConfig::updateOrCreate(
            ['subdomain' => $this->subdomain],
            [
                'tunnel_id' => $result['tunnel_id'],
                'tunnel_token_encrypted' => $result['tunnel_token'],
                'status' => 'active',
            ],
        );

        $startError = $tunnelService->start();

        if ($startError !== null) {
            $this->status = 'error';
            $this->message = 'Tunnel provisioned but failed to start: '.$startError;

            return;
        }

        $this->status = 'active';
        $this->tunnelActive = true;
        $this->message = 'Tunnel is active at https://' . $this->subdomain . '.' . config('vibecodepc.cloud_domain');
    }

    public function testConnectivity(TunnelService $tunnelService): void
    {
        $verified = $tunnelService->testConnectivity($this->subdomain);

        $this->connectivityVerified = $verified;
        $this->message = $verified
            ? 'Public URL is reachable.'
            : 'Could not reach the public URL. The tunnel may still be initializing.';

        if ($verified) {
            TunnelConfig::where('subdomain', $this->subdomain)
                ->update(['verified_at' => now()]);
        }
    }

    public function complete(WizardProgressService $progressService): void
    {
        $progressService->completeStep(WizardStep::Tunnel, [
            'subdomain' => $this->subdomain,
            'tunnel_active' => $this->tunnelActive,
        ]);

        $this->dispatch('step-completed');
    }

    public function skip(WizardProgressService $progressService): void
    {
        $progressService->skipStep(WizardStep::Tunnel);
        $this->dispatch('step-skipped');
    }

    public function render()
    {
        return view('livewire.wizard.tunnel');
    }
}
