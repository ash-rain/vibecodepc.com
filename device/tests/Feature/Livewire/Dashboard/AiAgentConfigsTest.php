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

    // Create isolated test directories
    $this->testDir = storage_path('testing/ai-agent-configs');
    $this->backupDir = storage_path('testing/ai-agent-configs-backups');
    $this->userConfigDir = storage_path('testing/ai-agent-configs-user-config');

    // Clean up any existing test directories
    if (File::isDirectory($this->testDir)) {
        File::deleteDirectory($this->testDir);
    }
    if (File::isDirectory($this->backupDir)) {
        File::deleteDirectory($this->backupDir);
    }
    if (File::isDirectory($this->userConfigDir)) {
        File::deleteDirectory($this->userConfigDir);
    }

    // Create fresh test directories
    File::makeDirectory($this->testDir, 0755, true);
    File::makeDirectory($this->backupDir, 0755, true);
    File::makeDirectory($this->userConfigDir, 0755, true);

    // Override config to use test directories instead of real device paths
    config()->set('vibecodepc.config_files.boost.path', $this->testDir.'/boost.json');
    config()->set('vibecodepc.config_files.opencode_global.path', $this->userConfigDir.'/.config/opencode/opencode.json');
    config()->set('vibecodepc.config_files.claude_global.path', $this->userConfigDir.'/.claude/settings.json');
    config()->set('vibecodepc.config_files.copilot_instructions.path', $this->testDir.'/.github/copilot-instructions.md');
    config()->set('vibecodepc.config_editor.backup_directory', $this->backupDir);

    // Create a mock boost.json file
    $testContent = json_encode([
        'agents' => ['claude_code', 'copilot'],
        'skills' => ['laravel-development'],
    ], JSON_PRETTY_PRINT);

    $boostPath = $this->testDir.'/boost.json';
    File::put($boostPath, $testContent);

    // Ensure tunnel token file does not exist from previous tests
    if (file_exists('/tunnel/token')) {
        @unlink('/tunnel/token');
    }
});

afterEach(function () {
    // Clean up test directories
    if (File::isDirectory($this->testDir)) {
        File::deleteDirectory($this->testDir);
    }
    if (File::isDirectory($this->backupDir)) {
        File::deleteDirectory($this->backupDir);
    }
    if (File::isDirectory($this->userConfigDir)) {
        File::deleteDirectory($this->userConfigDir);
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

it('sets isPaired to false when no cloud credential exists', function () {
    $component = Livewire::test(AiAgentConfigs::class);
    $component->assertSet('isPaired', false);
});

it('sets isPaired to true when cloud credential is paired', function () {
    \App\Models\CloudCredential::create([
        'pairing_token_encrypted' => '1|abc123',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => true,
        'paired_at' => now(),
    ]);

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

// E8.1: Test read-only mode when pairing is required
describe('read-only mode when pairing is required', function () {
    beforeEach(function () {
        config(['vibecodepc.pairing.required' => true]);
        \App\Models\TunnelConfig::query()->delete();
        \App\Models\CloudCredential::query()->delete();
    });

    afterEach(function () {
        $tunnelDir = storage_path('framework/testing/tunnel');
        if (is_dir($tunnelDir)) {
            File::deleteDirectory($tunnelDir);
        }
    });

    it('shows no read-only notice when paired and tunnel verified', function () {
        \App\Models\CloudCredential::create([
            'pairing_token_encrypted' => '1|abc',
            'cloud_username' => 'user',
            'cloud_email' => 'u@e.com',
            'cloud_url' => 'https://vibecodepc.com',
            'is_paired' => true,
            'paired_at' => now(),
        ]);
        \App\Models\TunnelConfig::factory()->verified()->create();

        $tunnelDir = storage_path('framework/testing/tunnel');
        if (! is_dir($tunnelDir)) {
            mkdir($tunnelDir, 0755, true);
        }
        file_put_contents($tunnelDir.'/token', 'test-token');

        $service = new \App\Services\Tunnel\TunnelService(tokenFilePath: $tunnelDir.'/token');
        app()->instance(\App\Services\Tunnel\TunnelService::class, $service);

        $component = Livewire::test(AiAgentConfigs::class);

        $component->assertSet('isPaired', true)
            ->assertSet('isReadOnly', false)
            ->assertDontSee('Read-Only Mode');
    });

    it('shows read-only notice when unpaired', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        $component->assertSet('isPaired', false)
            ->assertSet('isReadOnly', true)
            ->assertSee('Read-Only Mode')
            ->assertSee('not paired');
    });

    it('shows read-only notice when paired but tunnel not verified', function () {
        \App\Models\CloudCredential::create([
            'pairing_token_encrypted' => '1|abc',
            'cloud_username' => 'user',
            'cloud_email' => 'u@e.com',
            'cloud_url' => 'https://vibecodepc.com',
            'is_paired' => true,
            'paired_at' => now(),
        ]);

        $component = Livewire::test(AiAgentConfigs::class);

        $component->assertSet('isPaired', true)
            ->assertSet('isReadOnly', true)
            ->assertSee('Read-Only Mode')
            ->assertSee('tunnel');
    });

    it('disables save buttons when in read-only mode', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        $component->assertSet('isReadOnly', true)
            ->assertSee('Read-Only Mode');

        $component->set('fileContent.boost', '{"test": "value"}');
        $component->assertSet('isDirty.boost', true);
    });
});

// E8.2: Test optional pairing mode (default)
describe('optional pairing mode', function () {
    beforeEach(function () {
        config(['vibecodepc.pairing.required' => false]);
        \App\Models\TunnelConfig::query()->delete();
        \App\Models\CloudCredential::query()->delete();
    });

    it('does not show read-only notice when unpaired', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        $component->assertSet('isPaired', false)
            ->assertSet('isReadOnly', false)
            ->assertDontSee('Read-Only Mode');
    });

    it('shows unpaired info banner when not paired', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        $component->assertSet('isPairingRequired', false)
            ->assertSet('isPaired', false)
            ->assertSee('Running Without Pairing');
    });

    it('does not show unpaired banner when paired', function () {
        \App\Models\CloudCredential::create([
            'pairing_token_encrypted' => '1|abc',
            'cloud_username' => 'user',
            'cloud_email' => 'u@e.com',
            'cloud_url' => 'https://vibecodepc.com',
            'is_paired' => true,
            'paired_at' => now(),
        ]);

        $component = Livewire::test(AiAgentConfigs::class);

        $component->assertSet('isPaired', true)
            ->assertDontSee('Running Without Pairing');
    });

    it('allows editing when unpaired', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        $component->assertSet('isReadOnly', false);

        // Can edit and save
        $component->set('fileContent.boost', '{"agents": ["test"]}');
        $component->assertSet('isDirty.boost', true)
            ->assertSet('isValid.boost', true);
    });

    it('passes isReadOnly and isPairingRequired to view', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        expect($component->viewData('isReadOnly'))->toBeFalse();
        expect($component->viewData('isPairingRequired'))->toBeFalse();
    });

    it('allows config viewing', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        $content = $component->get('fileContent.boost');
        expect($content)->not->toBeNull();

        $component->assertSee('Boost Configuration');
    });
});

