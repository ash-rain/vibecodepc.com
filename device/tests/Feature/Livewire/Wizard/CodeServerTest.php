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
    $mock->shouldReceive('getVersion')->andReturn('4.96.4');
    $mock->shouldReceive('getUrl')->andReturn('http://localhost:8443');
    $mock->shouldReceive('installExtensions')->andReturn(true);
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
        ->assertSet('version', '4.96.4');
});

it('installs extensions', function () {
    Livewire::test(CodeServer::class)
        ->call('installExtensions')
        ->assertSet('extensionsInstalled', true);
});

it('applies a theme', function () {
    Livewire::test(CodeServer::class)
        ->set('selectedTheme', 'Dracula')
        ->call('applyTheme')
        ->assertSet('message', 'Theme set to Dracula.');
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
