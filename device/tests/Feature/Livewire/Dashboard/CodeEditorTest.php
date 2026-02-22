<?php

declare(strict_types=1);

use App\Livewire\Dashboard\CodeEditor;
use App\Services\CodeServer\CodeServerService;
use Livewire\Livewire;

it('renders the code editor page', function () {
    $mock = Mockery::mock(CodeServerService::class);
    $mock->shouldReceive('isInstalled')->andReturn(true);
    $mock->shouldReceive('isRunning')->andReturn(true);
    $mock->shouldReceive('getVersion')->andReturn('4.23.1');
    $mock->shouldReceive('getUrl')->andReturn('http://localhost:8443');
    app()->instance(CodeServerService::class, $mock);

    Livewire::test(CodeEditor::class)
        ->assertStatus(200)
        ->assertSee('Code Editor');
});

it('shows running status and version', function () {
    $mock = Mockery::mock(CodeServerService::class);
    $mock->shouldReceive('isInstalled')->andReturn(true);
    $mock->shouldReceive('isRunning')->andReturn(true);
    $mock->shouldReceive('getVersion')->andReturn('4.23.1');
    $mock->shouldReceive('getUrl')->andReturn('http://localhost:8443');
    app()->instance(CodeServerService::class, $mock);

    Livewire::test(CodeEditor::class)
        ->assertSee('Running')
        ->assertSee('4.23.1');
});

it('shows stopped status when not running', function () {
    $mock = Mockery::mock(CodeServerService::class);
    $mock->shouldReceive('isInstalled')->andReturn(true);
    $mock->shouldReceive('isRunning')->andReturn(false);
    $mock->shouldReceive('getVersion')->andReturn('4.23.1');
    $mock->shouldReceive('getUrl')->andReturn('http://localhost:8443');
    app()->instance(CodeServerService::class, $mock);

    Livewire::test(CodeEditor::class)
        ->assertSee('Stopped')
        ->assertSee('Start Editor');
});

it('shows not installed state', function () {
    $mock = Mockery::mock(CodeServerService::class);
    $mock->shouldReceive('isInstalled')->andReturn(false);
    $mock->shouldReceive('isRunning')->andReturn(false);
    $mock->shouldReceive('getVersion')->andReturn(null);
    $mock->shouldReceive('getUrl')->andReturn('http://localhost:8443');
    app()->instance(CodeServerService::class, $mock);

    Livewire::test(CodeEditor::class)
        ->assertSee('Not Installed')
        ->assertSee('code-server is not installed');
});

it('can start the editor', function () {
    $mock = Mockery::mock(CodeServerService::class);
    $mock->shouldReceive('isInstalled')->andReturn(true);
    $mock->shouldReceive('isRunning')->andReturn(false, true);
    $mock->shouldReceive('getVersion')->andReturn('4.23.1');
    $mock->shouldReceive('getUrl')->andReturn('http://localhost:8443');
    $mock->shouldReceive('start')->once()->andReturn(null);
    app()->instance(CodeServerService::class, $mock);

    Livewire::test(CodeEditor::class)
        ->assertSee('Stopped')
        ->call('start')
        ->assertSet('isRunning', true)
        ->assertSet('error', '');
});

it('shows error when start fails', function () {
    $mock = Mockery::mock(CodeServerService::class);
    $mock->shouldReceive('isInstalled')->andReturn(true);
    $mock->shouldReceive('isRunning')->andReturn(false, false);
    $mock->shouldReceive('getVersion')->andReturn('4.23.1');
    $mock->shouldReceive('getUrl')->andReturn('http://localhost:8443');
    $mock->shouldReceive('start')->once()->andReturn('code-server started but not responding on port 8443.');
    app()->instance(CodeServerService::class, $mock);

    Livewire::test(CodeEditor::class)
        ->call('start')
        ->assertSet('isRunning', false)
        ->assertSee('code-server started but not responding');
});

it('can restart the editor', function () {
    $mock = Mockery::mock(CodeServerService::class);
    $mock->shouldReceive('isInstalled')->andReturn(true);
    $mock->shouldReceive('isRunning')->andReturn(true, true);
    $mock->shouldReceive('getVersion')->andReturn('4.23.1');
    $mock->shouldReceive('getUrl')->andReturn('http://localhost:8443');
    $mock->shouldReceive('restart')->once()->andReturn(null);
    app()->instance(CodeServerService::class, $mock);

    Livewire::test(CodeEditor::class)
        ->call('restart')
        ->assertSet('isRunning', true)
        ->assertSet('error', '');
});
