<?php

declare(strict_types=1);

use App\Livewire\Wizard\GitHub;
use App\Models\GitHubCredential;
use App\Services\WizardProgressService;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use VibecodePC\Common\Enums\WizardStep;

beforeEach(function () {
    app(WizardProgressService::class)->seedProgress();
});

it('renders the github step', function () {
    Livewire::test(GitHub::class)
        ->assertStatus(200)
        ->assertSee('Connect GitHub');
});

it('shows connected state when credential exists', function () {
    GitHubCredential::factory()->create([
        'github_username' => 'existinguser',
        'github_name' => 'Existing User',
    ]);

    Livewire::test(GitHub::class)
        ->assertSet('status', 'connected')
        ->assertSet('githubUsername', 'existinguser');
});

it('initiates device flow', function () {
    Http::fake([
        'github.com/login/device/code' => Http::response([
            'device_code' => 'device-123',
            'user_code' => 'ABCD-1234',
            'verification_uri' => 'https://github.com/login/device',
            'expires_in' => 900,
            'interval' => 5,
        ]),
    ]);

    Livewire::test(GitHub::class)
        ->call('startDeviceFlow')
        ->assertSet('status', 'polling')
        ->assertSet('userCode', 'ABCD-1234');
});

it('handles device flow initiation error', function () {
    Http::fake([
        'github.com/login/device/code' => Http::response([], 500),
    ]);

    Livewire::test(GitHub::class)
        ->call('startDeviceFlow')
        ->assertSet('error', fn ($value) => str_contains($value, 'Could not start'));
});

it('skips the github step', function () {
    Livewire::test(GitHub::class)
        ->call('skip')
        ->assertDispatched('step-skipped');
});

it('completes the github step when connected', function () {
    GitHubCredential::factory()->create([
        'github_username' => 'testuser',
        'has_copilot' => true,
    ]);

    Livewire::test(GitHub::class)
        ->call('complete')
        ->assertDispatched('step-completed');

    expect(app(WizardProgressService::class)->isStepCompleted(WizardStep::GitHub))->toBeTrue();
});
