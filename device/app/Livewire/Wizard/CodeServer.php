<?php

declare(strict_types=1);

namespace App\Livewire\Wizard;

use App\Models\AiProviderConfig;
use App\Services\CodeServer\CodeServerService;
use App\Services\WizardProgressService;
use Livewire\Component;
use VibecodePC\Common\Enums\AiProvider;
use VibecodePC\Common\Enums\WizardStep;

class CodeServer extends Component
{
    public bool $isInstalled = false;

    public bool $isRunning = false;

    public ?string $version = null;

    public string $selectedTheme = 'Default Dark+';

    public bool $extensionsInstalled = false;

    public bool $installingExtensions = false;

    public bool $clineConfigured = false;

    public string $codeServerUrl = '';

    public string $message = '';

    public int $previewKey = 0;

    /** @var array<int, array{id: string, label: string, extension: string|null}> */
    public array $themes = [
        ['id' => 'Default Dark+', 'label' => 'Default Dark+', 'extension' => null],
        ['id' => 'One Dark Pro', 'label' => 'One Dark Pro', 'extension' => 'zhuangtongfa.material-theme'],
        ['id' => 'GitHub Dark', 'label' => 'GitHub Dark', 'extension' => 'github.github-vscode-theme'],
        ['id' => 'Dracula', 'label' => 'Dracula', 'extension' => 'dracula-theme.theme-dracula'],
    ];

    public function mount(CodeServerService $codeServer): void
    {
        $this->isInstalled = $codeServer->isInstalled();
        $this->isRunning = $codeServer->isRunning();
        $this->version = $codeServer->getVersion();
        $this->codeServerUrl = $codeServer->getUrl();
    }

    public function startCodeServer(CodeServerService $codeServer): void
    {
        $this->message = '';

        $error = $codeServer->start();

        if ($error !== null) {
            $this->message = $error;

            return;
        }

        $this->isRunning = true;
        $this->codeServerUrl = $codeServer->getUrl();
        $this->message = 'code-server started successfully.';
    }

    public function stopCodeServer(CodeServerService $codeServer): void
    {
        $this->message = '';

        $error = $codeServer->stop();

        if ($error !== null) {
            $this->message = $error;

            return;
        }

        $this->isRunning = false;
        $this->message = 'code-server stopped.';
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
            'saoudrizwan.claude-dev',
        ];

        $failed = $codeServer->installExtensions($extensions);

        $this->installingExtensions = false;
        $this->extensionsInstalled = empty($failed);
        $this->message = empty($failed)
            ? 'Extensions installed successfully.'
            : 'Failed to install: '.implode(', ', $failed);

        if ($this->extensionsInstalled) {
            $this->configureCline($codeServer);
            $this->previewKey++;
        }
    }

    public function applyTheme(CodeServerService $codeServer): void
    {
        $theme = collect($this->themes)->firstWhere('id', $this->selectedTheme);

        if ($theme && $theme['extension']) {
            $failed = $codeServer->installExtensions([$theme['extension']]);

            if (! empty($failed)) {
                $this->message = "Failed to install theme extension: {$theme['extension']}";

                return;
            }
        }

        $codeServer->setTheme($this->selectedTheme);
        $this->message = "Theme set to {$this->selectedTheme}.";
        $this->previewKey++;
    }

    public function complete(WizardProgressService $progressService): void
    {
        $progressService->completeStep(WizardStep::CodeServer, [
            'theme' => $this->selectedTheme,
            'extensions_installed' => $this->extensionsInstalled,
            'cline_configured' => $this->clineConfigured,
        ]);

        $this->dispatch('step-completed');
    }

    private function configureCline(CodeServerService $codeServer): void
    {
        $priority = [
            AiProvider::Anthropic,
            AiProvider::OpenRouter,
            AiProvider::OpenAI,
            AiProvider::Custom,
        ];

        $provider = AiProviderConfig::query()
            ->where('status', 'validated')
            ->whereIn('provider', array_map(fn (AiProvider $p) => $p->value, $priority))
            ->get()
            ->sortBy(fn (AiProviderConfig $c) => array_search($c->provider, $priority))
            ->first();

        if (! $provider) {
            return;
        }

        $this->clineConfigured = $codeServer->configureCline(
            provider: $provider->provider,
            apiKey: $provider->getDecryptedKey(),
            baseUrl: $provider->base_url,
        );
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
