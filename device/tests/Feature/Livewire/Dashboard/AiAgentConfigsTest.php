<?php

declare(strict_types=1);

use App\Livewire\Dashboard\AiAgentConfigs;
use App\Services\ConfigFileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');

    // Create a mock boost.json file
    $testContent = json_encode([
        'agents' => ['claude_code', 'copilot'],
        'skills' => ['laravel-development'],
    ], JSON_PRETTY_PRINT);

    $boostPath = base_path('boost.json');
    File::put($boostPath, $testContent);

    // Ensure tunnel token file does not exist from previous tests
    if (file_exists('/tunnel/token')) {
        @unlink('/tunnel/token');
    }
});

afterEach(function () {
    // Clean up test files
    $boostPath = base_path('boost.json');
    if (File::exists($boostPath)) {
        File::delete($boostPath);
    }

    // Clean up tunnel token file
    if (file_exists('/tunnel/token')) {
        @unlink('/tunnel/token');
    }
});

it('renders the ai agent configs page', function () {
    Livewire::test(AiAgentConfigs::class)
        ->assertStatus(200)
        ->assertSee('AI Agent Configs');
});

it('displays tabs for all config files', function () {
    Livewire::test(AiAgentConfigs::class)
        ->assertSee('Boost Configuration')
        ->assertSee('OpenCode Global')
        ->assertSee('Claude Code Global')
        ->assertSee('GitHub Copilot Instructions');
});

it('loads boost.json content', function () {
    $component = Livewire::test(AiAgentConfigs::class);

    // Content is in textarea, check the property directly
    $content = $component->get('fileContent.boost');
    expect($content)->toContain('claude_code');
    expect($content)->toContain('copilot');
});

it('validates json content in real-time', function () {
    $component = Livewire::test(AiAgentConfigs::class);

    // Set invalid JSON
    $component->set('fileContent.boost', 'invalid json {');

    $component->assertSet('isValid.boost', false)
        ->assertSet('isDirty.boost', true);
});

it('marks valid json as valid', function () {
    $component = Livewire::test(AiAgentConfigs::class);

    $validJson = json_encode(['test' => 'value']);
    $component->set('fileContent.boost', $validJson);

    $component->assertSet('isValid.boost', true);
});

it('tracks dirty state when content changes', function () {
    $component = Livewire::test(AiAgentConfigs::class);

    $component->assertSet('isDirty.boost', false);

    $component->set('fileContent.boost', '{"modified": true}');

    $component->assertSet('isDirty.boost', true);
});

it('can format json', function () {
    $component = Livewire::test(AiAgentConfigs::class);

    // Set minified JSON
    $minified = '{"agents":["test"],"skills":["php"]}';
    $component->set('fileContent.boost', $minified);

    // Call format
    $component->call('formatJson', 'boost');

    // Should be formatted with newlines and indentation
    $formatted = $component->get('fileContent.boost');
    expect($formatted)->toContain("\n");
    expect($formatted)->toContain('    ');
});

it('prevents saving invalid json', function () {
    $component = Livewire::test(AiAgentConfigs::class);

    $component->set('fileContent.boost', 'invalid {');

    $component->call('save', 'boost');

    $component->assertSet('statusType', 'error');
    $message = $component->get('statusMessage');
    expect($message)->toContain('Cannot save');
    expect($message)->toContain('Invalid JSON');
});

it('can save valid json content', function () {
    $component = Livewire::test(AiAgentConfigs::class);

    $newContent = json_encode(['agents' => ['copilot'], 'skills' => []], JSON_PRETTY_PRINT);
    $component->set('fileContent.boost', $newContent);

    $component->call('save', 'boost');

    $component->assertSet('statusType', 'success')
        ->assertSet('isDirty.boost', false);
});

it('creates backup before saving', function () {
    $service = app(ConfigFileService::class);

    // Clear any existing backups first
    $backupDir = config('vibecodepc.config_editor.backup_directory');
    if (File::isDirectory($backupDir)) {
        File::cleanDirectory($backupDir);
    }

    $component = Livewire::test(AiAgentConfigs::class);

    $newContent = json_encode(['agents' => ['copilot']], JSON_PRETTY_PRINT);
    $component->set('fileContent.boost', $newContent);

    $component->call('save', 'boost');

    // Check backup was created
    $backups = $service->listBackups('boost');
    expect($backups)->toHaveCount(1);
});

it('validates boost.json structure', function () {
    $component = Livewire::test(AiAgentConfigs::class);

    // Invalid structure - agents is not an array
    $invalidContent = json_encode(['agents' => 'not-an-array']);
    $component->set('fileContent.boost', $invalidContent);

    $component->assertSet('isValid.boost', false)
        ->assertSet('validationErrors.boost', 'boost.json: "agents" must be an array');
});