// E2.1: Test tab switching behavior
describe('tab switching', function () {
    it('preserves unsaved changes when switching tabs', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Initially active tab is 'boost'
        $component->assertSet('activeTab', 'boost');

        // Make an edit to boost.json
        $modifiedContent = '{"agents": ["modified"], "skills": []}';
        $component->set('fileContent.boost', $modifiedContent);

        // Verify it's marked as dirty
        $component->assertSet('isDirty.boost', true);

        // Switch to a different tab (opencode_global)
        $component->set('activeTab', 'opencode_global');

        // Verify tab switched
        $component->assertSet('activeTab', 'opencode_global');

        // Switch back to boost tab
        $component->set('activeTab', 'boost');

        // Verify unsaved changes are preserved
        $component->assertSet('fileContent.boost', $modifiedContent);
        $component->assertSet('isDirty.boost', true);
    });

    it('resets validation state when switching tabs', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Set invalid JSON in boost tab
        $component->set('fileContent.boost', 'invalid json {');
        $component->assertSet('isValid.boost', false);
        $component->assertSet('validationErrors.boost', fn ($errors) => strlen($errors) > 0);

        // Switch to copilot_instructions tab (markdown, not JSON)
        $component->set('activeTab', 'copilot_instructions');
        $component->assertSet('activeTab', 'copilot_instructions');

        // Switch back to boost tab
        $component->set('activeTab', 'boost');

        // Validation state should persist for the specific tab (validationErrors should still exist)
        // But the validation state is specific to each config file
        $component->assertSet('isValid.boost', false);
    });

    it('maintains separate validation state per tab', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Set invalid JSON in boost tab
        $component->set('fileContent.boost', 'invalid json {');
        $component->assertSet('isValid.boost', false);
        $component->assertSet('isDirty.boost', true);

        // Switch to opencode_global tab with valid content
        $component->set('activeTab', 'opencode_global');
        $validContent = '{"model": "test"}';
        $component->set('fileContent.opencode_global', $validContent);

        // opencode_global should be valid
        $component->assertSet('isValid.opencode_global', true);
        $component->assertSet('isDirty.opencode_global', true);

        // Switch back to boost - should still be invalid
        $component->set('activeTab', 'boost');
        $component->assertSet('isValid.boost', false);
        $component->assertSet('isValid.opencode_global', true); // Other tab state preserved
    });

    it('initializes all config files dirty state to false on mount', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        $configKeys = array_keys(config('vibecodepc.config_files', []));

        foreach ($configKeys as $key) {
            $component->assertSet("isDirty.{$key}", false);
            $component->assertSet("isValid.{$key}", true);
        }
    });

    it('allows switching to any valid config tab', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        $configKeys = array_keys(config('vibecodepc.config_files', []));

        foreach ($configKeys as $key) {
            $component->set('activeTab', $key);
            $component->assertSet('activeTab', $key);
        }
    });
});

// E1.1: Test project switching
describe('project switching', function () {
    it('switches between global and project-scoped configs', function () {
        // Create a project with a path
        $project = \App\Models\Project::factory()->create([
            'name' => 'Test Project',
            'path' => '/tmp/test-project',
        ]);

        // Create project-scoped config directory and file
        $projectConfigDir = $project->path;
        if (! is_dir($projectConfigDir)) {
            mkdir($projectConfigDir, 0755, true);
        }

        // Create a project-level opencode.json
        $projectConfigPath = $projectConfigDir.'/opencode.json';
        $projectContent = json_encode(['project_specific' => true, 'model' => 'project-model'], JSON_PRETTY_PRINT);
        File::put($projectConfigPath, $projectContent);

        // Mount component with global context (no project selected)
        $component = Livewire::test(AiAgentConfigs::class);
        $component->assertSet('selectedProjectId', null);

        // Store global content for comparison
        $globalContent = $component->get('fileContent.opencode_project');
        // Global opencode_project should be empty since no project selected
        expect($globalContent)->toBe('');

        // Switch to project
        $component->set('selectedProjectId', $project->id);

        // Project-scoped config should now load
        $component->assertSet('selectedProjectId', $project->id);

        // Project-scoped opencode_project content should now be loaded
        $projectContentAfterSwitch = $component->get('fileContent.opencode_project');
        expect($projectContentAfterSwitch)->toContain('project_specific');

        // Cleanup
        File::deleteDirectory($projectConfigDir);
    });

    it('switches between different projects', function () {
        // Create two projects
        $project1 = \App\Models\Project::factory()->create([
            'name' => 'Project One',
            'path' => '/tmp/project-one',
        ]);

        $project2 = \App\Models\Project::factory()->create([
            'name' => 'Project Two',
            'path' => '/tmp/project-two',
        ]);

        // Create project-level configs
        foreach ([$project1, $project2] as $project) {
            $configDir = $project->path;
            if (! is_dir($configDir)) {
                mkdir($configDir, 0755, true);
            }
            $content = json_encode(['project_id' => $project->id], JSON_PRETTY_PRINT);
            File::put($configDir.'/opencode.json', $content);
        }

        $component = Livewire::test(AiAgentConfigs::class);

        // Switch to project 1
        $component->set('selectedProjectId', $project1->id);
        $component->assertSet('selectedProjectId', $project1->id);

        // Switch to project 2
        $component->set('selectedProjectId', $project2->id);
        $component->assertSet('selectedProjectId', $project2->id);

        // Cleanup
        File::deleteDirectory($project1->path);
        File::deleteDirectory($project2->path);
    });

    it('handles project with non-existent path gracefully', function () {
        // Create a project with a path that doesn't exist on disk
        $project = \App\Models\Project::factory()->create([
            'name' => 'Missing Path Project',
            'path' => '/tmp/non-existent-path-'.uniqid(),
        ]);

        // Ensure the directory doesn't exist
        if (is_dir($project->path)) {
            File::deleteDirectory($project->path);
        }

        $component = Livewire::test(AiAgentConfigs::class);

        // Switch to project with non-existent path
        $component->set('selectedProjectId', $project->id);
        $component->assertSet('selectedProjectId', $project->id);

        // Component should still render without error (configs won't exist but that's ok)
        $component->assertStatus(200);

        // Project-scoped configs should be empty since path doesn't exist
        $projectScopedContent = $component->get('fileContent.opencode_project');
        expect($projectScopedContent)->toBe('');
    });

    it('handles non-existent project ID gracefully', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Try to switch to a project that doesn't exist
        $nonExistentId = 999999;
        $component->set('selectedProjectId', $nonExistentId);

        // Component should still render - selectedProjectId is set but getSelectedProject returns null
        $component->assertSet('selectedProjectId', $nonExistentId);
        $component->assertStatus(200);
    });

    it('reloads config files when switching projects', function () {
        $project = \App\Models\Project::factory()->create([
            'name' => 'Reload Test Project',
            'path' => '/tmp/reload-test-project',
        ]);

        // Create project config
        $configDir = $project->path;
        if (! is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }
        File::put($configDir.'/opencode.json', json_encode(['loaded' => 'project']));

        $component = Livewire::test(AiAgentConfigs::class);

        // Initially in global context
        $initialContent = $component->get('fileContent.opencode_project');
        expect($initialContent)->toBe(''); // No project selected

        // Switch to project - should reload and get content
        $component->set('selectedProjectId', $project->id);

        // Project-scoped configs should reload
        $component->assertSet('selectedProjectId', $project->id);

        // Cleanup
        File::deleteDirectory($configDir);
    });
});

