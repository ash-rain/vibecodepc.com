<?php

declare(strict_types=1);

use App\Livewire\Wizard\AiServices;
use App\Models\AiProviderConfig;
use App\Services\WizardProgressService;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use VibecodePC\Common\Enums\AiProvider;
use VibecodePC\Common\Enums\WizardStep;

beforeEach(function () {
    app(WizardProgressService::class)->seedProgress();
});

it('renders the ai services step', function () {
    Livewire::test(AiServices::class)
        ->assertStatus(200)
        ->assertSee('AI Services');
});

it('loads providers from the enum', function () {
    Livewire::test(AiServices::class)
        ->assertSee('OpenAI')
        ->assertSee('Anthropic')
        ->assertSee('OpenRouter')
        ->assertSee('Hugging Face')
        ->assertSee('Custom Provider');
});

it('tests a valid api key connection', function () {
    Http::fake([
        'api.openai.com/v1/models' => Http::response(['data' => [['id' => 'gpt-4']]], 200),
    ]);

    Livewire::test(AiServices::class)
        ->set('apiKeys.openai', 'sk-test-key')
        ->call('testConnection', 'openai')
        ->assertSet('statuses.openai', 'valid');
});

it('tests an invalid api key connection', function () {
    Http::fake([
        'api.openai.com/v1/models' => Http::response([], 401),
    ]);

    Livewire::test(AiServices::class)
        ->set('apiKeys.openai', 'sk-invalid')
        ->call('testConnection', 'openai')
        ->assertSet('statuses.openai', 'error');
});

it('saves a provider api key', function () {
    Livewire::test(AiServices::class)
        ->set('apiKeys.openai', 'sk-test-key')
        ->call('saveProvider', 'openai')
        ->assertSet('statuses.openai', 'saved');

    expect(AiProviderConfig::where('provider', 'openai')->exists())->toBeTrue();
});

it('removes a provider', function () {
    AiProviderConfig::factory()->forProvider(AiProvider::OpenAI)->create();

    Livewire::test(AiServices::class)
        ->call('removeProvider', 'openai')
        ->assertSet('statuses.openai', 'none');

    expect(AiProviderConfig::where('provider', 'openai')->exists())->toBeFalse();
});

it('completes the ai services step', function () {
    Livewire::test(AiServices::class)
        ->call('complete')
        ->assertDispatched('step-completed');

    expect(app(WizardProgressService::class)->isStepCompleted(WizardStep::AiServices))->toBeTrue();
});

it('skips the ai services step', function () {
    Livewire::test(AiServices::class)
        ->call('skip')
        ->assertDispatched('step-skipped');
});

it('shows error when testing empty api key', function () {
    Livewire::test(AiServices::class)
        ->set('apiKeys.openai', '')
        ->call('testConnection', 'openai')
        ->assertSet('statuses.openai', 'error');
});
