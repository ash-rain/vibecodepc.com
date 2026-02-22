<?php

declare(strict_types=1);

use App\Livewire\Wizard\CodeServer;
use App\Services\CodeServer\CodeServerService;
use App\Services\WizardProgressService;
use Livewire\Livewire;
use VibecodePC\Common\Enums\WizardStep;

beforeEach(function () {
    app(WizardProgressService::class)->seedProgress();

    $mock = Mockery::mock(CodeServerService::class);
    $mock->shouldReceive('isInstalled')->andReturn(true);
    $mock->shouldReceive('isRunning')->andReturn(true);
    $mock->shouldReceive('getVersion')->andReturn('4.96.4 6e0c2e65c6d6806b04e7684a7a1eab74e1ad940c with Code 1.96.4');
    $mock->shouldReceive('getUrl')->andReturn('http://localhost:8443');
    $mock->shouldReceive('installExtensions')->andReturn([]);
    $mock->shouldReceive('setTheme')->andReturn(true);
    app()->instance(CodeServerService::class, $mock);
});

it('renders the code server step', function () {
    Livewire::test(CodeServer::class)
        ->assertStatus(200)
        ->assertSee('VS Code Setup');
});

it('shows installation status', function () {
    Livewire::test(CodeServer::class)
        ->assertSet('isInstalled', true)
        ->assertSet('isRunning', true)
        ->assertSet('version', '4.96.4 6e0c2e65c6d6806b04e7684a7a1eab74e1ad940c with Code 1.96.4');
});

it('displays only the short version number as visible text', function () {
    Livewire::test(CodeServer::class)
        ->assertSee('4.96.4')
        ->assertSeeHtml('title="4.96.4 6e0c2e65c6d6806b04e7684a7a1eab74e1ad940c with Code 1.96.4"')
        ->assertSeeHtml('>4.96.4</dd>');
});

it('installs extensions', function () {
    Livewire::test(CodeServer::class)
        ->call('installExtensions')
        ->assertSet('extensionsInstalled', true);
});

it('applies a built-in theme without installing extensions', function () {
    Livewire::test(CodeServer::class)
        ->set('selectedTheme', 'Default Dark+')
        ->call('applyTheme')
        ->assertSet('message', 'Theme set to Default Dark+.');
});

it('installs theme extension before applying a third-party theme', function () {
    Livewire::test(CodeServer::class)
        ->set('selectedTheme', 'Dracula')
        ->call('applyTheme')
        ->assertSet('message', 'Theme set to Dracula.')
        ->assertSet('previewKey', 1);
});

it('shows error when theme extension install fails', function () {
    $mock = Mockery::mock(CodeServerService::class);
    $mock->shouldReceive('isInstalled')->andReturn(true);
    $mock->shouldReceive('isRunning')->andReturn(true);
    $mock->shouldReceive('getVersion')->andReturn('4.96.4');
    $mock->shouldReceive('getUrl')->andReturn('http://localhost:8443');
    $mock->shouldReceive('installExtensions')
        ->with(['dracula-theme.theme-dracula'])
        ->andReturn(['dracula-theme.theme-dracula']);
    $mock->shouldNotReceive('setTheme');
    app()->instance(CodeServerService::class, $mock);

    Livewire::test(CodeServer::class)
        ->set('selectedTheme', 'Dracula')
        ->call('applyTheme')
        ->assertSet('message', 'Failed to install theme extension: dracula-theme.theme-dracula')
        ->assertSet('previewKey', 0);
});

it('completes the code server step', function () {
    Livewire::test(CodeServer::class)
        ->call('complete')
        ->assertDispatched('step-completed');

    expect(app(WizardProgressService::class)->isStepCompleted(WizardStep::CodeServer))->toBeTrue();
});

it('skips the code server step', function () {
    Livewire::test(CodeServer::class)
        ->call('skip')
        ->assertDispatched('step-skipped');
});

it('shows start button when installed but not running', function () {
    $mock = Mockery::mock(CodeServerService::class);
    $mock->shouldReceive('isInstalled')->andReturn(true);
    $mock->shouldReceive('isRunning')->andReturn(false);
    $mock->shouldReceive('getVersion')->andReturn('4.96.4');
    $mock->shouldReceive('getUrl')->andReturn('http://localhost:8443');
    app()->instance(CodeServerService::class, $mock);

    Livewire::test(CodeServer::class)
        ->assertSet('isRunning', false)
        ->assertSee('Inactive')
        ->assertSee('Start')
        ->assertDontSee('Stop');
});

it('shows stop button when running', function () {
    Livewire::test(CodeServer::class)
        ->assertSet('isRunning', true)
        ->assertSee('Active')
        ->assertSee('Stop')
        ->assertDontSee('Start');
});

it('starts code-server successfully', function () {
    $mock = Mockery::mock(CodeServerService::class);
    $mock->shouldReceive('isInstalled')->andReturn(true);
    $mock->shouldReceive('isRunning')->andReturn(false);
    $mock->shouldReceive('getVersion')->andReturn('4.96.4');
    $mock->shouldReceive('getUrl')->andReturn('http://localhost:8443');
    $mock->shouldReceive('start')->once()->andReturn(null);
    app()->instance(CodeServerService::class, $mock);

    Livewire::test(CodeServer::class)
        ->assertSet('isRunning', false)
        ->call('startCodeServer')
        ->assertSet('isRunning', true)
        ->assertSet('message', 'code-server started successfully.');
});

it('shows error when start fails', function () {
    $mock = Mockery::mock(CodeServerService::class);
    $mock->shouldReceive('isInstalled')->andReturn(true);
    $mock->shouldReceive('isRunning')->andReturn(false);
    $mock->shouldReceive('getVersion')->andReturn('4.96.4');
    $mock->shouldReceive('getUrl')->andReturn('http://localhost:8443');
    $mock->shouldReceive('start')->once()->andReturn('Failed to start code-server.');
    app()->instance(CodeServerService::class, $mock);

    Livewire::test(CodeServer::class)
        ->call('startCodeServer')
        ->assertSet('isRunning', false)
        ->assertSet('message', 'Failed to start code-server.');
});

it('stops code-server successfully', function () {
    $mock = Mockery::mock(CodeServerService::class);
    $mock->shouldReceive('isInstalled')->andReturn(true);
    $mock->shouldReceive('isRunning')->andReturn(true);
    $mock->shouldReceive('getVersion')->andReturn('4.96.4');
    $mock->shouldReceive('getUrl')->andReturn('http://localhost:8443');
    $mock->shouldReceive('stop')->once()->andReturn(null);
    $mock->shouldReceive('installExtensions')->andReturn([]);
    $mock->shouldReceive('setTheme')->andReturn(true);
    app()->instance(CodeServerService::class, $mock);

    Livewire::test(CodeServer::class)
        ->assertSet('isRunning', true)
        ->call('stopCodeServer')
        ->assertSet('isRunning', false)
        ->assertSet('message', 'code-server stopped.');
});
