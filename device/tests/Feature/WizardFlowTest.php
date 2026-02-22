<?php

declare(strict_types=1);

use App\Livewire\Wizard\AiServices;
use App\Livewire\Wizard\CodeServer;
use App\Livewire\Wizard\Complete;
use App\Livewire\Wizard\GitHub;
use App\Livewire\Wizard\Tunnel;
use App\Livewire\Wizard\Welcome;
use App\Livewire\Wizard\WizardController;
use App\Models\CloudCredential;
use App\Models\DeviceState;
use App\Services\CodeServer\CodeServerService;
use App\Services\DeviceStateService;
use App\Services\SystemService;
use App\Services\WizardProgressService;
use Livewire\Livewire;
use VibecodePC\Common\Enums\WizardStep;

beforeEach(function () {
    // Simulate a paired device
    CloudCredential::create([
        'pairing_token_encrypted' => 'test-token',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => true,
        'paired_at' => now(),
    ]);

    DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_WIZARD);

    // Mock system services
    $systemMock = Mockery::mock(SystemService::class);
    $systemMock->shouldReceive('getCurrentTimezone')->andReturn('UTC');
    $systemMock->shouldReceive('getAvailableTimezones')->andReturn(['UTC', 'America/New_York']);
    $systemMock->shouldReceive('setAdminPassword')->andReturn(true);
    $systemMock->shouldReceive('setTimezone')->andReturn(true);
    app()->instance(SystemService::class, $systemMock);

    $codeServerMock = Mockery::mock(CodeServerService::class);
    $codeServerMock->shouldReceive('isInstalled')->andReturn(true);
    $codeServerMock->shouldReceive('isRunning')->andReturn(false);
    $codeServerMock->shouldReceive('getVersion')->andReturn('4.96.4');
    $codeServerMock->shouldReceive('getUrl')->andReturn('http://localhost:8443');
    app()->instance(CodeServerService::class, $codeServerMock);
});

it('redirects home to wizard when in wizard mode', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('wizard'));
});

it('renders wizard starting at welcome step', function () {
    Livewire::test(WizardController::class)
        ->assertSet('currentStep', 'welcome')
        ->assertSee('VibeCodePC Setup');
});

it('completes welcome and advances to tunnel', function () {
    Livewire::test(Welcome::class)
        ->set('adminPassword', 'securepassword')
        ->set('adminPasswordConfirmation', 'securepassword')
        ->set('timezone', 'UTC')
        ->set('acceptedTos', true)
        ->call('complete')
        ->assertDispatched('step-completed');

    $service = app(WizardProgressService::class);

    expect($service->isStepCompleted(WizardStep::Welcome))->toBeTrue()
        ->and($service->getCurrentStep())->toBe(WizardStep::Tunnel);
});

it('completes full wizard flow with skips and transitions to dashboard', function () {
    $service = app(WizardProgressService::class);

    // Complete welcome
    Livewire::test(Welcome::class)
        ->set('adminPassword', 'securepassword')
        ->set('adminPasswordConfirmation', 'securepassword')
        ->set('timezone', 'UTC')
        ->set('acceptedTos', true)
        ->call('complete');

    // Skip remaining steps
    Livewire::test(Tunnel::class)->call('skip');
    Livewire::test(AiServices::class)->call('skip');
    Livewire::test(GitHub::class)->call('skip');
    Livewire::test(CodeServer::class)->call('skip');

    expect($service->getCurrentStep())->toBe(WizardStep::Complete);

    // Complete wizard
    Livewire::test(Complete::class)
        ->assertSee('Setup Complete')
        ->call('goToDashboard')
        ->assertRedirect(route('home'));

    // Verify mode transition
    expect(DeviceState::getValue(DeviceStateService::MODE_KEY))->toBe(DeviceStateService::MODE_DASHBOARD);
});
