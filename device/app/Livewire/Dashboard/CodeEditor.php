<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\GitHubCredential;
use App\Services\CodeServer\CodeServerService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.dashboard', ['title' => 'Code Editor'])]
#[Title('Code Editor — VibeCodePC')]
class CodeEditor extends Component
{
    public bool $isRunning = false;

    public ?string $version = null;

    public string $editorUrl = '';

    public bool $hasCopilot = false;

    public function mount(CodeServerService $codeServerService): void
    {
        $this->isRunning = $codeServerService->isRunning();
        $this->version = $codeServerService->getVersion();
        $this->editorUrl = $codeServerService->getUrl();

        $github = GitHubCredential::current();
        $this->hasCopilot = $github?->hasCopilot() ?? false;
    }

    public function restart(CodeServerService $codeServerService): void
    {
        $codeServerService->restart();
        $this->isRunning = $codeServerService->isRunning();
    }

    public function render()
    {
        return view('livewire.dashboard.code-editor');
    }
}
