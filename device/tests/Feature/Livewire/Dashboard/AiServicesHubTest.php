<?php

declare(strict_types=1);

use App\Livewire\Dashboard\AiServicesHub;
use App\Models\AiProviderConfig;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use VibecodePC\Common\Enums\AiProvider;

it('renders the ai services hub', function () {
    Livewire::test(AiServicesHub::class)
        ->assertStatus(200)
        ->assertSee('AI Services');
});

it('loads all providers', function () {
    Livewire::test(AiServicesHub::class)
        ->assertSee('OpenAI')
        ->assertSee('Anthropic')
        ->assertSee('OpenRouter');
});

it('tests a valid api key', function () {
    Http::fake([
        'api.openai.com/v1/models' => Http::response(['data' => [['id' => 'gpt-4']]], 200),
    ]);

    Livewire::test(AiServicesHub::class)
        ->set('apiKeys.openai', 'sk-test')
        ->call('testConnection', 'openai')
        ->assertSet('statuses.openai', 'valid');
});

it('saves a provider api key', function () {
    Livewire::test(AiServicesHub::class)
        ->set('apiKeys.openai', 'sk-test')
        ->call('saveProvider', 'openai')
        ->assertSet('statuses.openai', 'saved');

    expect(AiProviderConfig::where('provider', 'openai')->exists())->toBeTrue();
});

it('removes a provider', function () {
    AiProviderConfig::factory()->forProvider(AiProvider::OpenAI)->create();

    Livewire::test(AiServicesHub::class)
        ->call('removeProvider', 'openai')
        ->assertSet('statuses.openai', 'none');

    expect(AiProviderConfig::where('provider', 'openai')->exists())->toBeFalse();
});
