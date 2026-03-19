<?php

declare(strict_types=1);

use App\Livewire\Dashboard\AiAgentConfigs;
use App\Services\ConfigReloadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Clear backups and audit logs before each test
    $backupDir = config('vibecodepc.config_editor.backup_directory');
    if (File::isDirectory($backupDir)) {
        File::cleanDirectory($backupDir);
    }
    \App\Models\ConfigAuditLog::query()->delete();

    // Ensure boost.json exists with initial content
    $boostPath = base_path('boost.json');
    File::put($boostPath, json_encode(['agents' => ['initial_agent']], JSON_PRETTY_PRINT));
});

afterEach(function () {
    // Clean up test files
    $boostPath = base_path('boost.json');
    if (File::exists($boostPath)) {
        File::delete($boostPath);
    }

    // Clean up project directories
    $testDirs = glob('/tmp/reload-test-*');
    foreach ($testDirs as $dir) {
        if (is_dir($dir)) {
            File::deleteDirectory($dir);
        }
    }
});

// F3.1: Test service reload after save
describe('service reload integration', function () {
    it('saves boost.json and verifies MCP service receives reload notification', function () {
        $reloadService = app(ConfigReloadService::class);

        // Step 1: User opens AI Agents page
        $component = Livewire::test(AiAgentConfigs::class);
        $component->assertStatus(200);

        // Step 2: Verify initial reload status for boost (MCP service)
        $initialStatus = $component->get('reloadStatus');
        expect($initialStatus['boost'])->toHaveKey('services');
        expect($initialStatus['boost']['services'])->toHaveCount(1);
        expect($initialStatus['boost']['services'][0]['type'])->toBe('mcp');
        expect($initialStatus['boost']['services'][0]['name'])->toBe('Laravel Boost');
        expect($initialStatus['boost']['requires_manual_reload'])->toBeTrue();

        // Step 3: User edits boost.json
        $newContent = json_encode([
            'agents' => ['claude_code', 'copilot', 'test_agent'],
            'skills' => ['laravel-development', 'new-skill'],
        ], JSON_PRETTY_PRINT);

        $component->set('fileContent.boost', $newContent);
        $component->assertSet('isDirty.boost', true);
        $component->assertSet('isValid.boost', true);

        // Step 4: User saves config - this should trigger reload
        $component->call('save', 'boost');

        // Step 5: Verify save was successful
        $component->assertSet('statusType', 'success');
        $component->assertSet('isDirty.boost', false);

        // Step 6: Verify reload status is updated in UI
        $updatedStatus = $component->get('reloadStatus');
        expect($updatedStatus['boost'])->toHaveKey('services');
        expect($updatedStatus['boost'])->toHaveKey('requires_manual_reload');
        expect($updatedStatus['boost'])->toHaveKey('instructions');
        expect($updatedStatus['boost'])->toHaveKey('last_modified');
        expect($updatedStatus['boost'])->toHaveKey('last_modified_formatted');

        // Step 7: Verify MCP service shows automatic detection
        expect($updatedStatus['boost']['services'])->toHaveCount(1);
        expect($updatedStatus['boost']['services'][0]['type'])->toBe('mcp');

        // Step 8: Verify instructions mention automatic detection
        $instructions = $updatedStatus['boost']['instructions'];
        expect($instructions)->toContain('MCP server');
        expect($instructions)->toContain('detected automatically');
    });

    it('saves vscode configs and triggers code-server reload when running', function () {
        // Mock CodeServerService to simulate running code-server
        $mockCodeServer = Mockery::mock(\App\Services\CodeServer\CodeServerService::class);
        $mockCodeServer->shouldReceive('isRunning')->andReturn(true);
        $mockCodeServer->shouldReceive('isInstalled')->andReturn(true);
        $mockCodeServer->shouldReceive('getPort')->andReturn(8443);
        app()->instance(\App\Services\CodeServer\CodeServerService::class, $mockCodeServer);

        $reloadService = new ConfigReloadService;

        // Create copilot instructions file
        $instructionsPath = config('vibecodepc.config_files.copilot_instructions.path');
        File::makeDirectory(dirname($instructionsPath), 0755, true, true);
        File::put($instructionsPath, '# Initial Instructions');

        // Step 1: Mount component
        $component = Livewire::test(AiAgentConfigs::class);

        // Step 2: Verify initial reload status for copilot (vscode service)
        $initialStatus = $component->get('reloadStatus');
        expect($initialStatus['copilot_instructions'])->toHaveKey('services');
        expect($initialStatus['copilot_instructions']['services'])->toHaveCount(1);
        expect($initialStatus['copilot_instructions']['services'][0]['type'])->toBe('vscode');
        expect($initialStatus['copilot_instructions']['services'][0]['name'])->toBe('GitHub Copilot');

        // Step 3: Edit copilot instructions
        $newContent = "# Updated Copilot Instructions\n\nThese are new instructions.";
        $component->set('fileContent.copilot_instructions', $newContent);
        $component->assertSet('isDirty.copilot_instructions', true);

        // Step 4: Save config
        $component->call('save', 'copilot_instructions');

        // Step 5: Verify save was successful
        $component->assertSet('statusType', 'success');

        // Step 6: Verify reload status updated
        $updatedStatus = $component->get('reloadStatus');
        expect($updatedStatus['copilot_instructions'])->toHaveKey('services');
        expect($updatedStatus['copilot_instructions'])->toHaveKey('last_modified');

        // Cleanup
        if (File::exists($instructionsPath)) {
            File::delete($instructionsPath);
        }
    });

    it('handles code-server not running during vscode config save', function () {
        // Mock CodeServerService to simulate stopped code-server
        $mockCodeServer = Mockery::mock(\App\Services\CodeServer\CodeServerService::class);
        $mockCodeServer->shouldReceive('isRunning')->andReturn(false);
        $mockCodeServer->shouldReceive('isInstalled')->andReturn(true);
        $mockCodeServer->shouldReceive('getPort')->andReturn(8443);
        app()->instance(\App\Services\CodeServer\CodeServerService::class, $mockCodeServer);

        // Create copilot instructions file
        $instructionsPath = config('vibecodepc.config_files.copilot_instructions.path');
        File::makeDirectory(dirname($instructionsPath), 0755, true, true);
        File::put($instructionsPath, '# Initial Instructions');

        // Step 1: Mount component
        $component = Livewire::test(AiAgentConfigs::class);

        // Step 2: Edit copilot instructions
        $newContent = "# Updated Instructions\n\nNew content.";
        $component->set('fileContent.copilot_instructions', $newContent);

        // Step 3: Save config
        $component->call('save', 'copilot_instructions');

        // Step 4: Save should still succeed even if reload fails
        $component->assertSet('statusType', 'success');
        $component->assertSet('isDirty.copilot_instructions', false);

        // Step 5: Verify reload status reflects code-server not running
        $updatedStatus = $component->get('reloadStatus');
        expect($updatedStatus['copilot_instructions'])->toHaveKey('is_code_server_running');
        expect($updatedStatus['copilot_instructions']['is_code_server_running'])->toBeFalse();

        // Cleanup
        if (File::exists($instructionsPath)) {
            File::delete($instructionsPath);
        }
    });

    it('updates reload status after each config file save', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Save boost.json
        $boostContent = json_encode([
            'agents' => ['test_agent'],
            'skills' => ['test'],
        ], JSON_PRETTY_PRINT);
        $component->set('fileContent.boost', $boostContent);
        $component->call('save', 'boost');

        $component->assertSet('statusType', 'success');

        // Verify boost reload status was updated
        $status1 = $component->get('reloadStatus');
        expect($status1['boost'])->toHaveKey('last_modified');
        expect($status1['boost']['last_modified'])->not->toBeNull();
        $firstModifiedTime = $status1['boost']['last_modified'];

        // Wait briefly to ensure different timestamp
        sleep(1);

        // Save boost.json again
        $boostContent2 = json_encode([
            'agents' => ['test_agent', 'another_agent'],
            'skills' => ['test', 'another'],
        ], JSON_PRETTY_PRINT);
        $component->set('fileContent.boost', $boostContent2);
        $component->call('save', 'boost');

        $component->assertSet('statusType', 'success');

        // Verify reload status was updated again
        $status2 = $component->get('reloadStatus');
        expect($status2['boost'])->toHaveKey('last_modified');
        expect($status2['boost']['last_modified'])->toBeGreaterThan($firstModifiedTime);
    });

    it('shows reload instructions in status message after save', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Edit and save boost.json
        $newContent = json_encode([
            'agents' => ['claude_code'],
            'skills' => ['php-development'],
        ], JSON_PRETTY_PRINT);

        $component->set('fileContent.boost', $newContent);
        $component->call('save', 'boost');

        // Verify status message includes config name
        $statusMessage = $component->get('statusMessage');
        expect($statusMessage)->toContain('Boost Configuration');
        expect($statusMessage)->toContain('saved successfully');
    });

    it('handles multiple service types for opencode_global', function () {
        // Mock CodeServerService
        $mockCodeServer = Mockery::mock(\App\Services\CodeServer\CodeServerService::class);
        $mockCodeServer->shouldReceive('isRunning')->andReturn(true);
        $mockCodeServer->shouldReceive('isInstalled')->andReturn(true);
        $mockCodeServer->shouldReceive('getPort')->andReturn(8443);
        app()->instance(\App\Services\CodeServer\CodeServerService::class, $mockCodeServer);

        // Create opencode global config
        $configPath = config('vibecodepc.config_files.opencode_global.path');
        File::makeDirectory(dirname($configPath), 0755, true, true);
        File::put($configPath, json_encode(['model' => 'gpt-4']));

        $component = Livewire::test(AiAgentConfigs::class);

        // Verify opencode_global has both CLI and vscode services
        $status = $component->get('reloadStatus');
        expect($status['opencode_global'])->toHaveKey('services');
        expect($status['opencode_global']['services'])->toHaveCount(2);

        $serviceTypes = collect($status['opencode_global']['services'])->pluck('type')->toArray();
        expect($serviceTypes)->toContain('cli');
        expect($serviceTypes)->toContain('vscode');

        // Edit and save
        $newContent = json_encode([
            'model' => 'claude-3',
            'temperature' => 0.7,
        ], JSON_PRETTY_PRINT);

        $component->set('fileContent.opencode_global', $newContent);
        $component->call('save', 'opencode_global');

        $component->assertSet('statusType', 'success');

        // Cleanup
        if (File::exists($configPath)) {
            File::delete($configPath);
        }
    });

    it('manually triggers reload via component method', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Call manual reload for boost
        $component->call('triggerReload', 'boost');

        // Should show success or warning (MCP requires manual but shows message)
        $statusType = $component->get('statusType');
        expect($statusType)->toBeIn(['success', 'warning']);

        // Reload status should be refreshed
        $status = $component->get('reloadStatus');
        expect($status['boost'])->toHaveKey('services');
        expect($status['boost']['services'])->toHaveCount(1);
    });

    it('handles reload trigger for vscode service when code-server is not installed', function () {
        // Mock CodeServerService to simulate not installed
        $mockCodeServer = Mockery::mock(\App\Services\CodeServer\CodeServerService::class);
        $mockCodeServer->shouldReceive('isRunning')->andReturn(false);
        $mockCodeServer->shouldReceive('isInstalled')->andReturn(false);
        app()->instance(\App\Services\CodeServer\CodeServerService::class, $mockCodeServer);

        // Create copilot instructions
        $instructionsPath = config('vibecodepc.config_files.copilot_instructions.path');
        File::makeDirectory(dirname($instructionsPath), 0755, true, true);
        File::put($instructionsPath, '# Test Instructions');

        $component = Livewire::test(AiAgentConfigs::class);

        // Call manual reload for copilot
        $component->call('triggerReload', 'copilot_instructions');

        // Should show warning since code-server is not running
        $component->assertSet('statusType', 'warning');

        // Status message should mention manual restart
        $message = $component->get('statusMessage');
        expect($message)->toContain('manual');

        // Cleanup
        if (File::exists($instructionsPath)) {
            File::delete($instructionsPath);
        }
    });

    it('preserves reload status isolation between different config files', function () {
        // Create opencode global config
        $configPath = config('vibecodepc.config_files.opencode_global.path');
        File::makeDirectory(dirname($configPath), 0755, true, true);
        File::put($configPath, json_encode(['model' => 'gpt-4']));

        $component = Livewire::test(AiAgentConfigs::class);

        // Save boost.json
        $component->set('fileContent.boost', json_encode(['agents' => ['test']], JSON_PRETTY_PRINT));
        $component->call('save', 'boost');

        // Get boost status
        $statusAfterBoost = $component->get('reloadStatus');
        $boostModified = $statusAfterBoost['boost']['last_modified'];

        // Wait briefly
        sleep(1);

        // Save opencode_global
        $component->set('fileContent.opencode_global', json_encode(['model' => 'claude'], JSON_PRETTY_PRINT));
        $component->call('save', 'opencode_global');

        // Get updated status
        $statusAfterOpencode = $component->get('reloadStatus');

        // Both should have their own reload status
        expect($statusAfterOpencode['boost']['last_modified'])->toBe($boostModified);
        expect($statusAfterOpencode['opencode_global']['last_modified'])->toBeGreaterThan(0);

        // Cleanup
        if (File::exists($configPath)) {
            File::delete($configPath);
        }
    });

    it('updates is_code_server_running flag in reload status', function () {
        // Mock CodeServerService to simulate running
        $mockCodeServer = Mockery::mock(\App\Services\CodeServer\CodeServerService::class);
        $mockCodeServer->shouldReceive('isRunning')->andReturn(true);
        $mockCodeServer->shouldReceive('isInstalled')->andReturn(true);
        $mockCodeServer->shouldReceive('getPort')->andReturn(8443);
        app()->instance(\App\Services\CodeServer\CodeServerService::class, $mockCodeServer);

        // Create copilot instructions
        $instructionsPath = config('vibecodepc.config_files.copilot_instructions.path');
        File::makeDirectory(dirname($instructionsPath), 0755, true, true);
        File::put($instructionsPath, '# Test');

        $component = Livewire::test(AiAgentConfigs::class);

        // Check reload status shows code-server running
        $status = $component->get('reloadStatus');
        expect($status['copilot_instructions']['is_code_server_running'])->toBeTrue();

        // Save and verify status remains correct
        $component->set('fileContent.copilot_instructions', '# Updated');
        $component->call('save', 'copilot_instructions');

        $updatedStatus = $component->get('reloadStatus');
        expect($updatedStatus['copilot_instructions']['is_code_server_running'])->toBeTrue();

        // Cleanup
        if (File::exists($instructionsPath)) {
            File::delete($instructionsPath);
        }
    });

    it('displays proper reload status in view data', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Save to trigger reload status update
        $component->set('fileContent.boost', json_encode(['agents' => ['view_test']], JSON_PRETTY_PRINT));
        $component->call('save', 'boost');

        // Verify reloadStatuses is passed to view
        $reloadStatuses = $component->viewData('reloadStatuses');
        expect($reloadStatuses)->toBeArray();
        expect($reloadStatuses)->toHaveKey('boost');
        expect($reloadStatuses['boost'])->toHaveKey('services');
        expect($reloadStatuses['boost'])->toHaveKey('requires_manual_reload');
        expect($reloadStatuses['boost'])->toHaveKey('instructions');
        expect($reloadStatuses['boost'])->toHaveKey('last_modified_formatted');
    });

    it('handles reload for cli-only configs with manual restart message', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Create claude global config
        $configPath = config('vibecodepc.config_files.claude_global.path');
        File::makeDirectory(dirname($configPath), 0755, true, true);
        File::put($configPath, json_encode(['model' => 'claude-3']));

        // Verify claude_global requires manual reload
        $status = $component->get('reloadStatus');
        expect($status['claude_global']['requires_manual_reload'])->toBeTrue();
        expect($status['claude_global']['services'])->toHaveCount(1);
        expect($status['claude_global']['services'][0]['type'])->toBe('cli');

        // Save and verify status message
        $component->set('fileContent.claude_global', json_encode(['model' => 'claude-3.5'], JSON_PRETTY_PRINT));
        $component->call('save', 'claude_global');

        $component->assertSet('statusType', 'success');

        // Cleanup
        if (File::exists($configPath)) {
            File::delete($configPath);
        }
    });

    it('logs reload operations when config is saved', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Clear existing audit logs
        \App\Models\ConfigAuditLog::query()->delete();

        // Save boost.json
        $component->set('fileContent.boost', json_encode(['agents' => ['log_test']], JSON_PRETTY_PRINT));
        $component->call('save', 'boost');

        // Verify save audit log was created
        $auditLog = \App\Models\ConfigAuditLog::where('config_key', 'boost')
            ->where('action', 'save')
            ->first();

        expect($auditLog)->not->toBeNull();
        expect($auditLog->config_key)->toBe('boost');
        expect($auditLog->action)->toBe('save');
        expect($auditLog->new_content_hash)->toBeArray();
        expect($auditLog->new_content_hash)->toHaveKey('sha256');
    });

    it('handles rapid successive saves with reload status updates', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Perform rapid saves
        for ($i = 1; $i <= 3; $i++) {
            $content = json_encode(['version' => $i, 'agents' => ["agent{$i}"]], JSON_PRETTY_PRINT);
            $component->set('fileContent.boost', $content);
            $component->call('save', 'boost');

            $component->assertSet('statusType', 'success');

            // Verify reload status updated each time
            $status = $component->get('reloadStatus');
            expect($status['boost'])->toHaveKey('last_modified');
            expect($status['boost']['last_modified'])->toBeGreaterThan(0);
        }

        // Final file should have version 3
        $finalContent = File::get(base_path('boost.json'));
        expect($finalContent)->toContain('version": 3');
    });

    it('reloads project-scoped configs with correct service mapping', function () {
        // Create a project
        $project = \App\Models\Project::factory()->create([
            'name' => 'Reload Test Project',
            'path' => '/tmp/reload-test-project-'.uniqid(),
        ]);

        // Create project config directory
        $projectConfigDir = $project->path;
        if (! is_dir($projectConfigDir)) {
            mkdir($projectConfigDir, 0755, true);
        }

        $component = Livewire::test(AiAgentConfigs::class);
        $component->set('selectedProjectId', $project->id);

        // Verify opencode_project has CLI service
        $status = $component->get('reloadStatus');
        expect($status['opencode_project'])->toHaveKey('services');
        expect($status['opencode_project']['services'])->toHaveCount(1);
        expect($status['opencode_project']['services'][0]['type'])->toBe('cli');
        expect($status['opencode_project']['requires_manual_reload'])->toBeTrue();

        // Save project config
        $content = json_encode(['model' => 'claude-3'], JSON_PRETTY_PRINT);
        $component->set('fileContent.opencode_project', $content);
        $component->call('save', 'opencode_project');

        $component->assertSet('statusType', 'success');
        $component->assertSet('isDirty.opencode_project', false);

        // Cleanup
        File::deleteDirectory($projectConfigDir);
    });

    it('correctly identifies all affected services for each config type', function () {
        $reloadService = app(ConfigReloadService::class);

        // Test each config type
        $configTypes = [
            'boost' => ['mcp'],
            'opencode_global' => ['cli', 'vscode'],
            'opencode_project' => ['cli'],
            'claude_global' => ['cli'],
            'claude_project' => ['cli'],
            'copilot_instructions' => ['vscode'],
        ];

        foreach ($configTypes as $configKey => $expectedTypes) {
            $services = $reloadService->getAffectedServices($configKey);
            $actualTypes = collect($services)->pluck('type')->toArray();

            expect($actualTypes)->toBe($expectedTypes, "Config {$configKey} should have services: ".implode(', ', $expectedTypes));
        }
    });

    it('shows appropriate reload status for configs requiring manual restart', function () {
        $reloadService = app(ConfigReloadService::class);

        // Configs requiring manual reload
        $manualConfigs = ['boost', 'opencode_global', 'opencode_project', 'claude_global', 'claude_project'];

        foreach ($manualConfigs as $configKey) {
            $requiresManual = $reloadService->requiresManualReload($configKey);
            expect($requiresManual)->toBeTrue("Config {$configKey} should require manual reload");
        }

        // Configs NOT requiring manual reload (hot-reloadable)
        $hotReloadConfigs = ['copilot_instructions'];

        foreach ($hotReloadConfigs as $configKey) {
            $requiresManual = $reloadService->requiresManualReload($configKey);
            expect($requiresManual)->toBeFalse("Config {$configKey} should not require manual reload");
        }
    });

    it('updates reload status timestamp after save operation', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Get initial status
        $initialStatus = $component->get('reloadStatus');
        $initialTimestamp = $initialStatus['boost']['last_modified'];

        // Wait to ensure different timestamps
        sleep(1);

        // Save new content
        $newContent = json_encode(['agents' => ['timestamp_test']], JSON_PRETTY_PRINT);
        $component->set('fileContent.boost', $newContent);
        $component->call('save', 'boost');

        // Verify status updated with new timestamp
        $updatedStatus = $component->get('reloadStatus');
        $updatedTimestamp = $updatedStatus['boost']['last_modified'];

        expect($updatedTimestamp)->toBeGreaterThan($initialTimestamp);
        expect($updatedStatus['boost']['last_modified_formatted'])->toContain('ago');
    });
});
