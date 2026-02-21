<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\AiProviderConfig;
use App\Services\AiProviders\AiProviderResolverService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use VibecodePC\Common\Enums\AiProvider;

#[Layout('layouts.dashboard', ['title' => 'AI Services'])]
#[Title('AI Services — VibeCodePC')]
class AiServicesHub extends Component
{
    /** @var array<string, string> */
    public array $apiKeys = [];

    /** @var array<string, string> */
    public array $statuses = [];

    /** @var array<string, string> */
    public array $messages = [];

    /** @var array<int, array{key: string, name: string, desc: string, url: string}> */
    public array $providers = [];

    public string $customName = '';

    public string $customBaseUrl = '';

    public string $quickTestResult = '';

    public string $quickTestProvider = '';

    public function mount(): void
    {
        foreach (AiProvider::cases() as $provider) {
            $this->apiKeys[$provider->value] = '';
            $this->statuses[$provider->value] = 'none';
            $this->messages[$provider->value] = '';
            $this->providers[] = [
                'key' => $provider->value,
                'name' => $provider->label(),
                'desc' => $provider->description(),
                'url' => $provider->apiKeyUrl(),
            ];
        }

        $existing = AiProviderConfig::all();

        foreach ($existing as $config) {
            $this->statuses[$config->provider->value] = $config->isValidated() ? 'valid' : 'saved';
            $this->apiKeys[$config->provider->value] = '••••••••';

            if ($config->provider === AiProvider::Custom) {
                $this->customName = $config->display_name ?? '';
                $this->customBaseUrl = $config->base_url ?? '';
            }
        }
    }

    public function testConnection(string $provider): void
    {
        $aiProvider = AiProvider::from($provider);
        $key = $this->apiKeys[$provider];

        if (! $key || $key === '••••••••') {
            $this->messages[$provider] = 'Please enter an API key.';
            $this->statuses[$provider] = 'error';

            return;
        }

        $resolver = app(AiProviderResolverService::class);
        $baseUrl = $aiProvider === AiProvider::Custom ? $this->customBaseUrl : null;
        $validator = $resolver->resolve($aiProvider, $baseUrl);

        $result = $validator->validate($key);

        $this->statuses[$provider] = $result->valid ? 'valid' : 'error';
        $this->messages[$provider] = $result->message;
    }

    public function saveProvider(string $provider): void
    {
        $aiProvider = AiProvider::from($provider);
        $key = $this->apiKeys[$provider];

        if (! $key || $key === '••••••••') {
            return;
        }

        $data = [
            'api_key_encrypted' => $key,
            'status' => $this->statuses[$provider] === 'valid' ? 'validated' : 'pending',
            'validated_at' => $this->statuses[$provider] === 'valid' ? now() : null,
        ];

        if ($aiProvider === AiProvider::Custom) {
            $data['display_name'] = $this->customName ?: 'Custom';
            $data['base_url'] = $this->customBaseUrl;
        }

        AiProviderConfig::updateOrCreate(
            ['provider' => $aiProvider->value],
            $data,
        );

        $this->statuses[$provider] = 'saved';
        $this->messages[$provider] = 'API key saved.';
    }

    public function removeProvider(string $provider): void
    {
        AiProviderConfig::where('provider', $provider)->delete();

        $this->apiKeys[$provider] = '';
        $this->statuses[$provider] = 'none';
        $this->messages[$provider] = '';
    }

    public function quickTest(string $provider): void
    {
        $config = AiProviderConfig::where('provider', $provider)->first();

        if (! $config) {
            $this->quickTestResult = 'No API key configured for this provider.';

            return;
        }

        $resolver = app(AiProviderResolverService::class);
        $validator = $resolver->resolve($config->provider, $config->base_url);
        $result = $validator->validate($config->getDecryptedKey());

        $this->quickTestProvider = $config->provider->label();
        $this->quickTestResult = $result->valid
            ? 'Connection successful! Provider is responding.'
            : "Connection failed: {$result->message}";
    }

    public function render()
    {
        return view('livewire.dashboard.ai-services-hub');
    }
}