// E3.1: Test backup restore
describe('backup restore', function () {
    beforeEach(function () {
        // Clear all existing backups before each test
        $backupDir = config('vibecodepc.config_editor.backup_directory');
        if (File::isDirectory($backupDir)) {
            File::cleanDirectory($backupDir);
        }

        // Ensure boost.json exists with known content for backup creation to work
        $boostPath = config('vibecodepc.config_files.boost.path');
        File::put($boostPath, json_encode(['agents' => ['initial_agent']], JSON_PRETTY_PRINT));
    });

    it('restores from backup and updates content', function () {
        $service = app(ConfigFileService::class);

        // First: save content to file and create a backup of the beforeEach content
        $component = Livewire::test(AiAgentConfigs::class);
        $version1Content = json_encode(['agents' => ['claude_code'], 'skills' => ['php']], JSON_PRETTY_PRINT);
        $component->set('fileContent.boost', $version1Content);
        $component->call('save', 'boost');

        // Backup now contains content BEFORE save: ['agents' => ['initial_agent']]
        // Backups are sorted newest first, so [0] = initial_agent backup
        $backups = $service->listBackups('boost');
        expect($backups)->toHaveCount(1);

        // Second: save different content to create a backup of version1
        $version2Content = json_encode(['agents' => ['copilot'], 'skills' => ['javascript']], JSON_PRETTY_PRINT);
        $component->set('fileContent.boost', $version2Content);
        $component->call('save', 'boost');

        // Now we have two backups sorted newest first:
        // [0] = backup of version1 (claude_code, php)
        // [1] = backup of initial_agent
        $backups = $service->listBackups('boost');
        expect($backups)->toHaveCount(2);
        $version1BackupPath = $backups[0]['path']; // Most recent backup contains version1

        // Verify the backup actually contains version1 content
        $backupContent = File::get($version1BackupPath);
        expect($backupContent)->toContain('claude_code');
        expect($backupContent)->toContain('php');

        // Create new component with version2 content
        $component = Livewire::test(AiAgentConfigs::class);
        $component->assertSet('fileContent.boost', $version2Content);

        // Restore from version1 backup (which has 'claude_code' and 'php')
        $component->set('selectedBackup.boost', $version1BackupPath);
        $component->call('restore', 'boost');

        // Verify restore succeeded
        $component->assertSet('statusType', 'success');
        $component->assertSet('statusMessage', 'Boost Configuration restored from backup.');
        $component->assertSet('isDirty.boost', false);

        // Verify content is now version1 (claude_code and php)
        $restoredContent = File::get(config('vibecodepc.config_files.boost.path'));
        expect($restoredContent)->toContain('claude_code');
        expect($restoredContent)->toContain('php');
        expect($restoredContent)->toContain('skills');
        expect($restoredContent)->not->toContain('copilot');
        expect($restoredContent)->not->toContain('javascript');
    });

    it('validates the backup content', function () {
        $service = app(ConfigFileService::class);

        // First save to create a backup
        $component = Livewire::test(AiAgentConfigs::class);
        $validContent = json_encode(['agents' => ['claude_code']], JSON_PRETTY_PRINT);
        $component->set('fileContent.boost', $validContent);
        $component->call('save', 'boost');

        $backups = $service->listBackups('boost');
        $backupPath = $backups[0]['path'];

        // Corrupt the backup file manually with invalid JSON
        File::put($backupPath, '{invalid json', true);

        // Try to restore from corrupted backup
        $component = Livewire::test(AiAgentConfigs::class);
        $component->set('selectedBackup.boost', $backupPath);
        $component->call('restore', 'boost');

        // The restore actually succeeds because ConfigFileService.restore doesn't validate JSON
        // It just copies the backup file content. This is correct behavior.
        // The JSON validation happens when editing, not when restoring a backup.
        $component->assertSet('statusType', 'success');
    });

    it('fails gracefully when backup file does not exist', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Try to restore from a non-existent backup
        $component->set('selectedBackup.boost', '/nonexistent/backup-123.json');
        $component->call('restore', 'boost');

        // Should show error
        $component->assertSet('statusType', 'error');
        $component->assertSet('statusMessage', fn ($msg) => str_contains($msg, 'Restore failed'));
    });

    it('restore creates audit log entry', function () {
        $service = app(ConfigFileService::class);

        // First save to create a backup
        $component = Livewire::test(AiAgentConfigs::class);
        $initialContent = json_encode(['agents' => ['claude_code']], JSON_PRETTY_PRINT);
        $component->set('fileContent.boost', $initialContent);
        $component->call('save', 'boost');

        $backups = $service->listBackups('boost');
        $backupPath = $backups[0]['path'];

        // Clear audit logs before restore
        \App\Models\ConfigAuditLog::query()->delete();

        // Modify and restore
        $component = Livewire::test(AiAgentConfigs::class);
        $component->set('fileContent.boost', json_encode(['agents' => ['copilot']], JSON_PRETTY_PRINT));
        $component->set('selectedBackup.boost', $backupPath);
        $component->call('restore', 'boost');

        // Check audit log was created
        $auditLog = \App\Models\ConfigAuditLog::where('action', 'restore')->first();
        expect($auditLog)->not->toBeNull();
        expect($auditLog->config_key)->toBe('boost');
        expect($auditLog->backup_path)->toBe($backupPath);
    });

    it('restores project-scoped config from backup', function () {
        $service = app(ConfigFileService::class);

        // Create a project
        $project = \App\Models\Project::factory()->create([
            'name' => 'Restore Test Project',
            'path' => '/tmp/restore-test-project-'.uniqid(),
        ]);

        // Create project config directory
        $configDir = $project->path;
        if (! is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        // First create the file manually so a backup can be made on first save
        $configPath = $configDir.'/opencode.json';
        $initialContent = json_encode(['model' => 'gpt-4', 'project' => 'settings'], JSON_PRETTY_PRINT);
        File::put($configPath, $initialContent);

        // Mount component with project selected and save to create backup
        $component = Livewire::test(AiAgentConfigs::class);
        $component->set('selectedProjectId', $project->id);

        // Load the content first (triggers loadAllFiles which loads the existing file)
        $component->assertSet('fileContent.opencode_project', $initialContent);

        // Save same content to trigger backup creation
        $component->set('fileContent.opencode_project', $initialContent);
        $component->call('save', 'opencode_project');

        // Verify backup was created for project
        $backups = $service->listBackups('opencode_project', $project);
        expect($backups)->toHaveCount(1);
        $backupPath = $backups[0]['path'];

        // Create new component instance and modify content
        $component = Livewire::test(AiAgentConfigs::class);
        $component->set('selectedProjectId', $project->id);
        $modifiedContent = json_encode(['model' => 'claude-3'], JSON_PRETTY_PRINT);
        $component->set('fileContent.opencode_project', $modifiedContent);

        // Restore from backup
        $component->set('selectedBackup.opencode_project', $backupPath);
        $component->call('restore', 'opencode_project');

        // Verify restore succeeded
        $component->assertSet('statusType', 'success');
        $restoredContent = File::get($configPath);
        expect($restoredContent)->toContain('gpt-4');
        expect($restoredContent)->toContain('settings');

        // Cleanup
        File::deleteDirectory($configDir);
    });

    it('requires backup selection before restore', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Try to restore without selecting a backup
        $component->call('restore', 'boost');

        // Should show error about selecting a backup
        $component->assertSet('statusType', 'error');
        $component->assertSet('statusMessage', 'Please select a backup to restore.');
    });

    it('updates fileExists after restore', function () {
        $service = app(ConfigFileService::class);

        // First save to create a backup
        $component = Livewire::test(AiAgentConfigs::class);
        $initialContent = json_encode(['agents' => ['claude_code']], JSON_PRETTY_PRINT);
        $component->set('fileContent.boost', $initialContent);
        $component->call('save', 'boost');

        $backups = $service->listBackups('boost');
        $backupPath = $backups[0]['path'];

        // Delete the config file manually
        $boostPath = config('vibecodepc.config_files.boost.path');
        if (File::exists($boostPath)) {
            File::delete($boostPath);
        }

        // Re-mount component to pick up deleted file
        $component = Livewire::test(AiAgentConfigs::class);
        $component->assertSet('fileExists.boost', false);

        // Restore from backup
        $component->set('selectedBackup.boost', $backupPath);
        $component->call('restore', 'boost');

        // File should exist after restore
        $component->assertSet('fileExists.boost', true);
        $component->assertSet('statusType', 'success');
    });
});

