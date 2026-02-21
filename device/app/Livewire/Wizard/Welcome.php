<?php

declare(strict_types=1);

namespace App\Livewire\Wizard;

use App\Models\CloudCredential;
use App\Services\SystemService;
use App\Services\WizardProgressService;
use Livewire\Component;
use VibecodePC\Common\Enums\WizardStep;

class Welcome extends Component
{
    public string $cloudUsername = '';

    public string $cloudEmail = '';

    public string $adminPassword = '';

    public string $adminPasswordConfirmation = '';

    public string $timezone = '';

    public bool $acceptedTos = false;

    /** @var array<int, string> */
    public array $timezones = [];

    public function mount(SystemService $systemService): void
    {
        $credential = CloudCredential::current();

        if ($credential) {
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
        $systemService->setTimezone($this->timezone);

        $progressService->completeStep(WizardStep::Welcome, [
            'timezone' => $this->timezone,
            'username' => $this->cloudUsername,
        ]);

        $this->dispatch('step-completed');
    }

    public function render()
    {
        return view('livewire.wizard.welcome');
    }
}
