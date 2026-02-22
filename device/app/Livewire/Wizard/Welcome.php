<?php

declare(strict_types=1);

namespace App\Livewire\Wizard;

use App\Models\CloudCredential;
use App\Models\DeviceState;
use App\Models\TunnelConfig;
use App\Services\DeviceStateService;
use App\Services\SystemService;
use App\Services\Tunnel\TunnelService;
use App\Services\WizardProgressService;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use VibecodePC\Common\Enums\WizardStep;

class Welcome extends Component
{
    public bool $showUnpairConfirm = false;

    public string $cloudUsername = '';

    public string $cloudEmail = '';

    public bool $isPaired = false;

    public string $adminPassword = '';

    public string $adminPasswordConfirmation = '';

    public string $timezone = '';

    public bool $acceptedTos = false;

    /** @var array<int, string> */
    public array $timezones = [];

    public function mount(SystemService $systemService): void
    {
        $credential = CloudCredential::current();

        if ($credential && $credential->isPaired()) {
            $this->isPaired = true;
            $this->cloudUsername = $credential->cloud_username ?? '';
            $this->cloudEmail = $credential->cloud_email ?? '';
        }

        $this->timezone = $systemService->getCurrentTimezone();
        $this->timezones = $systemService->getAvailableTimezones();
    }

    public function complete(SystemService $systemService, WizardProgressService $progressService): void
    {
        $this->validate([
            'adminPassword' => ['required', 'string', 'min:8', 'same:adminPasswordConfirmation'],
            'timezone' => ['required', 'string'],
            'acceptedTos' => ['accepted'],
        ], [
            'adminPassword.same' => 'Passwords do not match.',
            'acceptedTos.accepted' => 'You must accept the terms of service.',
        ]);

        $systemService->setAdminPassword($this->adminPassword);
        DeviceState::setValue('admin_password_hash', Hash::make($this->adminPassword));
        $systemService->setTimezone($this->timezone);

        $progressService->completeStep(WizardStep::Welcome, [
            'timezone' => $this->timezone,
            'username' => $this->cloudUsername,
        ]);

        $this->dispatch('step-completed');
    }

    public function confirmUnpair(): void
    {
        $this->showUnpairConfirm = true;
    }

    public function cancelUnpair(): void
    {
        $this->showUnpairConfirm = false;
    }

    public function unpair(TunnelService $tunnelService): void
    {
        // Stop the tunnel if running
        $tunnelService->stop();

        // Clean up all pairing and tunnel data
        TunnelConfig::query()->delete();
        CloudCredential::query()->delete();
        DeviceState::where('key', DeviceStateService::MODE_KEY)->delete();

        // Reset wizard progress so tunnel step can be re-done
        app(WizardProgressService::class)->resetWizard();

        session()->flush();

        $this->redirect('/');
    }

    public function render()
    {
        return view('livewire.wizard.welcome');
    }
}
