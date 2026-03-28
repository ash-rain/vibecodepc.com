<?php

declare(strict_types=1);

use App\Models\ConfigAuditLog;
use App\Models\Project;
use App\Models\User;
use App\Services\ConfigAuditLogService;
use App\Services\ConfigFileService;
use Illuminate\Support\Facades\File;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    actingAs($this->user);

    // Create test config directory
    $this->testConfigDir = storage_path('app/testing/config');
    File::makeDirectory($this->testConfigDir, 0755, true, true);

    // Set up test config
    config(['vibecodepc.config_files.test' => [
        'path' => $this->testConfigDir.'/test.json',
        'label' => 'Test Config',
        'editable' => true,
        'scope' => 'global',
    ]]);
});

afterEach(function (): void {
    // Clean up test files
    File::deleteDirectory($this->testConfigDir);
    ConfigAuditLog::query()->delete();
});

describe('ConfigAuditLog model', function (): void {
    it('can create a log entry', function (): void {
        $log = ConfigAuditLog::create([
            'config_key' => 'boost',
            'action' => 'save',
            'file_path' => '/path/to/boost.json',
            'old_content_hash' => ['sha256' => 'old_hash'],
            'new_content_hash' => ['sha256' => 'new_hash'],
            'change_summary' => 'Updated boost.json',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Agent',
        ]);

        expect($log->id)->toBeInt();
        expect($log->config_key)->toBe('boost');
        expect($log->action)->toBe('save');
        expect($log->user_id)->toBeNull();
    });

    it('can associate with a user', function (): void {
        $log = ConfigAuditLog::create([
            'config_key' => 'boost',
            'action' => 'save',
            'user_id' => $this->user->id,
            'file_path' => '/path/to/boost.json',
        ]);

        $log->load('user');
        expect($log->user->id)->toBe($this->user->id);
    });

    it('can associate with a project', function (): void {
        $project = Project::factory()->create();

        $log = ConfigAuditLog::create([
            'config_key' => 'opencode_project',
            'action' => 'save',
            'project_id' => $project->id,
            'file_path' => '/path/to/opencode.json',
        ]);

        $log->load('project');
        expect($log->project->id)->toBe($project->id);
    });
});

describe('ConfigAuditLogService', function (): void {
    it('logs config save operations', function (): void {
        $service = new ConfigAuditLogService;
        $path = '/path/to/boost.json';

        $log = $service->log('boost', 'save', $path, 'old content', 'new content');

        expect($log->config_key)->toBe('boost');
        expect($log->action)->toBe('save');
        expect($log->file_path)->toBe($path);
        expect($log->old_content_hash)->toBe(['sha256' => hash('sha256', 'old content')]);
        expect($log->new_content_hash)->toBe(['sha256' => hash('sha256', 'new content')]);
        expect($log->user_id)->toBe($this->user->id);
    });

    it('logs config delete operations', function (): void {
        $service = new ConfigAuditLogService;
        $path = '/path/to/boost.json';

        $log = $service->log('boost', 'delete', $path, 'old content', null);

        expect($log->config_key)->toBe('boost');
        expect($log->action)->toBe('delete');
        expect($log->old_content_hash)->toBe(['sha256' => hash('sha256', 'old content')]);
        expect($log->new_content_hash)->toBeNull();
    });

    it('logs config restore operations', function (): void {
        $service = new ConfigAuditLogService;
        $path = '/path/to/boost.json';
        $backupPath = '/backups/boost-2026-01-01-120000.json';

        $log = $service->log('boost', 'restore', $path, 'old content', 'restored content', $backupPath);

        expect($log->config_key)->toBe('boost');
        expect($log->action)->toBe('restore');
        expect($log->backup_path)->toBe($backupPath);
    });

    it('generates change summary for updates', function (): void {
        $service = new ConfigAuditLogService;
        $path = '/path/to/boost.json';

        $oldContent = json_encode(['agents' => ['copilot'], 'skills' => ['php']]);
        $newContent = json_encode(['agents' => ['copilot', 'claude_code'], 'skills' => ['php', 'laravel']]);

        $log = $service->log('boost', 'save', $path, $oldContent, $newContent);

        expect($log->change_summary)->toContain('Added');
    });

    it('retrieves recent logs for a config key', function (): void {
        $service = new ConfigAuditLogService;

        // Create some logs
        $service->log('boost', 'save', '/path/boost.json', 'old', 'new');
        $service->log('boost', 'save', '/path/boost.json', 'old', 'new');
        $service->log('opencode', 'save', '/path/opencode.json', 'old', 'new');

        $boostLogs = $service->getRecentLogs('boost', 10);

        expect(count($boostLogs))->toBe(2);
        expect($boostLogs[0]->config_key)->toBe('boost');
    });

    it('filters logs by action', function (): void {
        $service = new ConfigAuditLogService;

        $service->log('boost', 'save', '/path/boost.json', 'old', 'new');
        $service->log('boost', 'delete', '/path/boost.json', 'old', null);

        $deleteLogs = $service->getLogs(['action' => 'delete']);

        expect(count($deleteLogs))->toBe(1);
        expect($deleteLogs[0]->action)->toBe('delete');
    });
});

