<?php

declare(strict_types=1);

namespace App\Livewire\Wizard;

use App\Models\AiProviderConfig;
use App\Services\AiProviders\AiProviderResolverService;
use App\Services\WizardProgressService;
use Livewire\Component;
use VibecodePC\Common\Enums\AiProvider;
use VibecodePC\Common\Enums\WizardStep;

class AiServices extends Component
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

    public function complete(WizardProgressService $progressService): void
    {
        $configured = AiProviderConfig::count();

        $progressService->completeStep(WizardStep::AiServices, [
            'providers_configured' => $configured,
        ]);

        $this->dispatch('step-completed');
    }

    public function skip(WizardProgressService $progressService): void
    {
        $progressService->skipStep(WizardStep::AiServices);
        $this->dispatch('step-skipped');
    }

    public function render()
    {
        return view('livewire.wizard.ai-services');
    }
}