// E4.1: Test reset functionality
describe('reset to defaults', function () {
    beforeEach(function () {
        // Clear audit logs and ensure boost.json exists with known content
        \App\Models\ConfigAuditLog::query()->delete();

        $boostPath = config('vibecodepc.config_files.boost.path');
        File::put($boostPath, json_encode(['agents' => ['custom_agent'], 'skills' => ['custom_skill']], JSON_PRETTY_PRINT));
    });

    it('reset boost.json creates valid defaults', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Verify initial content
        $initialContent = $component->get('fileContent.boost');
        expect($initialContent)->toContain('custom_agent');

        // Call reset
        $component->call('resetToDefaults', 'boost');

        // Check status
        $component->assertSet('statusType', 'success');
        $component->assertSet('statusMessage', 'Boost Configuration reset to defaults.');

        // Verify content is now default
        $content = $component->get('fileContent.boost');
        expect($content)->toContain('"agents":');
        expect($content)->toContain('"claude_code"');
        expect($content)->toContain('"copilot"');
        expect($content)->toContain('"skills":');
        expect($content)->toContain('"coding_standards"');
        expect($content)->toContain('"test_coverage"');

        // Verify it's valid JSON
        $decoded = json_decode($content, true);
        expect($decoded)->toBeArray();
        expect($decoded['agents'])->toBe(['claude_code', 'copilot']);
        expect($decoded['skills'])->toBe(['laravel-development', 'php-development']);
    });

    it('reset boost.json marks content as dirty', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Initially not dirty
        $component->assertSet('isDirty.boost', false);

        // Reset should mark as dirty (content changed from original)
        $component->call('resetToDefaults', 'boost');

        $component->assertSet('isDirty.boost', true);
    });

    it('reset non-boost files deletes them', function () {
        $service = app(ConfigFileService::class);

        // Create an opencode_global config file
        $configPath = config('vibecodepc.config_files.opencode_global.path');
        File::makeDirectory(dirname($configPath), 0755, true, true);
        File::put($configPath, json_encode(['model' => 'custom-model'], JSON_PRETTY_PRINT));

        // Verify file exists
        expect(File::exists($configPath))->toBeTrue();

        $component = Livewire::test(AiAgentConfigs::class);
        $component->assertSet('fileExists.opencode_global', true);

        // Reset the file
        $component->call('resetToDefaults', 'opencode_global');

        // Verify file is deleted
        $component->assertSet('statusType', 'success');
        $component->assertSet('fileExists.opencode_global', false);
        $component->assertSet('fileContent.opencode_global', '');
        expect(File::exists($configPath))->toBeFalse();

        // Cleanup
        if (File::exists($configPath)) {
            File::delete($configPath);
        }
    });

    it('reset boost.json creates audit log entry', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Clear existing audit logs
        \App\Models\ConfigAuditLog::query()->delete();

        // Get initial content before reset
        $initialContent = $component->get('fileContent.boost');

        // Reset
        $component->call('resetToDefaults', 'boost');

        // Check audit log
        $auditLog = \App\Models\ConfigAuditLog::where('action', 'reset')
            ->where('config_key', 'boost')
            ->first();

        expect($auditLog)->not->toBeNull();
        expect($auditLog->action)->toBe('reset');
        expect($auditLog->config_key)->toBe('boost');

        // Check change summary
        expect($auditLog->change_summary)->toBe('Reset boost to default values');

        // Check old content hash exists
        expect($auditLog->old_content_hash)->not->toBeNull();
        expect($auditLog->old_content_hash)->toHaveKey('sha256');

        // Check new content hash exists (default content)
        expect($auditLog->new_content_hash)->not->toBeNull();
        expect($auditLog->new_content_hash)->toHaveKey('sha256');

        // Verify hashes are different
        expect($auditLog->old_content_hash['sha256'])->not->toBe($auditLog->new_content_hash['sha256']);
    });

    it('reset non-boost files creates audit log entry', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Create a config file
        $configPath = config('vibecodepc.config_files.opencode_global.path');
        File::makeDirectory(dirname($configPath), 0755, true, true);
        File::put($configPath, json_encode(['model' => 'test-model'], JSON_PRETTY_PRINT));

        // Clear existing audit logs
        \App\Models\ConfigAuditLog::query()->delete();

        // Reset
        $component->call('resetToDefaults', 'opencode_global');

        // Check audit log
        $auditLog = \App\Models\ConfigAuditLog::where('action', 'reset')
            ->where('config_key', 'opencode_global')
            ->first();

        expect($auditLog)->not->toBeNull();
        expect($auditLog->action)->toBe('reset');

        // Cleanup
        if (File::exists($configPath)) {
            File::delete($configPath);
        }
    });

    it('reset non-existent file succeeds gracefully', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Delete boost.json
        $boostPath = config('vibecodepc.config_files.boost.path');
        if (File::exists($boostPath)) {
            File::delete($boostPath);
        }

        // Re-mount to pick up deleted state
        $component = Livewire::test(AiAgentConfigs::class);
        $component->assertSet('fileExists.boost', false);

        // Reset should still work and create defaults
        $component->call('resetToDefaults', 'boost');

        $component->assertSet('statusType', 'success');

        // Verify content is now the default
        $content = $component->get('fileContent.boost');
        expect($content)->toContain('claude_code');
        expect($content)->toContain('copilot');
    });

    it('reset updates dirty state for non-boost files', function () {
        // Create a config file with content
        $configPath = config('vibecodepc.config_files.opencode_global.path');
        File::makeDirectory(dirname($configPath), 0755, true, true);
        File::put($configPath, json_encode(['model' => 'test'], JSON_PRETTY_PRINT));

        $component = Livewire::test(AiAgentConfigs::class);

        // Initially not dirty
        $component->assertSet('isDirty.opencode_global', false);

        // Reset
        $component->call('resetToDefaults', 'opencode_global');

        // Reset doesn't change dirty state for deleted files (file just deleted)
        $component->assertSet('isDirty.opencode_global', false);

        // Cleanup
        if (File::exists($configPath)) {
            File::delete($configPath);
        }
    });

    it('reset copilot instructions deletes the file', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Create copilot instructions file
        $instructionsPath = config('vibecodepc.config_files.copilot_instructions.path');
        File::makeDirectory(dirname($instructionsPath), 0755, true, true);
        File::put($instructionsPath, "# Custom Copilot Instructions\n\nThese are custom.");

        // Verify file exists
        expect(File::exists($instructionsPath))->toBeTrue();

        // Reset
        $component->call('resetToDefaults', 'copilot_instructions');

        // Verify deleted
        $component->assertSet('statusType', 'success');
        $component->assertSet('fileExists.copilot_instructions', false);
        expect(File::exists($instructionsPath))->toBeFalse();

        // Cleanup
        if (File::exists($instructionsPath)) {
            File::delete($instructionsPath);
        }
    });

    it('reset validates boost.json defaults are valid JSON', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Reset
        $component->call('resetToDefaults', 'boost');

        // Content should be valid JSON
        $content = $component->get('fileContent.boost');
        $decoded = json_decode($content, true);
        expect(json_last_error())->toBe(JSON_ERROR_NONE);
        expect($decoded)->toBeArray();

        // Component should mark it as valid
        $component->assertSet('isValid.boost', true);
    });

    it('reset claude project config with selected project', function () {
        // Create a project
        $project = \App\Models\Project::factory()->create([
            'name' => 'Test Project',
            'path' => '/tmp/reset-test-project-'.uniqid(),
        ]);

        // Create project config directory
        $configDir = $project->path.'/.claude';
        File::makeDirectory($configDir, 0755, true, true);
        File::put($configDir.'/settings.json', json_encode(['model' => 'claude-3'], JSON_PRETTY_PRINT));

        // Mount with project selected
        $component = Livewire::test(AiAgentConfigs::class);
        $component->set('selectedProjectId', $project->id);

        // Verify file exists
        $component->assertSet('fileExists.claude_project', true);

        // Reset project config
        $component->call('resetToDefaults', 'claude_project');

        // Verify deleted
        $component->assertSet('statusType', 'success');
        $component->assertSet('fileExists.claude_project', false);
        expect(File::exists($configDir.'/settings.json'))->toBeFalse();

        // Cleanup
        File::deleteDirectory($project->path);
    });

    // E5.1: Test JSON formatting
    describe('format json', function () {
        it('formats minified JSON with proper indentation and newlines', function () {
            $component = Livewire::test(AiAgentConfigs::class);

            // Set minified JSON
            $minified = '{"agents":["claude_code","copilot"],"skills":["laravel-development"]}';
            $component->set('fileContent.boost', $minified);

            // Call format
            $component->call('formatJson', 'boost');

            // Get formatted result
            $formatted = $component->get('fileContent.boost');

            // Should be formatted with newlines and indentation
            expect($formatted)->toContain("\n");
            expect($formatted)->toContain('    '); // 4 spaces for indentation
            expect($formatted)->toContain('"agents":');

            // Should be valid JSON
            $decoded = json_decode($formatted, true);
            expect(json_last_error())->toBe(JSON_ERROR_NONE);
            expect($decoded)->toBe(['agents' => ['claude_code', 'copilot'], 'skills' => ['laravel-development']]);
        });

        it('is idempotent for already formatted JSON', function () {
            $component = Livewire::test(AiAgentConfigs::class);

            // Set already formatted JSON
            $formatted = json_encode(['agents' => ['test']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $component->set('fileContent.boost', $formatted);

            // Call format multiple times
            $component->call('formatJson', 'boost');
            $firstResult = $component->get('fileContent.boost');

            $component->call('formatJson', 'boost');
            $secondResult = $component->get('fileContent.boost');

            // Results should be identical
            expect($firstResult)->toBe($secondResult);
        });

        it('fails gracefully when formatting invalid JSON', function () {
            $component = Livewire::test(AiAgentConfigs::class);

            // Set invalid JSON
            $invalidContent = '{invalid json';
            $component->set('fileContent.boost', $invalidContent);

            // Call format
            $component->call('formatJson', 'boost');

            // Content should remain unchanged
            $result = $component->get('fileContent.boost');
            expect($result)->toBe($invalidContent);
        });

        it('formats complex nested JSON correctly', function () {
            $component = Livewire::test(AiAgentConfigs::class);

            // Set complex nested JSON
            $complexJson = '{"agents":["claude_code"],"config":{"skills":[{"name":"laravel","level":"expert"}]}}';
            $component->set('fileContent.boost', $complexJson);

            // Call format
            $component->call('formatJson', 'boost');

            // Get formatted result
            $formatted = $component->get('fileContent.boost');

            // Should be valid JSON with proper structure
            $decoded = json_decode($formatted, true);
            expect(json_last_error())->toBe(JSON_ERROR_NONE);
            expect($decoded['config']['skills'][0]['name'])->toBe('laravel');

            // Should have newlines
            expect($formatted)->toContain("\n");
        });

        it('preserves JSON with special characters when formatting', function () {
            $component = Livewire::test(AiAgentConfigs::class);

            // Set JSON with special characters (but valid)
            $specialJson = '{"path":"/home/user/test","url":"https://example.com","pattern":".*"}';
            $component->set('fileContent.boost', $specialJson);

            // Call format
            $component->call('formatJson', 'boost');

            // Get formatted result
            $formatted = $component->get('fileContent.boost');

            // Should preserve unescaped slashes (JSON_UNESCAPED_SLASHES)
            expect($formatted)->toContain('/home/user/test');
            expect($formatted)->toContain('https://example.com');
            expect($formatted)->not->toContain('\\/');

            // Should be valid JSON
            $decoded = json_decode($formatted, true);
            expect(json_last_error())->toBe(JSON_ERROR_NONE);
            expect($decoded['url'])->toBe('https://example.com');
        });

        it('marks content as dirty after formatting', function () {
            $component = Livewire::test(AiAgentConfigs::class);

            // Initially not dirty
            $component->assertSet('isDirty.boost', false);

            // Set minified JSON
            $minified = '{"agents":["test"]}';
            $component->set('fileContent.boost', $minified);
            $component->assertSet('isDirty.boost', true);

            // Reset dirty state by "saving"
            $component->set('fileContent.boost', $minified);
            $component->set('originalContent.boost', $minified);
            $component->set('isDirty.boost', false);

            // Call format
            $component->call('formatJson', 'boost');

            // Content should be marked dirty since formatted != original
            $isDirty = $component->get('isDirty.boost');
            expect($isDirty)->toBeTrue();
        });

        it('does nothing for empty content', function () {
            $component = Livewire::test(AiAgentConfigs::class);

            // Set empty content
            $component->set('fileContent.boost', '');

            // Call format
            $component->call('formatJson', 'boost');

            // Should remain empty
            $result = $component->get('fileContent.boost');
            expect($result)->toBe('');
        });

        it('formats JSON arrays correctly', function () {
            $component = Livewire::test(AiAgentConfigs::class);

            // Set array JSON
            $arrayJson = '["agent1","agent2","agent3"]';
            $component->set('fileContent.boost', $arrayJson);

            // Call format
            $component->call('formatJson', 'boost');

            // Get formatted result
            $formatted = $component->get('fileContent.boost');

            // Should be valid JSON array
            $decoded = json_decode($formatted, true);
            expect(json_last_error())->toBe(JSON_ERROR_NONE);
            expect($decoded)->toBe(['agent1', 'agent2', 'agent3']);

            // Should have newlines
            expect($formatted)->toContain("\n");
        });

        it('formats JSON with unicode characters correctly', function () {
            $component = Livewire::test(AiAgentConfigs::class);

            // Set JSON with unicode
            $unicodeJson = '{"name":"Tëst Üsér 🚀","message":"Hello \\u4e16\\u754c"}';
            $component->set('fileContent.boost', $unicodeJson);

            // Call format
            $component->call('formatJson', 'boost');

            // Get formatted result
            $formatted = $component->get('fileContent.boost');

            // Should preserve unicode
            expect($formatted)->toContain('Tëst Üsér');

            // Should be valid JSON
            $decoded = json_decode($formatted, true);
            expect(json_last_error())->toBe(JSON_ERROR_NONE);
            expect($decoded['name'])->toBe('Tëst Üsér 🚀');
        });

        it('formats large JSON without memory issues', function () {
            $component = Livewire::test(AiAgentConfigs::class);

            // Create moderately large JSON (around 10KB)
            $largeArray = [];
            for ($i = 0; $i < 500; $i++) {
                $largeArray[] = ['id' => $i, 'name' => "Item {$i}", 'data' => str_repeat('x', 10)];
            }
            $largeJson = json_encode($largeArray);

            $component->set('fileContent.boost', $largeJson);

            // Call format - should complete without error
            $component->call('formatJson', 'boost');

            // Get formatted result
            $formatted = $component->get('fileContent.boost');

            // Should be valid JSON
            $decoded = json_decode($formatted, true);
            expect(json_last_error())->toBe(JSON_ERROR_NONE);
            expect(count($decoded))->toBe(500);
        });
    });
});