describe('ConfigFileService integration', function (): void {
    it('creates audit log when saving config', function (): void {
        $configService = app(ConfigFileService::class);

        $content = json_encode(['agents' => ['copilot']]);
        $configService->putContent('test', $content);

        $logs = ConfigAuditLog::where('config_key', 'test')->get();
        expect($logs)->toHaveCount(1);
        expect($logs->first()->action)->toBe('save');
    });

    it('creates audit log with old and new content hash', function (): void {
        $configService = app(ConfigFileService::class);

        // First save
        $oldContent = json_encode(['agents' => ['copilot']]);
        $configService->putContent('test', $oldContent);

        // Second save (update)
        $newContent = json_encode(['agents' => ['copilot', 'claude_code']]);
        $configService->putContent('test', $newContent);

        $logs = ConfigAuditLog::where('config_key', 'test')->orderBy('id')->get();
        expect($logs)->toHaveCount(2);

        $secondLog = $logs->last();
        expect($secondLog->old_content_hash['sha256'])->toBe(hash('sha256', $oldContent));
        expect($secondLog->new_content_hash['sha256'])->toBe(hash('sha256', $newContent));
    });

    it('creates audit log when restoring from backup', function (): void {
        $configService = app(ConfigFileService::class);

        // Create initial content
        $content = json_encode(['agents' => ['copilot']]);
        $configService->putContent('test', $content);

        // Update content
        $newContent = json_encode(['agents' => ['claude_code']]);
        $configService->putContent('test', $newContent);

        // Get backup path - there should be at least one backup
        $backups = $configService->listBackups('test');
        expect($backups)->not->toHaveCount(0); // At least one backup exists

        // Use the first backup (older one - sorted by date desc)
        $backupToRestore = $backups[0];

        // Restore from backup
        $configService->restore('test', $backupToRestore['path']);

        $restoreLogs = ConfigAuditLog::where('config_key', 'test')->where('action', 'restore')->get();
        expect($restoreLogs)->toHaveCount(1);
        expect($restoreLogs->first()->backup_path)->toBe($backupToRestore['path']);
    });

    it('creates audit log when deleting config', function (): void {
        $configService = app(ConfigFileService::class);

        // Create and then delete
        $content = json_encode(['agents' => ['copilot']]);
        $configService->putContent('test', $content);
        $configService->delete('test');

        $deleteLogs = ConfigAuditLog::where('config_key', 'test')->where('action', 'delete')->get();
        expect($deleteLogs)->toHaveCount(1);
    });

    it('creates audit log for project-scoped configs', function (): void {
        // Use a subdirectory in our test config dir to avoid permission issues
        $projectPath = $this->testConfigDir.'/test-project';
        $project = Project::factory()->create(['path' => $projectPath]);

        config(['vibecodepc.config_files.test_project' => [
            'path_template' => '{project_path}/test.json',
            'label' => 'Test Project Config',
            'editable' => true,
            'scope' => 'project',
        ]]);

        $configService = app(ConfigFileService::class);
        $content = json_encode(['test' => 'data']);

        // Create project directory
        File::makeDirectory($projectPath, 0755, true, true);

        $configService->putContent('test_project', $content, $project);

        $logs = ConfigAuditLog::where('config_key', 'test_project')->get();
        expect($logs)->toHaveCount(1);
        expect($logs->first()->project_id)->toBe($project->id);

        // Cleanup
        File::deleteDirectory($projectPath);
    });
});
