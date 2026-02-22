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

    public function mount(CodeServerService $codeServerService): void
    {
        $this->folder = request()->query('folder');
        $this->isInstalled = $codeServerService->isInstalled();
        $this->isRunning = $codeServerService->isRunning();
        $this->version = $codeServerService->getVersion();

        $github = GitHubCredential::current();
        $this->hasCopilot = $github?->hasCopilot() ?? false;
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
