<?php

declare(strict_types=1);

use App\Livewire\Dashboard\AiAgentConfigs;
use App\Services\ConfigFileService;
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
    $testDirs = glob('/tmp/config-workflow-test-*');
    foreach ($testDirs as $dir) {
        if (is_dir($dir)) {
            File::deleteDirectory($dir);
        }
    }
});

// F1.1: Test complete config editing workflow
describe('complete config editing workflow', function () {
    it('completes full editing workflow with file write, backup, audit log, and reload', function () {
        $configFileService = app(ConfigFileService::class);
        $reloadService = app(ConfigReloadService::class);

        // Step 1: User opens AI Agents page
        $component = Livewire::test(AiAgentConfigs::class);
        $component->assertStatus(200);
        $component->assertSee('AI Agent Configs');
        $component->assertSee('Boost Configuration');

        // Verify initial state
        $component->assertSet('activeTab', 'boost');
        $component->assertSet('isDirty.boost', false);
        $component->assertSet('isValid.boost', true);

        // Step 2: User switches to a project
        $project = \App\Models\Project::factory()->create([
            'name' => 'Workflow Test Project',
            'path' => '/tmp/config-workflow-test-'.uniqid(),
        ]);

        // Create project config directory
        $projectConfigDir = $project->path;
        if (! is_dir($projectConfigDir)) {
            mkdir($projectConfigDir, 0755, true);
        }

        // Switch to project
        $component->set('selectedProjectId', $project->id);
        $component->assertSet('selectedProjectId', $project->id);

        // Configs should reload for project context
        $component->assertSet('fileContent.opencode_project', '');

        // Step 3: User edits config (opencode_project for this test)
        $newConfigContent = json_encode([
            'model' => 'claude-3-opus',
            'temperature' => 0.7,
            'max_tokens' => 4096,
        ], JSON_PRETTY_PRINT);

        $component->set('fileContent.opencode_project', $newConfigContent);
        $component->assertSet('isDirty.opencode_project', true);
        $component->assertSet('isValid.opencode_project', true);

        // Step 4: User saves config
        // Verify no backups exist yet for this project config
        $backupsBefore = $configFileService->listBackups('opencode_project', $project);
        expect($backupsBefore)->toHaveCount(0);

        // Verify no audit logs exist yet
        $auditLogsBefore = \App\Models\ConfigAuditLog::where('config_key', 'opencode_project')->count();
        expect($auditLogsBefore)->toBe(0);

        // First, create initial content so there's a file to backup on second save
        $initialContent = json_encode(['initial' => 'config'], JSON_PRETTY_PRINT);
        File::put($projectConfigDir.'/opencode.json', $initialContent);

        // Load component again to pick up the existing file
        $component = Livewire::test(AiAgentConfigs::class);
        $component->set('selectedProjectId', $project->id);

        // Step 4: User saves config (this creates a backup of existing file)
        $component->set('fileContent.opencode_project', $newConfigContent);
        $component->call('save', 'opencode_project');

        // Step 5: Verify all outcomes

        // 5a: File was written
        $configPath = $projectConfigDir.'/opencode.json';
        expect(File::exists($configPath))->toBeTrue();
        $savedContent = File::get($configPath);
        expect($savedContent)->toBe($newConfigContent);
        expect($savedContent)->toContain('claude-3-opus');
        expect($savedContent)->toContain('temperature');

        // 5b: Backup was created (because file existed before save)
        $backupsAfter = $configFileService->listBackups('opencode_project', $project);
        expect($backupsAfter)->toHaveCount(1);
        $backupPath = $backupsAfter[0]['path'];
        expect(File::exists($backupPath))->toBeTrue();

        // Verify backup filename contains project ID suffix
        expect($backupPath)->toContain('project-'.$project->id);

        // 5c: Audit log entry created
        $auditLog = \App\Models\ConfigAuditLog::where('config_key', 'opencode_project')
            ->where('action', 'save')
            ->first();

        expect($auditLog)->not->toBeNull();
        expect($auditLog->config_key)->toBe('opencode_project');
        expect($auditLog->action)->toBe('save');
        expect($auditLog->project_id)->toBe($project->id);
        expect($auditLog->new_content_hash)->toBeArray();
        expect($auditLog->new_content_hash)->toHaveKey('sha256');

        // 5d: Component state updated correctly
        $component->assertSet('statusType', 'success');
        $component->assertSet('isDirty.opencode_project', false);
        $component->assertSet('fileExists.opencode_project', true);

        // Status message should indicate success
        $statusMessage = $component->get('statusMessage');
        expect($statusMessage)->toContain('saved');

        // Cleanup
        File::deleteDirectory($projectConfigDir);
    });

    it('completes workflow for global config (boost.json)', function () {
        $configFileService = app(ConfigFileService::class);

        // Step 1: Open page
        $component = Livewire::test(AiAgentConfigs::class);

        // Step 2: Edit global boost.json (no project switch needed)
        $newContent = json_encode([
            'agents' => ['claude_code', 'copilot', 'custom_agent'],
            'skills' => ['laravel-development', 'php-development', 'testing'],
        ], JSON_PRETTY_PRINT);

        $component->set('fileContent.boost', $newContent);
        $component->assertSet('isDirty.boost', true);

        // Step 3: Save
        $component->call('save', 'boost');

        // Step 4: Verify outcomes

        // File written
        $savedContent = File::get(base_path('boost.json'));
        expect($savedContent)->toBe($newContent);
        expect($savedContent)->toContain('custom_agent');
        expect($savedContent)->toContain('testing');

        // Backup created
        $backups = $configFileService->listBackups('boost');
        expect($backups)->toHaveCount(1);

        // Audit log created
        $auditLog = \App\Models\ConfigAuditLog::where('config_key', 'boost')
            ->where('action', 'save')
            ->first();
        expect($auditLog)->not->toBeNull();
        expect($auditLog->project_id)->toBeNull(); // Global config has no project

        // Component state
        $component->assertSet('statusType', 'success');
        $component->assertSet('isDirty.boost', false);
    });

    it('handles multiple saves creating multiple backups', function () {
        $configFileService = app(ConfigFileService::class);
        $project = \App\Models\Project::factory()->create([
            'name' => 'Multi-save Project',
            'path' => '/tmp/config-workflow-test-'.uniqid(),
        ]);

        $projectConfigDir = $project->path;
        if (! is_dir($projectConfigDir)) {
            mkdir($projectConfigDir, 0755, true);
        }

        // Create initial file so first save creates a backup
        File::put($projectConfigDir.'/opencode.json', json_encode(['version' => 0], JSON_PRETTY_PRINT));

        $component = Livewire::test(AiAgentConfigs::class);
        $component->set('selectedProjectId', $project->id);

        // Save version 1 (creates backup of version 0)
        $content1 = json_encode(['version' => 1, 'data' => 'first'], JSON_PRETTY_PRINT);
        $component->set('fileContent.opencode_project', $content1);
        $component->call('save', 'opencode_project');

        // Save version 2 (creates backup of version 1)
        $content2 = json_encode(['version' => 2, 'data' => 'second'], JSON_PRETTY_PRINT);
        $component->set('fileContent.opencode_project', $content2);
        $component->call('save', 'opencode_project');

        // Save version 3 (creates backup of version 2)
        $content3 = json_encode(['version' => 3, 'data' => 'third'], JSON_PRETTY_PRINT);
        $component->set('fileContent.opencode_project', $content3);
        $component->call('save', 'opencode_project');

        // Should have 3 backups (versions 0, 1, and 2)
        $backups = $configFileService->listBackups('opencode_project', $project);
        expect($backups)->toHaveCount(3);

        // Backups should be sorted newest first
        expect($backups[0]['created_at'])->toBeGreaterThanOrEqual($backups[1]['created_at']);

        // Final file should contain version 3
        $finalContent = File::get($projectConfigDir.'/opencode.json');
        expect($finalContent)->toContain('version": 3');

        // Should have 3 audit log entries
        $auditLogCount = \App\Models\ConfigAuditLog::where('config_key', 'opencode_project')
            ->where('action', 'save')
            ->count();
        expect($auditLogCount)->toBe(3);

        // Cleanup
        File::deleteDirectory($projectConfigDir);
    });

    it('updates reload status after save', function () {
        $configFileService = app(ConfigFileService::class);

        $component = Livewire::test(AiAgentConfigs::class);

        // Initial reload status should be populated
        $initialReloadStatus = $component->get('reloadStatus');
        expect($initialReloadStatus)->toHaveKey('boost');

        // Edit and save
        $newContent = json_encode([
            'agents' => ['claude_code'],
            'skills' => ['php-development'],
        ], JSON_PRETTY_PRINT);

        $component->set('fileContent.boost', $newContent);
        $component->call('save', 'boost');

        // Reload status should be updated
        $updatedReloadStatus = $component->get('reloadStatus');
        expect($updatedReloadStatus)->toHaveKey('boost');
        expect($updatedReloadStatus['boost'])->toHaveKey('last_modified_formatted');

        // File should have new modification time
        $modTime = File::lastModified(base_path('boost.json'));
        expect($modTime)->toBeGreaterThan(0);
    });

    it('preserves config isolation between global and project contexts', function () {
        $configFileService = app(ConfigFileService::class);

        // Create two projects
        $project1 = \App\Models\Project::factory()->create([
            'name' => 'Project One',
            'path' => '/tmp/config-workflow-test-'.uniqid(),
        ]);

        $project2 = \App\Models\Project::factory()->create([
            'name' => 'Project Two',
            'path' => '/tmp/config-workflow-test-'.uniqid(),
        ]);

        // Create directories and initial files
        foreach ([$project1, $project2] as $project) {
            if (! is_dir($project->path)) {
                mkdir($project->path, 0755, true);
            }
            // Create initial config file so save creates backups
            File::put($project->path.'/opencode.json', json_encode(['initial' => true], JSON_PRETTY_PRINT));
        }

        $component = Livewire::test(AiAgentConfigs::class);

        // Save to project 1
        $component->set('selectedProjectId', $project1->id);
        $content1 = json_encode(['project' => 'one'], JSON_PRETTY_PRINT);
        $component->set('fileContent.opencode_project', $content1);
        $component->call('save', 'opencode_project');

        // Create a new component instance for project 2 to properly reload
        $component = Livewire::test(AiAgentConfigs::class);
        $component->set('selectedProjectId', $project2->id);

        // Should have different content from initial file
        $contentProject2 = $component->get('fileContent.opencode_project');
        expect($contentProject2)->toContain('initial'); // Has initial content

        // Save different content to project 2
        $content2 = json_encode(['project' => 'two'], JSON_PRETTY_PRINT);
        $component->set('fileContent.opencode_project', $content2);
        $component->call('save', 'opencode_project');

        // Verify isolation
        $file1 = File::get($project1->path.'/opencode.json');
        $file2 = File::get($project2->path.'/opencode.json');

        expect($file1)->toContain('one');
        expect($file1)->not->toContain('two');
        expect($file2)->toContain('two');
        expect($file2)->not->toContain('one');

        // Backups should be isolated
        $backups1 = $configFileService->listBackups('opencode_project', $project1);
        $backups2 = $configFileService->listBackups('opencode_project', $project2);

        expect($backups1)->toHaveCount(1);
        expect($backups2)->toHaveCount(1);

        // Backup filenames should have different project suffixes
        expect($backups1[0]['path'])->toContain('project-'.$project1->id);
        expect($backups2[0]['path'])->toContain('project-'.$project2->id);

        // Cleanup
        File::deleteDirectory($project1->path);
        File::deleteDirectory($project2->path);
    });

    it('handles rapid successive edits and saves', function () {
        $configFileService = app(ConfigFileService::class);

        $component = Livewire::test(AiAgentConfigs::class);

        // Rapid succession of edits
        for ($i = 1; $i <= 5; $i++) {
            $content = json_encode(['iteration' => $i, 'data' => str_repeat('x', $i * 10)], JSON_PRETTY_PRINT);
            $component->set('fileContent.boost', $content);
            $component->call('save', 'boost');

            // Verify success after each save
            $component->assertSet('statusType', 'success');
        }

        // Should have 5 backups
        $backups = $configFileService->listBackups('boost');
        expect($backups)->toHaveCount(5);

        // Final file should have iteration 5
        $finalContent = File::get(base_path('boost.json'));
        expect($finalContent)->toContain('iteration": 5');

        // Should have 5 audit log entries
        $auditLogCount = \App\Models\ConfigAuditLog::where('config_key', 'boost')
            ->where('action', 'save')
            ->count();
        expect($auditLogCount)->toBe(5);
    });

    it('validates JSON structure before allowing save', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Try to save invalid JSON
        $invalidContent = '{invalid json structure';
        $component->set('fileContent.boost', $invalidContent);
        $component->assertSet('isValid.boost', false);

        // Save should fail
        $component->call('save', 'boost');
        $component->assertSet('statusType', 'error');
        $component->assertSet('statusMessage', fn ($msg) => str_contains($msg, 'Cannot save'));

        // File should not be modified
        $fileContent = File::get(base_path('boost.json'));
        expect($fileContent)->toContain('initial_agent'); // Original content
        expect($fileContent)->not->toContain('invalid json');

        // No new backup should be created
        $configFileService = app(ConfigFileService::class);
        $backups = $configFileService->listBackups('boost');
        expect($backups)->toHaveCount(0); // No saves succeeded
    });

    it('handles switching tabs during editing workflow', function () {
        $configFileService = app(ConfigFileService::class);

        $component = Livewire::test(AiAgentConfigs::class);

        // Start editing boost.json
        $boostContent = json_encode(['agents' => ['modified']], JSON_PRETTY_PRINT);
        $component->set('fileContent.boost', $boostContent);
        $component->assertSet('isDirty.boost', true);

        // Switch to opencode_global tab
        $component->set('activeTab', 'opencode_global');
        $component->assertSet('activeTab', 'opencode_global');

        // Switch back to boost
        $component->set('activeTab', 'boost');

        // Changes should be preserved
        $component->assertSet('fileContent.boost', $boostContent);
        $component->assertSet('isDirty.boost', true);

        // Save should still work
        $component->call('save', 'boost');
        $component->assertSet('statusType', 'success');

        // Verify file was saved
        $savedContent = File::get(base_path('boost.json'));
        expect($savedContent)->toContain('modified');
    });
});