it('allows markdown content for copilot instructions', function () {
    $component = Livewire::test(AiAgentConfigs::class);

    // Set markdown content (not JSON)
    $markdownContent = "# Copilot Instructions\n\nThese are custom instructions.";
    $component->set('fileContent.copilot_instructions', $markdownContent);

    // Should be valid (not JSON)
    $component->assertSet('isValid.copilot_instructions', true);
});

it('allows empty content for new files', function () {
    $component = Livewire::test(AiAgentConfigs::class);

    // Clear content for a file that doesn't exist
    $component->set('fileContent.opencode_global', '');

    $component->assertSet('isValid.opencode_global', true);
});

it('shows error when trying to save empty content', function () {
    $component = Livewire::test(AiAgentConfigs::class);

    $component->set('fileContent.boost', '');
    $component->call('save', 'boost');

    $component->assertSet('statusType', 'error')
        ->assertSet('statusMessage', 'Cannot save empty content.');
});

it('can reset to defaults for boost.json', function () {
    $component = Livewire::test(AiAgentConfigs::class);

    $component->call('resetToDefaults', 'boost');

    $component->assertSet('statusType', 'success')
        ->assertSet('isDirty.boost', true);

    // Check content contains expected keys
    $content = $component->get('fileContent.boost');
    expect($content)->toContain('agents');
    expect($content)->toContain('skills');
});

it('loads reload status on mount', function () {
    $component = Livewire::test(AiAgentConfigs::class);

    // Check that reloadStatus is populated for config files
    $reloadStatus = $component->get('reloadStatus');
    expect($reloadStatus)->toHaveKey('boost');
    expect($reloadStatus['boost'])->toHaveKey('services');
    expect($reloadStatus['boost'])->toHaveKey('requires_manual_reload');
    expect($reloadStatus['boost'])->toHaveKey('instructions');
});

it('shows reload instructions after save for services requiring manual reload', function () {
    $component = Livewire::test(AiAgentConfigs::class);

    $newContent = json_encode(['agents' => ['copilot'], 'skills' => []], JSON_PRETTY_PRINT);
    $component->set('fileContent.boost', $newContent);

    $component->call('save', 'boost');

    $component->assertSet('statusType', 'success');
    // Check that the status message contains reload instructions
    $statusMessage = $component->get('statusMessage');
    expect($statusMessage)->toContain('Boost Configuration');
    expect($statusMessage)->toContain('saved');
});

it('can trigger manual reload for config files', function () {
    $component = Livewire::test(AiAgentConfigs::class);

    $component->call('triggerReload', 'boost');

    // Should get a warning status since MCP servers require manual restart
    $statusType = $component->get('statusType');
    expect($statusType)->toBeIn(['success', 'warning']);
});

it('reload status is updated after file operations', function () {
    $component = Livewire::test(AiAgentConfigs::class);

    $newContent = json_encode(['agents' => ['copilot']], JSON_PRETTY_PRINT);
    $component->set('fileContent.boost', $newContent);
    $component->call('save', 'boost');

    // Check that reloadStatus is still populated after save
    $reloadStatus = $component->get('reloadStatus');
    expect($reloadStatus)->toHaveKey('boost');
    expect($reloadStatus['boost'])->toHaveKey('last_modified_formatted');
});

it('sets isPaired to false when no tunnel config exists', function () {
    $component = Livewire::test(AiAgentConfigs::class);
    $component->assertSet('isPaired', false);
});

it('sets isPaired to true when tunnel is verified', function () {
    \App\Models\TunnelConfig::factory()->verified()->create();

    $component = Livewire::test(AiAgentConfigs::class);
    $component->assertSet('isPaired', true);
});

it('sets isTunnelRunning to false when tunnel token file does not exist', function () {
    // Ensure the tunnel token file does NOT exist (use a non-existent path)
    $testTokenPath = storage_path('framework/testing/tunnel-token-test');
    // Delete any existing file at this path
    if (file_exists($testTokenPath)) {
        @unlink($testTokenPath);
    }

    // Override the TunnelService to use a non-existent token path
    $service = new \App\Services\Tunnel\TunnelService(tokenFilePath: $testTokenPath);
    app()->instance(\App\Services\Tunnel\TunnelService::class, $service);

    $component = Livewire::test(AiAgentConfigs::class);
    $component->assertSet('isTunnelRunning', false);
});

it('sets isTunnelRunning to true when tunnel token file exists', function () {
    // Create the tunnel token file to simulate running tunnel
    $tunnelDir = storage_path('framework/testing/tunnel');
    if (! is_dir($tunnelDir)) {
        mkdir($tunnelDir, 0755, true);
    }
    file_put_contents($tunnelDir.'/token', 'test-token');

    // Override the TunnelService to use our test token path
    $service = new \App\Services\Tunnel\TunnelService(tokenFilePath: $tunnelDir.'/token');
    app()->instance(\App\Services\Tunnel\TunnelService::class, $service);

    $component = Livewire::test(AiAgentConfigs::class);
    $component->assertSet('isTunnelRunning', true);

    // Cleanup
    @unlink($tunnelDir.'/token');
    @rmdir($tunnelDir);
});
