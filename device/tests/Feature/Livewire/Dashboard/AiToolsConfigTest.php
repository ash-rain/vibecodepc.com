<?php

declare(strict_types=1);

use App\Livewire\Dashboard\AiToolsConfig;
use App\Services\AiToolConfigService;
use Livewire\Livewire;

beforeEach(function () {
    $this->tmpDir = sys_get_temp_dir().'/ai-tool-feature-test-'.uniqid();
    mkdir($this->tmpDir, 0755, true);
    $_SERVER['HOME'] = $this->tmpDir;
});

afterEach(function () {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->tmpDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($this->tmpDir);
});

it('renders the ai tools config page', function () {
    Livewire::test(AiToolsConfig::class)
        ->assertSuccessful()
        ->assertSee('AI Tools Config');
});

it('shows the environment tab by default', function () {
    Livewire::test(AiToolsConfig::class)
        ->assertSee('API Keys')
        ->assertSee('Gemini API Key')
        ->assertSee('Claude API Key');
});

it('masks existing api keys on mount', function () {
    $service = new AiToolConfigService;
    $service->setEnvVars([
        'GEMINI_API_KEY' => 'real-gemini-key',
        'CLAUDE_API_KEY' => 'real-claude-key',
        'OPENAI_API_KEY' => 'real-openai-key',
        'COHERE_API_KEY' => 'real-cohere-key',
    ]);

    Livewire::test(AiToolsConfig::class)
        ->assertSet('geminiApiKey', '••••••••')
        ->assertSet('claudeApiKey', '••••••••')
        ->assertSet('openaiApiKey', '••••••••')
        ->assertSet('cohereApiKey', '••••••••');
});

it('leaves api key fields empty when no key is configured', function () {
    Livewire::test(AiToolsConfig::class)
        ->assertSet('geminiApiKey', '')
        ->assertSet('claudeApiKey', '')
        ->assertSet('openaiApiKey', '')
        ->assertSet('cohereApiKey', '');
});

it('saves new environment variables', function () {
    Livewire::test(AiToolsConfig::class)
        ->set('geminiApiKey', 'new-gemini-key')
        ->set('claudeApiKey', 'new-claude-key')
        ->set('openaiApiKey', 'new-openai-key')
        ->set('cohereApiKey', 'new-cohere-key')
        ->call('saveEnvironment')
        ->assertSet('statusType', 'success')
        ->assertSet('statusMessage', 'Environment variables saved successfully.');

    $service = new AiToolConfigService;
    $vars = $service->getEnvVars();

    expect($vars['GEMINI_API_KEY'])->toBe('new-gemini-key')
        ->and($vars['CLAUDE_API_KEY'])->toBe('new-claude-key')
        ->and($vars['OPENAI_API_KEY'])->toBe('new-openai-key')
        ->and($vars['COHERE_API_KEY'])->toBe('new-cohere-key');
});

it('preserves masked keys on save', function () {
    $service = new AiToolConfigService;
    $service->setEnvVars(['GEMINI_API_KEY' => 'original-key']);

    Livewire::test(AiToolsConfig::class)
        ->assertSet('geminiApiKey', '••••••••')
        ->call('saveEnvironment');

    $vars = $service->getEnvVars();

    expect($vars['GEMINI_API_KEY'])->toBe('original-key');
});

it('saves the extra path entry', function () {
    Livewire::test(AiToolsConfig::class)
        ->set('extraPath', '/autodev/bin')
        ->call('saveEnvironment');

    $service = new AiToolConfigService;
    $vars = $service->getEnvVars();

    expect($vars['_extra_path'])->toBe('/autodev/bin');

    $content = file_get_contents($this->tmpDir.'/.bashrc');

    expect($content)->toContain('export PATH="/autodev/bin:$PATH"');
});

it('saves the opencode config json', function () {
    $json = json_encode([
        '$schema' => 'https://opencode.ai/config.json',
        'permission' => ['*' => 'allow'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    Livewire::test(AiToolsConfig::class)
        ->set('opencodeConfigJson', $json)
        ->call('saveOpencodeConfig')
        ->assertSet('statusType', 'success');

    $service = new AiToolConfigService;
    $config = $service->getOpencodeConfig();

    expect($config['permission'])->toBe(['*' => 'allow']);
});

it('shows an error for invalid opencode config json', function () {
    Livewire::test(AiToolsConfig::class)
        ->set('opencodeConfigJson', 'not valid json {{{')
        ->call('saveOpencodeConfig')
        ->assertSet('statusType', 'error')
        ->assertSet('statusMessage', 'Invalid JSON — please fix syntax errors before saving.');
});

it('saves the opencode auth json', function () {
    $json = json_encode([
        'anthropic' => ['type' => 'api', 'key' => 'sk-ant-test'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    Livewire::test(AiToolsConfig::class)
        ->set('opencodeAuthJson', $json)
        ->call('saveOpencodeAuth')
        ->assertSet('statusType', 'success');

    $service = new AiToolConfigService;
    $auth = $service->getOpencodeAuth();

    expect($auth['anthropic']['key'])->toBe('sk-ant-test');
});

it('shows an error for invalid opencode auth json', function () {
    Livewire::test(AiToolsConfig::class)
        ->set('opencodeAuthJson', '{ broken')
        ->call('saveOpencodeAuth')
        ->assertSet('statusType', 'error');
});

it('loads opencode experimental toggles from env vars', function () {
    $service = new AiToolConfigService;
    $service->setEnvVars([
        'OPENCODE_EXPERIMENTAL' => '1',
        'OPENCODE_ENABLE_EXPERIMENTAL_MODELS' => '1',
        'OPENCODE_EXPERIMENTAL_BASH_DEFAULT_TIMEOUT_MS' => '94748364',
    ]);

    Livewire::test(AiToolsConfig::class)
        ->assertSet('opencodeExperimental', true)
        ->assertSet('opencodeEnableExperimentalModels', true)
        ->assertSet('opencodeExperimentalBashTimeoutMs', '94748364');
});