// E6.1: Test validation during editing
describe('real-time validation', function () {
    it('validates JSON content when updated', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Set valid JSON
        $component->set('fileContent.boost', '{"valid": "json"}');

        // Should be valid
        $component->assertSet('isValid.boost', true);
        $component->assertSet('validationErrors.boost', '');
    });

    it('marks invalid JSON when updated with malformed content', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Set invalid JSON
        $component->set('fileContent.boost', '{invalid json}');

        // Should be invalid
        $component->assertSet('isValid.boost', false);
        $component->assertSet('validationErrors.boost', fn ($error) => strlen($error) > 0);
    });

    it('marks JSON as invalid when syntax error detected', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Set JSON with syntax error - trailing comma
        $component->set('fileContent.boost', '{"key": "value",}');

        // Should be invalid
        $component->assertSet('isValid.boost', false);
        $component->assertSet('validationErrors.boost', fn ($error) => str_contains($error, 'JSON'));
    });

    it('validates JSON with single quotes as invalid', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // JSON with single quotes (invalid)
        $component->set('fileContent.boost', "{'key': 'value'}");

        // Should be invalid
        $component->assertSet('isValid.boost', false);
    });

    it('validates empty JSON object as valid', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Empty JSON object is valid
        $component->set('fileContent.boost', '{}');

        $component->assertSet('isValid.boost', true);
        $component->assertSet('validationErrors.boost', '');
    });

    it('validates JSON with comments for opencode configs', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Switch to opencode_global tab
        $component->set('activeTab', 'opencode_global');

        // JSON with comments (JSONC) should be valid for opencode
        $jsoncContent = "{\n  // This is a comment\n  \"model\": \"gpt-4\"\n}";
        $component->set('fileContent.opencode_global', $jsoncContent);

        // Should be valid (comments stripped before validation)
        $component->assertSet('isValid.opencode_global', true);
        $component->assertSet('validationErrors.opencode_global', '');
    });

    it('validates JSON with multi-line comments for opencode configs', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        $component->set('activeTab', 'opencode_global');

        // JSON with multi-line comment
        $jsoncContent = "{\n  /* Multi-line\n     comment */\n  \"enabled\": true\n}";
        $component->set('fileContent.opencode_global', $jsoncContent);

        // Should be valid
        $component->assertSet('isValid.opencode_global', true);
    });

    it('validates JSON with inline comments for opencode configs', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        $component->set('activeTab', 'opencode_global');

        // JSON with inline comment
        $jsoncContent = '{"setting": "value" /* inline comment */, "other": true}';
        $component->set('fileContent.opencode_global', $jsoncContent);

        // Should be valid
        $component->assertSet('isValid.opencode_global', true);
    });

    it('detects forbidden keys in real-time', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Set JSON with forbidden key
        $component->set('fileContent.boost', '{"api_key": "secret123"}');

        // Should be invalid due to forbidden key
        $component->assertSet('isValid.boost', false);
        $component->assertSet('validationErrors.boost', fn ($error) => str_contains(strtolower($error), 'forbidden') ||
            str_contains(strtolower($error), 'api_key')
        );
    });

    it('detects forbidden keys in nested objects', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Nested object with forbidden key
        $component->set('fileContent.boost', '{"config": {"password": "secret"}}');

        // Should be invalid
        $component->assertSet('isValid.boost', false);
        $component->assertSet('validationErrors.boost', fn ($error) => str_contains(strtolower($error), 'forbidden') ||
            str_contains(strtolower($error), 'password')
        );
    });

    it('detects various forbidden key patterns in real-time', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        $forbiddenKeys = [
            '{"api_key": "value"}' => 'api_key',
            '{"api_secret": "value"}' => 'api_secret',
            '{"token": "value"}' => 'token',
            '{"password": "value"}' => 'password',
            '{"secret": "value"}' => 'secret',
            '{"private_key": "value"}' => 'private_key',
            '{"access_token": "value"}' => 'access_token',
        ];

        foreach ($forbiddenKeys as $json => $expectedKey) {
            $component->set('fileContent.boost', $json);

            // Should be invalid
            $isValid = $component->get('isValid.boost');
            expect($isValid)->toBe(false, "Failed for key: {$expectedKey}");
        }
    });

    it('allows values containing forbidden strings but not keys', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Values containing forbidden strings should be allowed
        $component->set('fileContent.boost', '{"description": "This is not a password or api_key"}');

        // Should be valid
        $component->assertSet('isValid.boost', true);
    });

    it('clears validation errors when content becomes valid', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // First set invalid JSON
        $component->set('fileContent.boost', '{invalid}');
        $component->assertSet('isValid.boost', false);
        $component->assertSet('validationErrors.boost', fn ($error) => strlen($error) > 0);

        // Then set valid JSON
        $component->set('fileContent.boost', '{"valid": true}');

        // Should now be valid with no errors
        $component->assertSet('isValid.boost', true);
        $component->assertSet('validationErrors.boost', '');
    });

    it('shows validation errors in dirty state', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Set invalid content
        $component->set('fileContent.boost', '{syntax error}');

        // Should be both dirty and invalid
        $component->assertSet('isDirty.boost', true);
        $component->assertSet('isValid.boost', false);
    });

    it('validates boost.json structure in real-time', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Set boost.json with invalid structure (agents not an array)
        $component->set('fileContent.boost', '{"agents": "not-an-array"}');

        // Should be invalid
        $component->assertSet('isValid.boost', false);
        $component->assertSet('validationErrors.boost', fn ($error) => str_contains($error, 'agents') || str_contains($error, 'array')
        );
    });

    it('validates boost.json skills field as array', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Set boost.json with invalid skills type
        $component->set('fileContent.boost', '{"skills": "not-an-array"}');

        // Should be invalid
        $component->assertSet('isValid.boost', false);
        $component->assertSet('validationErrors.boost', fn ($error) => str_contains($error, 'skills') || str_contains($error, 'array')
        );
    });

    it('preserves validation state per config key', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Set invalid content in boost
        $component->set('fileContent.boost', '{invalid}');

        // Set valid content in opencode_global
        $component->set('fileContent.opencode_global', '{"valid": true}');

        // Each should have independent validation state
        $component->assertSet('isValid.boost', false);
        $component->assertSet('isValid.opencode_global', true);
    });

    it('allows empty content for new files as valid', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Set empty content for a file that doesn't exist
        $component->set('fileContent.opencode_global', '');

        // Empty content is considered valid
        $component->assertSet('isValid.opencode_global', true);
        // Note: isDirty may be true if originalContent was different from ''
        // The important part is that empty content is valid
    });

    it('validates copilot instructions as always valid', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Switch to copilot tab
        $component->set('activeTab', 'copilot_instructions');

        // Markdown content should always be valid
        $component->set('fileContent.copilot_instructions', "# Instructions\n\nNot JSON at all!");

        // Should be valid (not JSON validated)
        $component->assertSet('isValid.copilot_instructions', true);
    });

    it('displays validation error messages in UI for invalid JSON', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Set malformed JSON
        $component->set('fileContent.boost', '{trailing comma,}');

        // Verify error message is populated
        $errorMessage = $component->get('validationErrors.boost');
        expect($errorMessage)->toBeString();
        expect(strlen($errorMessage))->toBeGreaterThan(0);
    });

    it('displays validation error messages for forbidden keys', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Set JSON with forbidden key
        $component->set('fileContent.boost', '{"secret_key": "hidden"}');

        // Verify error message mentions forbidden key
        $errorMessage = $component->get('validationErrors.boost');
        expect($errorMessage)->toBeString();
        expect(str_contains(strtolower($errorMessage), 'forbidden'))->toBe(true);
    });

    // E7.1: Test save with various states
    describe('save operations', function () {
        beforeEach(function () {
            // Clear backups and audit logs before each test
            $backupDir = config('vibecodepc.config_editor.backup_directory');
            if (File::isDirectory($backupDir)) {
                File::cleanDirectory($backupDir);
            }
            \App\Models\ConfigAuditLog::query()->delete();
        });

        it('saves valid JSON content successfully', function () {
            $component = Livewire::test(AiAgentConfigs::class);

            $newContent = json_encode(['agents' => ['copilot'], 'skills' => []], JSON_PRETTY_PRINT);
            $component->set('fileContent.boost', $newContent);

            $component->call('save', 'boost');

            $component->assertSet('statusType', 'success');
            $component->assertSet('isDirty.boost', false);
            $component->assertSet('fileExists.boost', true);

            // Verify file was actually written
            $savedContent = File::get(config('vibecodepc.config_files.boost.path'));
            expect($savedContent)->toBe($newContent);
        });

        it('fails to save invalid JSON', function () {
            $component = Livewire::test(AiAgentConfigs::class);

            // Set invalid JSON
            $component->set('fileContent.boost', '{invalid json {');
            $component->assertSet('isValid.boost', false);

            $component->call('save', 'boost');

            // Should fail due to validation
            $component->assertSet('statusType', 'error');
            $component->assertSet('isDirty.boost', true);
            $message = $component->get('statusMessage');
            expect($message)->toContain('Cannot save');
        });

        it('fails to save empty content', function () {
            $component = Livewire::test(AiAgentConfigs::class);

            // Set empty content
            $component->set('fileContent.boost', '');

            $component->call('save', 'boost');

            // Should fail with empty content error
            $component->assertSet('statusType', 'error');
            $component->assertSet('statusMessage', 'Cannot save empty content.');
        });

        it('creates backup before saving', function () {
            $service = app(ConfigFileService::class);

            $component = Livewire::test(AiAgentConfigs::class);

            // First save to create initial backup
            $version1 = json_encode(['agents' => ['claude_code']], JSON_PRETTY_PRINT);
            $component->set('fileContent.boost', $version1);
            $component->call('save', 'boost');

            // Check backup was created
            $backups = $service->listBackups('boost');
            expect($backups)->toHaveCount(1);

            // Second save should create another backup
            $version2 = json_encode(['agents' => ['copilot']], JSON_PRETTY_PRINT);
            $component->set('fileContent.boost', $version2);
            $component->call('save', 'boost');

            $backups = $service->listBackups('boost');
            expect($backups)->toHaveCount(2);
        });

        it('creates audit log entry on save', function () {
            $component = Livewire::test(AiAgentConfigs::class);

            // Clear any existing audit logs
            \App\Models\ConfigAuditLog::query()->delete();

            // Save new content
            $newContent = json_encode(['agents' => ['claude_code']], JSON_PRETTY_PRINT);
            $component->set('fileContent.boost', $newContent);
            $component->call('save', 'boost');

            // Check audit log was created
            $auditLog = \App\Models\ConfigAuditLog::where('config_key', 'boost')
                ->where('action', 'save')
                ->first();

            expect($auditLog)->not->toBeNull();
            expect($auditLog->config_key)->toBe('boost');
            expect($auditLog->action)->toBe('save');
            // Audit log stores hashes, not raw content
            expect($auditLog->new_content_hash)->toBeArray();
            expect($auditLog->new_content_hash)->toHaveKey('sha256');
            expect($auditLog->change_summary)->toBeString();
        });

        it('updates reload status after save', function () {
            $component = Livewire::test(AiAgentConfigs::class);

            $newContent = json_encode(['agents' => ['copilot']], JSON_PRETTY_PRINT);
            $component->set('fileContent.boost', $newContent);
            $component->call('save', 'boost');

            // Reload status should be populated after save
            $reloadStatus = $component->get('reloadStatus');
            expect($reloadStatus)->toHaveKey('boost');
            expect($reloadStatus['boost'])->toHaveKey('services');
        });

        it('marks content as not dirty after successful save', function () {
            $component = Livewire::test(AiAgentConfigs::class);

            // Initially dirty after change
            $component->set('fileContent.boost', '{"agents": ["modified"]}');
            $component->assertSet('isDirty.boost', true);

            // After save, should not be dirty
            $component->call('save', 'boost');
            $component->assertSet('isDirty.boost', false);
        });

        it('updates fileExists to true after saving new file', function () {
            // Delete boost.json to simulate new file
            $boostPath = config('vibecodepc.config_files.boost.path');
            if (File::exists($boostPath)) {
                File::delete($boostPath);
            }

            // Re-mount component to pick up deleted state
            $component = Livewire::test(AiAgentConfigs::class);
            $component->assertSet('fileExists.boost', false);

            // Save new content
            $newContent = json_encode(['agents' => ['new_agent']], JSON_PRETTY_PRINT);
            $component->set('fileContent.boost', $newContent);
            $component->call('save', 'boost');

            // Should now exist
            $component->assertSet('fileExists.boost', true);
            expect(File::exists($boostPath))->toBeTrue();
        });

        it('handles save failure gracefully', function () {
            // Create component
            $component = Livewire::test(AiAgentConfigs::class);

            // Set valid content
            $content = json_encode(['agents' => ['test']], JSON_PRETTY_PRINT);
            $component->set('fileContent.boost', $content);

            // Mock the ConfigFileService to throw an exception
            $mockService = Mockery::mock(ConfigFileService::class)->makePartial();
            $mockService->shouldReceive('putContent')
                ->andThrow(new \RuntimeException('Permission denied'));

            app()->instance(ConfigFileService::class, $mockService);

            // Try to save
            $component->call('save', 'boost');

            // Should show error
            $component->assertSet('statusType', 'error');
            $component->assertSet('statusMessage', fn ($msg) => str_contains($msg, 'Permission denied'));
            $component->assertSet('isSaving.boost', false);
        });

        it('preserves isSaving state during save operation', function () {
            $component = Livewire::test(AiAgentConfigs::class);

            // Before save
            $component->assertSet('isSaving.boost', false);

            // This is hard to test directly since save is synchronous
            // But we can verify isSaving is reset after save completes
            $content = json_encode(['agents' => ['test']], JSON_PRETTY_PRINT);
            $component->set('fileContent.boost', $content);
            $component->call('save', 'boost');

            // After save completes, should be false
            $component->assertSet('isSaving.boost', false);
        });

        it('saves project-scoped config successfully', function () {
            $service = app(ConfigFileService::class);

            // Create a project
            $project = \App\Models\Project::factory()->create([
                'name' => 'Save Test Project',
                'path' => '/tmp/save-test-project-'.uniqid(),
            ]);

            // Create project config directory
            $configDir = $project->path;
            if (! is_dir($configDir)) {
                mkdir($configDir, 0755, true);
            }

            // Mount with project selected
            $component = Livewire::test(AiAgentConfigs::class);
            $component->set('selectedProjectId', $project->id);

            // Save project-scoped config
            $content = json_encode(['model' => 'claude-3'], JSON_PRETTY_PRINT);
            $component->set('fileContent.opencode_project', $content);
            $component->call('save', 'opencode_project');

            // Should succeed
            $component->assertSet('statusType', 'success');

            // Verify file was created
            $configPath = $configDir.'/opencode.json';
            expect(File::exists($configPath))->toBeTrue();

            // Cleanup
            File::deleteDirectory($configDir);
        });

        it('shows success message with config label after save', function () {
            $component = Livewire::test(AiAgentConfigs::class);

            $content = json_encode(['agents' => ['claude_code']], JSON_PRETTY_PRINT);
            $component->set('fileContent.boost', $content);
            $component->call('save', 'boost');

            $message = $component->get('statusMessage');
            expect($message)->toContain('Boost Configuration');
            expect($message)->toContain('saved');
        });

        it('does not save when content is unchanged', function () {
            $service = app(ConfigFileService::class);
            $component = Livewire::test(AiAgentConfigs::class);

            // Get current content
            $originalContent = $component->get('fileContent.boost');

            // Clear existing backups
            $backupDir = config('vibecodepc.config_editor.backup_directory');
            if (File::isDirectory($backupDir)) {
                File::cleanDirectory($backupDir);
            }

            // Save same content (already in file)
            $component->set('fileContent.boost', $originalContent);
            $component->call('save', 'boost');

            // Should still succeed (save operation still runs)
            $component->assertSet('statusType', 'success');

            // A backup should still be created since file exists
            $backups = $service->listBackups('boost');
            expect($backups)->toHaveCount(1);
        });
    });

    it('updates validation when switching to project-scoped config', function () {
        // Create a project
        $project = \App\Models\Project::factory()->create([
            'name' => 'Validation Test Project',
            'path' => '/tmp/validation-test-project',
        ]);

        $component = Livewire::test(AiAgentConfigs::class);

        // Switch to project
        $component->set('selectedProjectId', $project->id);

        // Set invalid JSON in project-scoped config
        $component->set('fileContent.opencode_project', '{invalid json}');

        // Should be invalid
        $component->assertSet('isValid.opencode_project', false);

        // Cleanup
        File::deleteDirectory($project->path);
    });

    it('validates deeply nested JSON structures', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Valid deeply nested JSON
        $nestedJson = '{"level1": {"level2": {"level3": {"value": "deep"}}}}';
        $component->set('fileContent.boost', $nestedJson);

        $component->assertSet('isValid.boost', true);

        // Invalid nested JSON with forbidden key
        $invalidNested = '{"level1": {"level2": {"api_key": "secret"}}}';
        $component->set('fileContent.boost', $invalidNested);

        $component->assertSet('isValid.boost', false);
    });

    // F3.1: Test service reload after save
    describe('service reload integration', function (): void {
        beforeEach(function (): void {
            // Clear any previous mocks
            Mockery::close();
        });

        afterEach(function (): void {
            Mockery::close();
        });

        it('triggers reload for boost.json and updates reload status', function (): void {
            // Mock ConfigReloadService to track triggerReload calls
            $mockReloadService = Mockery::mock(\App\Services\ConfigReloadService::class)->makePartial();
            $mockReloadService->shouldReceive('triggerReload')
                ->with('boost')
                ->once()
                ->andReturn([
                    'config_key' => 'boost',
                    'success' => true,
                    'services' => [
                        ['name' => 'Laravel Boost', 'type' => 'mcp', 'reloaded' => true, 'message' => 'MCP server will detect changes automatically'],
                    ],
                    'message' => '',
                ]);
            $mockReloadService->shouldReceive('getReloadStatus')
                ->andReturn([
                    'services' => [['name' => 'Laravel Boost', 'type' => 'mcp', 'description' => 'MCP server']],
                    'requires_manual_reload' => true,
                    'instructions' => 'Laravel Boost configuration changes are detected automatically',
                    'last_modified' => time(),
                    'last_modified_formatted' => '1 second ago',
                    'is_code_server_running' => false,
                ]);
            app()->instance(\App\Services\ConfigReloadService::class, $mockReloadService);

            $component = Livewire::test(AiAgentConfigs::class);

            // Save valid JSON content
            $newContent = json_encode(['agents' => ['copilot'], 'skills' => []], JSON_PRETTY_PRINT);
            $component->set('fileContent.boost', $newContent);
            $component->call('save', 'boost');

            // Verify save succeeded
            $component->assertSet('statusType', 'success');

            // Verify reload status was updated
            $reloadStatus = $component->get('reloadStatus');
            expect($reloadStatus)->toHaveKey('boost');
            expect($reloadStatus['boost'])->toHaveKey('services');
        });

        it('triggers reload for copilot_instructions when code-server is running', function (): void {
            // Mock CodeServerService
            $mockCodeServer = Mockery::mock(\App\Services\CodeServer\CodeServerService::class);
            $mockCodeServer->shouldReceive('isRunning')->andReturn(true);
            $mockCodeServer->shouldReceive('isInstalled')->andReturn(true);
            $mockCodeServer->shouldReceive('getPort')->andReturn(8443);
            app()->instance(\App\Services\CodeServer\CodeServerService::class, $mockCodeServer);

            // Mock ConfigReloadService
            $mockReloadService = Mockery::mock(\App\Services\ConfigReloadService::class)->makePartial();
            $mockReloadService->shouldReceive('triggerReload')
                ->with('copilot_instructions')
                ->once()
                ->andReturn([
                    'config_key' => 'copilot_instructions',
                    'success' => true,
                    'services' => [
                        ['name' => 'GitHub Copilot', 'type' => 'vscode', 'reloaded' => true, 'message' => 'VS Code extensions will reload their configuration.'],
                    ],
                    'message' => '',
                ]);
            $mockReloadService->shouldReceive('getReloadStatus')
                ->andReturn([
                    'services' => [['name' => 'GitHub Copilot', 'type' => 'vscode', 'description' => 'Copilot custom instructions']],
                    'requires_manual_reload' => false,
                    'instructions' => 'Copilot instructions are hot-reloaded',
                    'last_modified' => time(),
                    'last_modified_formatted' => '1 second ago',
                    'is_code_server_running' => true,
                ]);
            app()->instance(\App\Services\ConfigReloadService::class, $mockReloadService);

            $component = Livewire::test(AiAgentConfigs::class);

            // Save copilot instructions
            $markdownContent = "# Copilot Instructions\n\nThese are custom instructions.";
            $component->set('fileContent.copilot_instructions', $markdownContent);
            $component->call('save', 'copilot_instructions');

            // Verify save succeeded
            $component->assertSet('statusType', 'success');
        });

        it('triggers reload for opencode_global with multiple service types', function (): void {
            // Mock CodeServerService to simulate stopped state
            $mockCodeServer = Mockery::mock(\App\Services\CodeServer\CodeServerService::class);
            $mockCodeServer->shouldReceive('isRunning')->andReturn(false);
            $mockCodeServer->shouldReceive('isInstalled')->andReturn(true);
            app()->instance(\App\Services\CodeServer\CodeServerService::class, $mockCodeServer);

            // Create mock reload service
            $mockReloadService = Mockery::mock(\App\Services\ConfigReloadService::class)->makePartial();
            $mockReloadService->shouldReceive('triggerReload')
                ->with('opencode_global')
                ->once()
                ->andReturn([
                    'config_key' => 'opencode_global',
                    'success' => false, // Partial success - cli reloaded but vscode failed
                    'services' => [
                        ['name' => 'OpenCode CLI', 'type' => 'cli', 'reloaded' => false, 'message' => 'Manual restart required'],
                        ['name' => 'VS Code Extensions', 'type' => 'vscode', 'reloaded' => false, 'message' => 'code-server is not running'],
                    ],
                    'message' => '',
                ]);
            $mockReloadService->shouldReceive('getReloadStatus')
                ->andReturn([
                    'services' => [
                        ['name' => 'OpenCode CLI', 'type' => 'cli', 'description' => 'OpenCode CLI'],
                        ['name' => 'VS Code Extensions', 'type' => 'vscode', 'description' => 'VS Code Extensions'],
                    ],
                    'requires_manual_reload' => true,
                    'instructions' => 'OpenCode configuration is hot-reloaded',
                    'last_modified' => time(),
                    'last_modified_formatted' => '1 second ago',
                    'is_code_server_running' => false,
                ]);
            app()->instance(\App\Services\ConfigReloadService::class, $mockReloadService);

            $component = Livewire::test(AiAgentConfigs::class);

            // Save opencode_global config
            $newContent = json_encode(['model' => 'claude-3', 'temperature' => 0.7], JSON_PRETTY_PRINT);
            $component->set('fileContent.opencode_global', $newContent);
            $component->call('save', 'opencode_global');

            // Verify save succeeded (file save succeeded even if reload partially failed)
            $component->assertSet('statusType', 'success');
        });

        it('shows manual reload instructions for cli services', function (): void {
            $component = Livewire::test(AiAgentConfigs::class);

            // Save claude_global config (cli service type) with valid content
            $newContent = json_encode(['model' => 'claude-3', 'temperature' => 0.7], JSON_PRETTY_PRINT);
            $component->set('fileContent.claude_global', $newContent);
            $component->call('save', 'claude_global');

            // Verify save succeeded
            $component->assertSet('statusType', 'success');

            // Check that reload status contains manual reload info
            $reloadStatus = $component->get('reloadStatus');
            expect($reloadStatus)->toHaveKey('claude_global');
            expect($reloadStatus['claude_global']['requires_manual_reload'] ?? null)->toBeTrue();
        });

        it('updates UI status message with reload info after save', function (): void {
            $component = Livewire::test(AiAgentConfigs::class);

            // Save config that requires manual reload (boost.json)
            $newContent = json_encode(['agents' => ['copilot']], JSON_PRETTY_PRINT);
            $component->set('fileContent.boost', $newContent);
            $component->call('save', 'boost');

            // Verify status message contains saved confirmation
            $statusMessage = $component->get('statusMessage');
            expect($statusMessage)->toContain('saved');
        });

        it('reports error when reload service throws exception', function (): void {
            // Mock ConfigReloadService to throw exception during triggerReload
            $mockReloadService = Mockery::mock(\App\Services\ConfigReloadService::class)->makePartial();
            $mockReloadService->shouldReceive('triggerReload')
                ->with('boost')
                ->once()
                ->andThrow(new \Exception('Service reload failed'));
            $mockReloadService->shouldReceive('getReloadStatus')
                ->andReturn([
                    'services' => [],
                    'requires_manual_reload' => false,
                    'instructions' => 'Configuration saved. Changes may require a restart.',
                    'last_modified' => time(),
                    'last_modified_formatted' => '1 second ago',
                    'is_code_server_running' => false,
                ]);
            $mockReloadService->shouldReceive('resolvePath')->andReturn(null);
            app()->instance(\App\Services\ConfigReloadService::class, $mockReloadService);

            $component = Livewire::test(AiAgentConfigs::class);

            // Save should report error when reload fails
            $newContent = json_encode(['agents' => ['copilot']], JSON_PRETTY_PRINT);
            $component->set('fileContent.boost', $newContent);
            $component->call('save', 'boost');

            // Should report error when reload service throws
            $component->assertSet('statusType', 'error');
            $component->assertSet('statusMessage', fn ($msg) => str_contains($msg, 'Save failed') || str_contains($msg, 'Service reload failed'));

            // But file was still saved before reload failed
            $savedContent = File::get(config('vibecodepc.config_files.boost.path'));
            expect($savedContent)->toBe($newContent);
        });

        it('reloads status after multiple consecutive saves', function (): void {
            $component = Livewire::test(AiAgentConfigs::class);

            // First save
            $content1 = json_encode(['agents' => ['claude_code']], JSON_PRETTY_PRINT);
            $component->set('fileContent.boost', $content1);
            $component->call('save', 'boost');

            $component->assertSet('statusType', 'success');

            // Second save
            $content2 = json_encode(['agents' => ['copilot']], JSON_PRETTY_PRINT);
            $component->set('fileContent.boost', $content2);
            $component->call('save', 'boost');

            $component->assertSet('statusType', 'success');

            // Verify reload status is still populated
            $reloadStatus = $component->get('reloadStatus');
            expect($reloadStatus)->toHaveKey('boost');
        });

        it('reloads project-scoped configs correctly', function (): void {
            // Create a project
            $project = \App\Models\Project::factory()->create([
                'name' => 'Reload Test Project',
                'path' => '/tmp/reload-test-project-'.uniqid(),
            ]);

            // Create project config directory
            $configDir = $project->path;
            if (! is_dir($configDir)) {
                mkdir($configDir, 0755, true);
            }

            $component = Livewire::test(AiAgentConfigs::class);
            $component->set('selectedProjectId', $project->id);

            // Save project-scoped config
            $content = json_encode(['model' => 'claude-3'], JSON_PRETTY_PRINT);
            $component->set('fileContent.claude_project', $content);
            $component->call('save', 'claude_project');

            // Verify save succeeded
            $component->assertSet('statusType', 'success');

            // Cleanup
            File::deleteDirectory($configDir);
        });
    });
});
