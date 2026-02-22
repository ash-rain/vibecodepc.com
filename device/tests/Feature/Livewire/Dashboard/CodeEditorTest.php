<?php

declare(strict_types=1);

use App\Livewire\Dashboard\CodeEditor;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;

beforeEach(function () {
    Process::fake([
        'code-server --version*' => Process::result(output: '4.23.1'),
        'lsof*' => Process::result(output: '12345'),
        'ss*' => Process::result(output: 'LISTEN'),
        '*' => Process::result(),
    ]);
});

it('renders the code editor page', function () {
    Livewire::test(CodeEditor::class)
        ->assertStatus(200)
        ->assertSee('Code Editor');
});

it('shows running status', function () {
    Livewire::test(CodeEditor::class)
        ->assertSee('Running')
        ->assertSee('4.23.1');
});

it('can restart the editor', function () {
    Livewire::test(CodeEditor::class)
        ->call('restart');

    Process::assertRan(fn ($process) => str_contains($process->command, 'restart') && str_contains($process->command, 'code-server'));
});
