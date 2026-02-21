<?php

declare(strict_types=1);

namespace App\Livewire\Wizard;

use App\Models\CloudCredential;
use App\Models\TunnelConfig;
use App\Services\CloudApiClient;
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
            'subdomain' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9][a-z0-9-]*[a-z0-9]$/'],
        ], [
            'subdomain.regex' => 'Subdomain must be lowercase alphanumeric with optional hyphens.',
        ]);

        try {
            $result = $cloudApi->checkSubdomainAvailability($this->subdomain);
            $this->subdomainAvailable = $result;
            $this->message = $result
                ? "{$this->subdomain}.vibecodepc.com is available!"
                : 'This subdomain is taken. Try another.';
        } catch (\Exception $e) {
            $this->message = 'Could not check availability: '.$e->getMessage();
            $this->subdomainAvailable = false;
        }
    }

    public function setupTunnel(TunnelService $tunnelService): void
    {
        if (! $this->subdomainAvailable) {
            return;
        }

        $this->status = 'configuring';
        $this->message = '';

        $success = $tunnelService->createTunnel($this->subdomain, '');

        if (! $success) {
            $this->status = 'error';
            $this->message = 'Failed to create tunnel configuration.';

            return;
        }

        $started = $tunnelService->start();

        if (! $started) {
            $this->status = 'error';
            $this->message = 'Tunnel configured but failed to start.';

            return;
        }

        TunnelConfig::updateOrCreate(
            ['subdomain' => $this->subdomain],
            [
                'status' => 'active',
            ],
        );

        $this->status = 'active';
        $this->tunnelActive = true;
        $this->message = "Tunnel is active at https://{$this->subdomain}.vibecodepc.com";
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
