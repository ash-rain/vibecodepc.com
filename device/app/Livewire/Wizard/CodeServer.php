<?php

declare(strict_types=1);

namespace App\Livewire\Wizard;

use App\Services\CodeServer\CodeServerService;
use App\Services\WizardProgressService;
use Livewire\Component;
use VibecodePC\Common\Enums\WizardStep;

class CodeServer extends Component
{
    public bool $isInstalled = false;

    public bool $isRunning = false;

    public ?string $version = null;

    public string $selectedTheme = 'Default Dark+';

    public bool $extensionsInstalled = false;

    public bool $installingExtensions = false;

    public string $codeServerUrl = '';

    public string $message = '';

    /** @var array<int, array{id: string, label: string}> */
    public array $themes = [
        ['id' => 'Default Dark+', 'label' => 'Default Dark+'],
        ['id' => 'One Dark Pro', 'label' => 'One Dark Pro'],
        ['id' => 'GitHub Dark', 'label' => 'GitHub Dark'],
        ['id' => 'Dracula', 'label' => 'Dracula'],
    ];

    public function mount(CodeServerService $codeServer): void
    {
        $this->isInstalled = $codeServer->isInstalled();
        $this->isRunning = $codeServer->isRunning();
        $this->version = $codeServer->getVersion();
        $this->codeServerUrl = $codeServer->getUrl();
    }

    public function installExtensions(CodeServerService $codeServer): void
    {
        $this->installingExtensions = true;
        $this->message = '';

        $extensions = [
            'bradlc.vscode-tailwindcss',
            'dbaeumer.vscode-eslint',
            'esbenp.prettier-vscode',
            'continue.continue',
        ];

        $failed = $codeServer->installExtensions($extensions);

        $this->installingExtensions = false;
        $this->extensionsInstalled = empty($failed);
        $this->message = empty($failed)
            ? 'Extensions installed successfully.'
            : 'Failed to install: '.implode(', ', $failed);
    }

    public function applyTheme(CodeServerService $codeServer): void
    {
        $codeServer->setTheme($this->selectedTheme);
        $this->message = "Theme set to {$this->selectedTheme}.";
    }

    public function complete(WizardProgressService $progressService): void
    {
        $progressService->completeStep(WizardStep::CodeServer, [
            'theme' => $this->selectedTheme,
            'extensions_installed' => $this->extensionsInstalled,
        ]);

        $this->dispatch('step-completed');
    }

    public function skip(WizardProgressService $progressService): void
    {
        $progressService->skipStep(WizardStep::CodeServer);
        $this->dispatch('step-skipped');
    }

    public function render()
    {
        return view('livewire.wizard.code-server');
    }
}
