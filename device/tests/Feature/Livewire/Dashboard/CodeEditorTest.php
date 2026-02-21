<?php

declare(strict_types=1);

use App\Livewire\Dashboard\CodeEditor;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;

beforeEach(function () {
    Process::fake([
        'systemctl is-active code-server@vibecodepc' => Process::result(output: 'active'),
        'code-server --version' => Process::result(output: '4.23.1'),
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

    Process::assertRan('sudo systemctl restart code-server@vibecodepc');
});
