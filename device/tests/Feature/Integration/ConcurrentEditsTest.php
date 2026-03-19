<?php

declare(strict_types=1);

use App\Livewire\Dashboard\AiAgentConfigs;
use App\Services\ConfigFileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create isolated test directories
    $this->testDir = storage_path('testing/concurrent-edits');
    $this->backupDir = storage_path('testing/concurrent-edits-backups');
    $this->userConfigDir = storage_path('testing/concurrent-edits-user-config');

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

    // Clear backups and audit logs before each test
    \App\Models\ConfigAuditLog::query()->delete();

    // Ensure boost.json exists with initial content (in test directory)
    $boostPath = $this->testDir.'/boost.json';
    File::put($boostPath, json_encode(['agents' => ['initial_agent']], JSON_PRETTY_PRINT));
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

    // Clean up project directories
    $testDirs = glob('/tmp/concurrent-test-*');
    foreach ($testDirs as $dir) {
        if (is_dir($dir)) {
            File::deleteDirectory($dir);
        }
    }
});

// F2.1: Test concurrent edits
describe('concurrent edits', function () {
    it('detects conflict when another user modifies file between load and save', function () {
        $configFileService = app(ConfigFileService::class);

        // Step 1: User A loads config
        $componentA = Livewire::test(AiAgentConfigs::class);
        $componentA->assertStatus(200);
        $componentA->assertSet('fileContent.boost', fn ($content) => str_contains($content, 'initial_agent'));

        // Get the content hash that User A loaded with
        $contentHashA = $componentA->get('contentHash.boost');
        expect($contentHashA)->not->toBeEmpty();

        // Step 2: Another process (simulating User B) modifies the file directly
        $modifiedByUserB = json_encode([
            'agents' => ['user_b_agent'],
            'skills' => ['user_b_skill'],
        ], JSON_PRETTY_PRINT);

        File::put(config('vibecodepc.config_files.boost.path'), $modifiedByUserB);

        // Verify file was actually modified
        $currentContent = File::get(config('vibecodepc.config_files.boost.path'));
        expect($currentContent)->toContain('user_b_agent');

        // Step 3: User A tries to save their changes
        $userAContent = json_encode([
            'agents' => ['user_a_agent'],
            'skills' => ['user_a_skill'],
        ], JSON_PRETTY_PRINT);

        $componentA->set('fileContent.boost', $userAContent);
        $componentA->call('save', 'boost');

        // Step 4: Verify conflict was detected
        $componentA->assertSet('statusType', 'error');
        $statusMessage = $componentA->get('statusMessage');
        expect($statusMessage)->toContain('Conflict detected');
        expect($statusMessage)->toContain('modified by another user');
        expect($statusMessage)->toContain('reload');

        // Step 5: Verify User B's changes were preserved
        $finalContent = File::get(config('vibecodepc.config_files.boost.path'));
        expect($finalContent)->toContain('user_b_agent');
        expect($finalContent)->not->toContain('user_a_agent');
    });

    it('allows save when no conflict exists (content hash matches)', function () {
        $configFileService = app(ConfigFileService::class);

        // User loads config
        $component = Livewire::test(AiAgentConfigs::class);

        // Get the content hash
        $contentHash = $component->get('contentHash.boost');
        expect($contentHash)->not->toBeEmpty();

        // User modifies and saves
        $newContent = json_encode([
            'agents' => ['new_agent'],
            'skills' => ['new_skill'],
        ], JSON_PRETTY_PRINT);

        $component->set('fileContent.boost', $newContent);
        $component->call('save', 'boost');

        // Save should succeed
        $component->assertSet('statusType', 'success');

        // Verify file was updated
        $savedContent = File::get(config('vibecodepc.config_files.boost.path'));
        expect($savedContent)->toContain('new_agent');
        expect($savedContent)->toContain('new_skill');
    });

    it('allows save for new files without hash check', function () {
        // Delete boost.json to simulate new file scenario
        $boostPath = config('vibecodepc.config_files.boost.path');
        if (File::exists($boostPath)) {
            File::delete($boostPath);
        }

        // User loads component - no file exists
        $component = Livewire::test(AiAgentConfigs::class);
        $component->assertSet('fileExists.boost', false);
        $component->assertSet('contentHash.boost', '');

        // User creates new file
        $newContent = json_encode([
            'agents' => ['first_agent'],
            'skills' => ['first_skill'],
        ], JSON_PRETTY_PRINT);

        $component->set('fileContent.boost', $newContent);
        $component->call('save', 'boost');

        // Save should succeed for new files
        $component->assertSet('statusType', 'success');

        // Verify file was created
        $savedContent = File::get(config('vibecodepc.config_files.boost.path'));
        expect($savedContent)->toContain('first_agent');
    });

    it('detects conflict for project-scoped configs', function () {
        $configFileService = app(ConfigFileService::class);

        // Create a project
        $project = \App\Models\Project::factory()->create([
            'name' => 'Concurrent Test Project',
            'path' => '/tmp/concurrent-test-'.uniqid(),
        ]);

        // Create project config directory and initial file
        $projectConfigDir = $project->path;
        if (! is_dir($projectConfigDir)) {
            mkdir($projectConfigDir, 0755, true);
        }

        // Create initial config file
        File::put($projectConfigDir.'/opencode.json', json_encode([
            'model' => 'initial-model',
        ], JSON_PRETTY_PRINT));

        // User A loads project config
        $componentA = Livewire::test(AiAgentConfigs::class);
        $componentA->set('selectedProjectId', $project->id);
        $componentA->assertSet('fileContent.opencode_project', fn ($content) => str_contains($content, 'initial-model'));

        // Get User A's content hash
        $contentHashA = $componentA->get('contentHash.opencode_project');
        expect($contentHashA)->not->toBeEmpty();

        // User B modifies the file directly
        File::put($projectConfigDir.'/opencode.json', json_encode([
            'model' => 'user-b-model',
            'temperature' => 0.5,
        ], JSON_PRETTY_PRINT));

        // User A tries to save
        $userAContent = json_encode([
            'model' => 'user-a-model',
            'max_tokens' => 1000,
        ], JSON_PRETTY_PRINT);

        $componentA->set('fileContent.opencode_project', $userAContent);
        $componentA->call('save', 'opencode_project');

        // Conflict should be detected
        $componentA->assertSet('statusType', 'error');
        $statusMessage = $componentA->get('statusMessage');
        expect($statusMessage)->toContain('Conflict detected');

        // Verify User B's changes were preserved
        $finalContent = File::get($projectConfigDir.'/opencode.json');
        expect($finalContent)->toContain('user-b-model');
        expect($finalContent)->not->toContain('user-a-model');

        // Cleanup
        File::deleteDirectory($projectConfigDir);
    });

    it('allows save after reloading with conflict detection disabled', function () {
        // User A loads config
        $componentA = Livewire::test(AiAgentConfigs::class);

        // Another user modifies the file
        File::put(config('vibecodepc.config_files.boost.path'), json_encode(['agents' => ['modified']], JSON_PRETTY_PRINT));

        // User A tries to save and gets conflict
        $componentA->set('fileContent.boost', json_encode(['agents' => ['user-a']], JSON_PRETTY_PRINT));
        $componentA->call('save', 'boost');
        $componentA->assertSet('statusType', 'error');

        // User A reloads the file (simulated by mounting a new component)
        $componentA = Livewire::test(AiAgentConfigs::class);
        $componentA->assertSet('fileContent.boost', fn ($content) => str_contains($content, 'modified'));

        // Now save should work
        $componentA->set('fileContent.boost', json_encode(['agents' => ['user-a-after-reload']], JSON_PRETTY_PRINT));
        $componentA->call('save', 'boost');
        $componentA->assertSet('statusType', 'success');

        // Verify final content
        $finalContent = File::get(config('vibecodepc.config_files.boost.path'));
        expect($finalContent)->toContain('user-a-after-reload');
    });

    it('handles rapid successive modifications', function () {
        $configFileService = app(ConfigFileService::class);

        // Create a project for isolation
        $project = \App\Models\Project::factory()->create([
            'name' => 'Rapid Test Project',
            'path' => '/tmp/concurrent-test-'.uniqid(),
        ]);

        $projectConfigDir = $project->path;
        if (! is_dir($projectConfigDir)) {
            mkdir($projectConfigDir, 0755, true);
        }

        File::put($projectConfigDir.'/opencode.json', json_encode(['version' => 0], JSON_PRETTY_PRINT));

        // Simulate multiple users loading the same config
        $user1 = Livewire::test(AiAgentConfigs::class);
        $user1->set('selectedProjectId', $project->id);

        $user2 = Livewire::test(AiAgentConfigs::class);
        $user2->set('selectedProjectId', $project->id);

        // User 1 saves first
        $user1->set('fileContent.opencode_project', json_encode(['version' => 1, 'by' => 'user1'], JSON_PRETTY_PRINT));
        $user1->call('save', 'opencode_project');
        $user1->assertSet('statusType', 'success');

        // User 2 tries to save with stale hash - should get conflict
        $user2->set('fileContent.opencode_project', json_encode(['version' => 2, 'by' => 'user2'], JSON_PRETTY_PRINT));
        $user2->call('save', 'opencode_project');
        $user2->assertSet('statusType', 'error');

        // Verify only user 1's changes were saved
        $finalContent = File::get($projectConfigDir.'/opencode.json');
        expect($finalContent)->toContain('"version": 1');
        expect($finalContent)->toContain('user1');
        expect($finalContent)->not->toContain('user2');

        // Cleanup
        File::deleteDirectory($projectConfigDir);
    });

    it('updates content hash after successful save', function () {
        $configFileService = app(ConfigFileService::class);

        // User loads config
        $component = Livewire::test(AiAgentConfigs::class);
        $initialHash = $component->get('contentHash.boost');
        expect($initialHash)->not->toBeEmpty();

        // User saves changes
        $newContent = json_encode(['agents' => ['updated'], 'skills' => ['php']], JSON_PRETTY_PRINT);
        $component->set('fileContent.boost', $newContent);
        $component->call('save', 'boost');
        $component->assertSet('statusType', 'success');

        // Verify hash was updated
        $newHash = $component->get('contentHash.boost');
        expect($newHash)->not->toBe($initialHash);
        expect($newHash)->toBe($configFileService->getContentHash($newContent));
    });

    it('handles concurrent edits on different config files independently', function () {
        // Create an opencode_global config
        $opencodePath = config('vibecodepc.config_files.opencode_global.path');
        File::makeDirectory(dirname($opencodePath), 0755, true, true);
        File::put($opencodePath, json_encode(['model' => 'gpt-4'], JSON_PRETTY_PRINT));

        // User loads boost config
        $componentBoost = Livewire::test(AiAgentConfigs::class);
        $componentBoost->set('activeTab', 'boost');

        // Another user modifies opencode config (different file)
        File::put($opencodePath, json_encode(['model' => 'claude-3'], JSON_PRETTY_PRINT));

        // User tries to save boost - should succeed (different file)
        $componentBoost->set('fileContent.boost', json_encode(['agents' => ['new-agent']], JSON_PRETTY_PRINT));
        $componentBoost->call('save', 'boost');
        $componentBoost->assertSet('statusType', 'success');

        // Verify boost was saved
        $boostContent = File::get(config('vibecodepc.config_files.boost.path'));
        expect($boostContent)->toContain('new-agent');

        // Cleanup
        if (File::exists($opencodePath)) {
            File::delete($opencodePath);
        }
    });

    it('provides helpful error message on conflict', function () {
        // User loads config
        $component = Livewire::test(AiAgentConfigs::class);

        // Another user modifies the file
        File::put(config('vibecodepc.config_files.boost.path'), json_encode(['agents' => ['conflicting-change']], JSON_PRETTY_PRINT));

        // User tries to save
        $component->set('fileContent.boost', json_encode(['agents' => ['user-change']], JSON_PRETTY_PRINT));
        $component->call('save', 'boost');

        // Error message should be helpful
        $statusMessage = $component->get('statusMessage');
        expect($statusMessage)->toContain('Conflict detected');
        expect($statusMessage)->toContain('modified by another user');
        expect($statusMessage)->toContain('Please reload the file');
    });

    it('conflict detection is bypassed when expected hash is null', function () {
        // Test that putContent with null expectedHash works correctly
        $configFileService = app(ConfigFileService::class);

        // Simulate first save with null hash (no conflict check)
        $content1 = json_encode(['agents' => ['first']], JSON_PRETTY_PRINT);
        $configFileService->putContent('boost', $content1, null, null);

        // Verify file was written
        $savedContent = File::get(config('vibecodepc.config_files.boost.path'));
        expect($savedContent)->toContain('first');

        // Now modify file externally
        $content2 = json_encode(['agents' => ['external']], JSON_PRETTY_PRINT);
        File::put(config('vibecodepc.config_files.boost.path'), $content2);

        // Save with null hash again should overwrite
        $content3 = json_encode(['agents' => ['third']], JSON_PRETTY_PRINT);
        $configFileService->putContent('boost', $content3, null, null);

        // Verify third content was written
        $finalContent = File::get(config('vibecodepc.config_files.boost.path'));
        expect($finalContent)->toContain('third');
    });
});