// F1.2: Test backup and restore workflow
describe('backup and restore workflow', function () {
    it('creates config, edits multiple times, views backups, and restores specific version', function () {
        $configFileService = app(ConfigFileService::class);

        // Step 1: Create initial config (already done by beforeEach with 'initial_agent')
        // The beforeEach creates: ['agents' => ['initial_agent']]
        $initialContent = json_encode(['agents' => ['initial_agent']], JSON_PRETTY_PRINT);

        $component = Livewire::test(AiAgentConfigs::class);

        // Step 2: Edit and save multiple times (creating backups)
        // First save backs up initial_agent, writes version 1
        $content1 = json_encode([
            'version' => 'v1',
            'agents' => ['agent1'],
            'iteration' => 1,
        ], JSON_PRETTY_PRINT);
        $component->set('fileContent.boost', $content1);
        $component->call('save', 'boost');
        $component->assertSet('statusType', 'success');

        // Second save backs up version 1, writes version 2
        $content2 = json_encode([
            'version' => 'v2',
            'agents' => ['agent2'],
            'iteration' => 2,
        ], JSON_PRETTY_PRINT);
        $component->set('fileContent.boost', $content2);
        $component->call('save', 'boost');
        $component->assertSet('statusType', 'success');

        // Third save backs up version 2, writes version 3
        $content3 = json_encode([
            'version' => 'v3',
            'agents' => ['agent3'],
            'iteration' => 3,
        ], JSON_PRETTY_PRINT);
        $component->set('fileContent.boost', $content3);
        $component->call('save', 'boost');
        $component->assertSet('statusType', 'success');

        // Step 3: View backup list
        $backups = $configFileService->listBackups('boost');
        expect($backups)->toHaveCount(3); // 3 backups from 3 saves

        // Verify backup metadata
        foreach ($backups as $index => $backup) {
            expect($backup)->toHaveKey('path');
            expect($backup)->toHaveKey('filename');
            expect($backup)->toHaveKey('created_at');
            expect($backup)->toHaveKey('size');
            expect(File::exists($backup['path']))->toBeTrue();
            expect($backup['size'])->toBeGreaterThan(0);
        }

        // Backups should be sorted newest first by timestamp
        expect($backups[0]['created_at'])->toBeGreaterThanOrEqual($backups[1]['created_at']);
        expect($backups[1]['created_at'])->toBeGreaterThanOrEqual($backups[2]['created_at']);

        // Step 4: Find the backup containing version 1 content by reading backup contents
        // This is more robust than assuming sort order when timestamps are equal
        $backupContainingVersion1 = null;
        foreach ($backups as $backup) {
            $backupContent = File::get($backup['path']);
            if (str_contains($backupContent, '"version": "v1"')) {
                $backupContainingVersion1 = $backup;
                break;
            }
        }
        expect($backupContainingVersion1)->not->toBeNull('Could not find backup containing version 1');

        // Step 5: Restore the backup containing version 1
        $component->set('selectedBackup.boost', $backupContainingVersion1['path']);
        $component->call('restore', 'boost');
        $component->assertSet('statusType', 'success');
        $component->assertSet('statusMessage', fn ($msg) => str_contains($msg, 'restored'));

        // Step 6: Verify content restored to version 1
        $restoredContent = File::get(base_path('boost.json'));
        expect($restoredContent)->toBe($content1); // Should match version 1
        expect($restoredContent)->toContain('"version": "v1"');
        expect($restoredContent)->toContain('"agent1"');

        // Component should reflect restored content
        $component->assertSet('fileContent.boost', $content1);
        $component->assertSet('isDirty.boost', false);
        $component->assertSet('fileExists.boost', true);

        // Step 7: Verify audit log for restore action
        $restoreAuditLog = \App\Models\ConfigAuditLog::where('config_key', 'boost')
            ->where('action', 'restore')
            ->first();

        expect($restoreAuditLog)->not->toBeNull();
        expect($restoreAuditLog->config_key)->toBe('boost');
        expect($restoreAuditLog->action)->toBe('restore');
        expect($restoreAuditLog->backup_path)->toBe($backupContainingVersion1['path']);
        expect($restoreAuditLog->project_id)->toBeNull();
    });

    it('restores backup for project-scoped configs with proper isolation', function () {
        $configFileService = app(ConfigFileService::class);

        // Create two projects
        $project1 = \App\Models\Project::factory()->create([
            'name' => 'Project One',
            'path' => '/tmp/backup-test-project1-'.uniqid(),
        ]);

        $project2 = \App\Models\Project::factory()->create([
            'name' => 'Project Two',
            'path' => '/tmp/backup-test-project2-'.uniqid(),
        ]);

        // Create directories and initial files for both projects
        foreach ([$project1, $project2] as $project) {
            if (! is_dir($project->path)) {
                mkdir($project->path, 0755, true);
            }
            // Create initial config file so saves can create backups
            File::put($project->path.'/opencode.json', json_encode(['initial' => true], JSON_PRETTY_PRINT));
        }

        // Work with project 1
        $component = Livewire::test(AiAgentConfigs::class);
        $component->set('selectedProjectId', $project1->id);

        // Save multiple versions for project 1
        // First save backs up 'initial'
        $content1 = json_encode(['project' => 'one', 'version' => 1], JSON_PRETTY_PRINT);
        $component->set('fileContent.opencode_project', $content1);
        $component->call('save', 'opencode_project');
        $component->assertSet('statusType', 'success');

        // Second save backs up version 1
        $content2 = json_encode(['project' => 'one', 'version' => 2], JSON_PRETTY_PRINT);
        $component->set('fileContent.opencode_project', $content2);
        $component->call('save', 'opencode_project');
        $component->assertSet('statusType', 'success');

        // Work with project 2
        $component->set('selectedProjectId', $project2->id);

        // Save multiple versions for project 2
        $content1p2 = json_encode(['project' => 'two', 'version' => 1], JSON_PRETTY_PRINT);
        $component->set('fileContent.opencode_project', $content1p2);
        $component->call('save', 'opencode_project');
        $component->assertSet('statusType', 'success');

        $content2p2 = json_encode(['project' => 'two', 'version' => 2], JSON_PRETTY_PRINT);
        $component->set('fileContent.opencode_project', $content2p2);
        $component->call('save', 'opencode_project');
        $component->assertSet('statusType', 'success');

        // Verify backup isolation - each project should have its own backups
        $backups1 = $configFileService->listBackups('opencode_project', $project1);
        $backups2 = $configFileService->listBackups('opencode_project', $project2);

        expect($backups1)->toHaveCount(2);
        expect($backups2)->toHaveCount(2);

        // Backup filenames should contain project ID
        foreach ($backups1 as $backup) {
            expect($backup['path'])->toContain('project-'.$project1->id);
            expect($backup['path'])->not->toContain('project-'.$project2->id);
        }

        foreach ($backups2 as $backup) {
            expect($backup['path'])->toContain('project-'.$project2->id);
            expect($backup['path'])->not->toContain('project-'.$project1->id);
        }

        // Find the backup containing version 1 for project 1
        $backupContainingVersion1 = null;
        foreach ($backups1 as $backup) {
            $backupContent = File::get($backup['path']);
            if (str_contains($backupContent, '"version": 1') && str_contains($backupContent, '"project": "one"')) {
                $backupContainingVersion1 = $backup;
                break;
            }
        }
        expect($backupContainingVersion1)->not->toBeNull('Could not find backup containing version 1 for project one');

        // Restore project 1's version 1 backup
        $component->set('selectedProjectId', $project1->id);
        $component->set('selectedBackup.opencode_project', $backupContainingVersion1['path']);
        $component->call('restore', 'opencode_project');
        $component->assertSet('statusType', 'success');

        // Verify project 1 was restored to version 1
        $content1 = File::get($project1->path.'/opencode.json');
        expect($content1)->toContain('"version": 1');
        expect($content1)->toContain('"project": "one"');

        // Verify project 2 was NOT affected
        $content2 = File::get($project2->path.'/opencode.json');
        expect($content2)->toContain('"version": 2'); // Still has latest version
        expect($content2)->toContain('"project": "two"');

        // Cleanup
        File::deleteDirectory($project1->path);
        File::deleteDirectory($project2->path);
    });

    it('fails gracefully when restoring from non-existent backup', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Try to restore from non-existent backup
        $component->set('selectedBackup.boost', '/tmp/non-existent-backup-12345.json');
        $component->call('restore', 'boost');

        $component->assertSet('statusType', 'error');
        $component->assertSet('statusMessage', fn ($msg) => str_contains($msg, 'Restore failed'));

        // Original file should remain unchanged
        $originalContent = File::get(base_path('boost.json'));
        expect($originalContent)->toContain('initial_agent');
    });

    it('requires backup selection before restore', function () {
        $component = Livewire::test(AiAgentConfigs::class);

        // Try to restore without selecting a backup
        $component->set('selectedBackup.boost', '');
        $component->call('restore', 'boost');

        $component->assertSet('statusType', 'error');
        $component->assertSet('statusMessage', fn ($msg) => str_contains($msg, 'select a backup'));
    });

    it('restores backup and updates fileExists to true for previously deleted file', function () {
        $configFileService = app(ConfigFileService::class);

        // Create initial file and backup
        $initialContent = json_encode(['agents' => ['test']], JSON_PRETTY_PRINT);
        File::put(base_path('boost.json'), $initialContent);

        $component = Livewire::test(AiAgentConfigs::class);

        // Save to create a backup
        $component->call('save', 'boost');

        $backups = $configFileService->listBackups('boost');
        expect($backups)->toHaveCount(1);

        // Delete the file
        File::delete(base_path('boost.json'));

        // Verify file is gone
        expect(File::exists(base_path('boost.json')))->toBeFalse();

        // Load component fresh (file should be marked as not existing)
        $component = Livewire::test(AiAgentConfigs::class);
        $component->assertSet('fileExists.boost', false);

        // Restore from backup
        $component->set('selectedBackup.boost', $backups[0]['path']);
        $component->call('restore', 'boost');

        $component->assertSet('statusType', 'success');
        $component->assertSet('fileExists.boost', true);

        // Verify file was recreated
        expect(File::exists(base_path('boost.json')))->toBeTrue();
        $restoredContent = File::get(base_path('boost.json'));
        expect($restoredContent)->toBe($initialContent);
    });

    it('restores backup and creates parent directories if needed', function () {
        $project = \App\Models\Project::factory()->create([
            'name' => 'Deep Path Project',
            'path' => '/tmp/deep-path-test-'.uniqid(),
        ]);

        // Create the project directory structure
        $projectDir = $project->path;
        if (! is_dir($projectDir)) {
            mkdir($projectDir, 0755, true);
        }

        $configFileService = app(ConfigFileService::class);

        // Create initial file - opencode_project uses {project_path}/opencode.json
        $configPath = $projectDir.'/opencode.json';
        $initialContent = json_encode(['deep' => 'config'], JSON_PRETTY_PRINT);
        File::put($configPath, $initialContent);

        $component = Livewire::test(AiAgentConfigs::class);
        $component->set('selectedProjectId', $project->id);

        // Save to create backup
        $component->set('fileContent.opencode_project', $initialContent);
        $component->call('save', 'opencode_project');

        $backups = $configFileService->listBackups('opencode_project', $project);
        expect($backups)->toHaveCount(1);

        // Delete the entire project directory to test directory recreation
        File::deleteDirectory($projectDir);
        expect(File::exists($configPath))->toBeFalse();

        // Load component fresh
        $component = Livewire::test(AiAgentConfigs::class);
        $component->set('selectedProjectId', $project->id);

        // Restore from backup - should recreate project directory
        $component->set('selectedBackup.opencode_project', $backups[0]['path']);
        $component->call('restore', 'opencode_project');

        $component->assertSet('statusType', 'success');

        // Verify file and directories were recreated
        expect(File::exists($configPath))->toBeTrue();
        $restoredContent = File::get($configPath);
        expect($restoredContent)->toBe($initialContent);

        // Cleanup
        File::deleteDirectory($projectDir);
    });
});
