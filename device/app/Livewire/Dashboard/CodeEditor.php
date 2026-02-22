<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\GitHubCredential;
use App\Services\CodeServer\CodeServerService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.editor')]
#[Title('Code Editor — VibeCodePC')]
class CodeEditor extends Component
{
    public bool $isInstalled = false;

    public bool $isRunning = false;

    public ?string $version = null;

    public bool $hasCopilot = false;

    public string $error = '';

    public ?string $folder = null;

    public int $iframeKey = 0;

    /** @var array<int, array{id: string, version: string}> */
    public array $extensions = [];

    public string $newExtensionId = '';

    public string $extensionMessage = '';

    public function mount(CodeServerService $codeServerService): void
    {
        $this->folder = request()->query('folder');
        $this->isInstalled = $codeServerService->isInstalled();
        $this->isRunning = $codeServerService->isRunning();
        $this->version = $codeServerService->getVersion();

        $github = GitHubCredential::current();
        $this->hasCopilot = $github?->hasCopilot() ?? false;

        $this->loadExtensions($codeServerService);
    }

    public function loadExtensions(?CodeServerService $codeServerService = null): void
    {
        $codeServerService ??= app(CodeServerService::class);
        $this->extensions = $codeServerService->listExtensions();
    }

    public function installExtension(CodeServerService $codeServerService): void
    {
        $this->extensionMessage = '';

        $id = trim($this->newExtensionId);

        if ($id === '') {
            $this->extensionMessage = 'Please enter an extension ID.';

            return;
        }

        $failed = $codeServerService->installExtensions([$id]);

        if (empty($failed)) {
            $this->extensionMessage = "Extension {$id} installed successfully.";
            $this->newExtensionId = '';
            $this->loadExtensions($codeServerService);
        } else {
            $this->extensionMessage = "Failed to install {$id}.";
        }
    }

    public function removeExtension(string $id, CodeServerService $codeServerService): void
    {
        $this->extensionMessage = '';

        if ($codeServerService->uninstallExtension($id)) {
            $this->extensionMessage = "Extension {$id} removed.";
            $this->loadExtensions($codeServerService);
        } else {
            $this->extensionMessage = "Failed to remove {$id}.";
        }
    }

    public function start(CodeServerService $codeServerService): void
    {
        $this->error = '';

        $error = $codeServerService->start();

        $this->isRunning = $codeServerService->isRunning();

        if ($error !== null) {
            $this->error = $error;
        }
    }

    public function restart(CodeServerService $codeServerService): void
    {
        $this->error = '';

        $error = $codeServerService->restart();

        $this->isRunning = $codeServerService->isRunning();

        if ($error !== null) {
            $this->error = $error;
        } else {
            $this->iframeKey++;
        }
    }

    public function render(CodeServerService $codeServerService)
    {
        $editorUrl = $codeServerService->getUrl();

        if ($this->folder) {
            $editorUrl .= '/?folder='.urlencode($this->folder);
        }

        return view('livewire.dashboard.code-editor', [
            'editorUrl' => $editorUrl,
        ]);
    }
}
