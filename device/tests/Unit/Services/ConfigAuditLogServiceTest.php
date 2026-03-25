<?php

declare(strict_types=1);

use App\Models\ConfigAuditLog;
use App\Models\Project;
use App\Models\User;
use App\Services\ConfigAuditLogService;
use Illuminate\Support\Facades\Request;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    actingAs($this->user);
});

describe('log method', function (): void {
    it('logs a save action with content hashes', function (): void {
        $service = new ConfigAuditLogService;
        $oldContent = json_encode(['setting' => 'old']);
        $newContent = json_encode(['setting' => 'new']);

        $log = $service->log('boost', 'save', '/path/boost.json', $oldContent, $newContent);

        expect($log->config_key)->toBe('boost')
            ->and($log->action)->toBe('save')
            ->and($log->file_path)->toBe('/path/boost.json')
            ->and($log->old_content_hash)->toBe(['sha256' => hash('sha256', $oldContent)])
            ->and($log->new_content_hash)->toBe(['sha256' => hash('sha256', $newContent)])
            ->and($log->user_id)->toBe($this->user->id);
    });

    it('logs a delete action with null new content', function (): void {
        $service = new ConfigAuditLogService;
        $oldContent = json_encode(['setting' => 'value']);

        $log = $service->log('boost', 'delete', '/path/boost.json', $oldContent, null);

        expect($log->action)->toBe('delete')
            ->and($log->old_content_hash)->toBe(['sha256' => hash('sha256', $oldContent)])
            ->and($log->new_content_hash)->toBeNull();
    });

    it('logs a reset action', function (): void {
        $service = new ConfigAuditLogService;

        $log = $service->log('boost', 'reset', '/path/boost.json', null, null);

        expect($log->action)->toBe('reset')
            ->and($log->change_summary)->toContain('default');
    });

    it('logs a restore action with backup path', function (): void {
        $service = new ConfigAuditLogService;
        $backupPath = '/backups/boost-2026-01-01.json';

        $log = $service->log('boost', 'restore', '/path/boost.json', 'old', 'restored', $backupPath);

        expect($log->action)->toBe('restore')
            ->and($log->backup_path)->toBe($backupPath)
            ->and($log->change_summary)->toContain('backup');
    });

    it('captures IP address and user agent', function (): void {
        $service = new ConfigAuditLogService;

        // Create a real request with the desired values
        $request = new \Illuminate\Http\Request;
        $request->server->set('REMOTE_ADDR', '192.168.1.100');
        $request->headers->set('User-Agent', 'TestAgent/1.0');

        // Swap the request in the container
        app()->bind('request', fn () => $request);

        $log = $service->log('boost', 'save', '/path/boost.json', 'old', 'new');

        expect($log->ip_address)->toBe('192.168.1.100')
            ->and($log->user_agent)->toBe('TestAgent/1.0');
    });

    it('handles null old content for new file creation', function (): void {
        $service = new ConfigAuditLogService;
        $newContent = json_encode(['setting' => 'value']);

        $log = $service->log('boost', 'save', '/path/boost.json', null, $newContent);

        expect($log->old_content_hash)->toBeNull()
            ->and($log->new_content_hash)->toBe(['sha256' => hash('sha256', $newContent)])
            ->and($log->change_summary)->toContain('Created');
    });

    it('associates log with project when provided', function (): void {
        $service = new ConfigAuditLogService;
        $project = Project::factory()->create();

        $log = $service->log('opencode_project', 'save', '/path/config.json', 'old', 'new', null, $project);

        expect($log->project_id)->toBe($project->id);
    });

    it('works without authenticated user', function (): void {
        auth()->logout();
        $service = new ConfigAuditLogService;

        $log = $service->log('boost', 'save', '/path/boost.json', 'old', 'new');

        expect($log->user_id)->toBeNull();
    });

    it('generates correct hash for large content', function (): void {
        $service = new ConfigAuditLogService;
        $largeContent = str_repeat('x', 10000);

        $log = $service->log('boost', 'save', '/path/boost.json', null, $largeContent);

        expect($log->new_content_hash)->toBe(['sha256' => hash('sha256', $largeContent)]);
    });
});

describe('change summary generation', function (): void {
    it('summarizes delete action', function (): void {
        $service = new ConfigAuditLogService;

        $log = $service->log('boost', 'delete', '/path/boost.json', 'content', null);

        expect($log->change_summary)->toBe('Deleted boost configuration file');
    });

    it('summarizes reset action', function (): void {
        $service = new ConfigAuditLogService;

        $log = $service->log('boost', 'reset', '/path/boost.json', null, null);

        expect($log->change_summary)->toBe('Reset boost to default values');
    });

    it('summarizes restore action', function (): void {
        $service = new ConfigAuditLogService;

        $log = $service->log('boost', 'restore', '/path/boost.json', 'old', 'restored', '/backup.json');

        expect($log->change_summary)->toBe('Restored boost from backup');
    });

    it('summarizes new file creation', function (): void {
        $service = new ConfigAuditLogService;

        $log = $service->log('boost', 'save', '/path/boost.json', null, json_encode(['key' => 'value']));

        expect($log->change_summary)->toBe('Created new boost configuration');
    });

    it('detects added keys in JSON update', function (): void {
        $service = new ConfigAuditLogService;
        $old = json_encode(['existing' => 'value']);
        $new = json_encode(['existing' => 'value', 'newkey' => 'newvalue']);

        $log = $service->log('boost', 'save', '/path/boost.json', $old, $new);

        expect($log->change_summary)->toContain("Added 'newkey'");
    });

    it('detects removed keys in JSON update', function (): void {
        $service = new ConfigAuditLogService;
        $old = json_encode(['keep' => 'value', 'remove' => 'value']);
        $new = json_encode(['keep' => 'value']);

        $log = $service->log('boost', 'save', '/path/boost.json', $old, $new);

        expect($log->change_summary)->toContain("Removed 'remove'");
    });

    it('detects modified keys in JSON update', function (): void {
        $service = new ConfigAuditLogService;
        $old = json_encode(['setting' => 'old']);
        $new = json_encode(['setting' => 'new']);

        $log = $service->log('boost', 'save', '/path/boost.json', $old, $new);

        expect($log->change_summary)->toContain("Modified 'setting'");
    });

    it('detects nested changes in JSON update', function (): void {
        $service = new ConfigAuditLogService;
        $old = json_encode(['config' => ['nested' => 'old']]);
        $new = json_encode(['config' => ['nested' => 'new']]);

        $log = $service->log('boost', 'save', '/path/boost.json', $old, $new);

        expect($log->change_summary)->toContain('config');
    });

    it('limits change summary to 5 items', function (): void {
        $service = new ConfigAuditLogService;
        $old = json_encode([]);
        $new = json_encode(['key1' => 1, 'key2' => 2, 'key3' => 3, 'key4' => 4, 'key5' => 5, 'key6' => 6]);

        $log = $service->log('boost', 'save', '/path/boost.json', $old, $new);

        // Should only show first 5 changes
        expect($log->change_summary)->toContain('key1')
            ->and($log->change_summary)->toContain('key5')
            ->and(strpos($log->change_summary, 'key6'))->toBeFalse();
    });

    it('falls back to generic message for invalid JSON', function (): void {
        $service = new ConfigAuditLogService;
        $old = 'not valid json';
        $new = 'also not valid';

        $log = $service->log('boost', 'save', '/path/boost.json', $old, $new);

        expect($log->change_summary)->toBe('Updated boost configuration');
    });

    it('handles deeply nested object changes', function (): void {
        $service = new ConfigAuditLogService;
        $old = json_encode(['level1' => ['level2' => ['level3' => 'old']]]);
        $new = json_encode(['level1' => ['level2' => ['level3' => 'new']]]);

        $log = $service->log('boost', 'save', '/path/boost.json', $old, $new);

        expect($log->change_summary)->toContain('level1')
            ->and($log->change_summary)->toContain('level2');
    });
});

describe('getRecentLogs method', function (): void {
    it('returns logs for specific config key', function (): void {
        $service = new ConfigAuditLogService;
        $service->log('boost', 'save', '/path/boost.json', 'old', 'new');
        $service->log('boost', 'save', '/path/boost.json', 'old', 'new');
        $service->log('opencode', 'save', '/path/opencode.json', 'old', 'new');

        $logs = $service->getRecentLogs('boost', 10);

        expect($logs)->toHaveCount(2)
            ->and($logs[0]->config_key)->toBe('boost');
    });

    it('respects the limit parameter', function (): void {
        $service = new ConfigAuditLogService;
        for ($i = 0; $i < 10; $i++) {
            $service->log('boost', 'save', '/path/boost.json', 'old', "new{$i}");
        }

        $logs = $service->getRecentLogs('boost', 5);

        expect($logs)->toHaveCount(5);
    });

    it('returns logs ordered by created_at desc', function (): void {
        $service = new ConfigAuditLogService;

        $older = ConfigAuditLog::create([
            'config_key' => 'boost',
            'action' => 'save',
            'file_path' => '/path/boost.json',
            'created_at' => now()->subDay(),
        ]);

        $newer = ConfigAuditLog::create([
            'config_key' => 'boost',
            'action' => 'save',
            'file_path' => '/path/boost.json',
            'created_at' => now(),
        ]);

        $logs = $service->getRecentLogs('boost', 10);

        expect($logs[0]->id)->toBe($newer->id)
            ->and($logs[1]->id)->toBe($older->id);
    });

    it('loads user and project relationships', function (): void {
        $service = new ConfigAuditLogService;
        $project = Project::factory()->create();

        $service->log('opencode_project', 'save', '/path/config.json', 'old', 'new', null, $project);

        $logs = $service->getRecentLogs('opencode_project', 10);

        expect($logs[0]->user)->toBeInstanceOf(User::class)
            ->and($logs[0]->project)->toBeInstanceOf(Project::class);
    });

    it('returns empty array when no logs exist', function (): void {
        $service = new ConfigAuditLogService;

        $logs = $service->getRecentLogs('nonexistent', 10);

        expect($logs)->toBe([]);
    });
});

describe('getLogs method', function (): void {
    it('returns all logs with no filters', function (): void {
        $service = new ConfigAuditLogService;
        $service->log('boost', 'save', '/path/boost.json', 'old', 'new');
        $service->log('opencode', 'save', '/path/opencode.json', 'old', 'new');

        $logs = $service->getLogs([], 50);

        expect($logs)->toHaveCount(2);
    });

    it('filters by config_key', function (): void {
        $service = new ConfigAuditLogService;
        $service->log('boost', 'save', '/path/boost.json', 'old', 'new');
        $service->log('opencode', 'save', '/path/opencode.json', 'old', 'new');

        $logs = $service->getLogs(['config_key' => 'boost'], 50);

        expect($logs)->toHaveCount(1)
            ->and($logs[0]->config_key)->toBe('boost');
    });

    it('filters by action', function (): void {
        $service = new ConfigAuditLogService;
        $service->log('boost', 'save', '/path/boost.json', 'old', 'new');
        $service->log('boost', 'delete', '/path/boost.json', 'old', null);

        $logs = $service->getLogs(['action' => 'delete'], 50);

        expect($logs)->toHaveCount(1)
            ->and($logs[0]->action)->toBe('delete');
    });

    it('filters by user_id', function (): void {
        $service = new ConfigAuditLogService;
        $otherUser = User::factory()->create();

        $service->log('boost', 'save', '/path/boost.json', 'old', 'new');

        // Create log as other user
        actingAs($otherUser);
        $service->log('boost', 'save', '/path/boost.json', 'old', 'new');

        $logs = $service->getLogs(['user_id' => $this->user->id], 50);

        expect($logs)->toHaveCount(1)
            ->and($logs[0]->user_id)->toBe($this->user->id);
    });

    it('filters by project_id', function (): void {
        $service = new ConfigAuditLogService;
        $project1 = Project::factory()->create();
        $project2 = Project::factory()->create();

        $service->log('opencode_project', 'save', '/path/config.json', 'old', 'new', null, $project1);
        $service->log('opencode_project', 'save', '/path/config.json', 'old', 'new', null, $project2);

        $logs = $service->getLogs(['project_id' => $project1->id], 50);

        expect($logs)->toHaveCount(1)
            ->and($logs[0]->project_id)->toBe($project1->id);
    });

    it('applies multiple filters', function (): void {
        $service = new ConfigAuditLogService;
        $service->log('boost', 'save', '/path/boost.json', 'old', 'new');
        $service->log('boost', 'delete', '/path/boost.json', 'old', null);
        $service->log('opencode', 'save', '/path/opencode.json', 'old', 'new');

        $logs = $service->getLogs(['config_key' => 'boost', 'action' => 'save'], 50);

        expect($logs)->toHaveCount(1)
            ->and($logs[0]->config_key)->toBe('boost')
            ->and($logs[0]->action)->toBe('save');
    });

    it('respects the limit parameter', function (): void {
        $service = new ConfigAuditLogService;
        for ($i = 0; $i < 20; $i++) {
            $service->log('boost', 'save', '/path/boost.json', 'old', "new{$i}");
        }

        $logs = $service->getLogs([], 10);

        expect($logs)->toHaveCount(10);
    });

    it('loads relationships for all returned logs', function (): void {
        $service = new ConfigAuditLogService;
        $service->log('boost', 'save', '/path/boost.json', 'old', 'new');

        $logs = $service->getLogs([], 50);

        // Verify relationships are eager loaded
        expect($logs[0]->user)->not->toBeNull();
    });
});

describe('edge cases', function (): void {
    it('handles special characters in config key', function (): void {
        $service = new ConfigAuditLogService;

        $log = $service->log('config-with-special_chars.123', 'save', '/path/config.json', 'old', 'new');

        expect($log->config_key)->toBe('config-with-special_chars.123');
    });

    it('handles unicode in file paths', function (): void {
        $service = new ConfigAuditLogService;
        $path = '/path/配置/файл.json';

        $log = $service->log('boost', 'save', $path, 'old', 'new');

        expect($log->file_path)->toBe($path);
    });

    it('handles empty JSON objects', function (): void {
        $service = new ConfigAuditLogService;
        $old = json_encode(['key' => 'value']);
        $new = json_encode([]);

        $log = $service->log('boost', 'save', '/path/boost.json', $old, $new);

        expect($log->change_summary)->toContain("Removed 'key'");
    });

    it('handles JSON arrays', function (): void {
        $service = new ConfigAuditLogService;
        $old = json_encode(['item1']);
        $new = json_encode(['item1', 'item2']);

        $log = $service->log('boost', 'save', '/path/boost.json', $old, $new);

        // Arrays at root level are treated as objects with numeric keys
        expect($log->change_summary)->toContain("Added '1'");
    });

    it('handles null values in JSON', function (): void {
        $service = new ConfigAuditLogService;
        $old = json_encode(['key' => null]);
        $new = json_encode(['key' => 'value']);

        $log = $service->log('boost', 'save', '/path/boost.json', $old, $new);

        expect($log->change_summary)->toContain("Modified 'key'");
    });

    it('handles boolean values in JSON', function (): void {
        $service = new ConfigAuditLogService;
        $old = json_encode(['enabled' => false]);
        $new = json_encode(['enabled' => true]);

        $log = $service->log('boost', 'save', '/path/boost.json', $old, $new);

        expect($log->change_summary)->toContain("Modified 'enabled'");
    });

    it('handles numeric values in JSON', function (): void {
        $service = new ConfigAuditLogService;
        $old = json_encode(['count' => 1]);
        $new = json_encode(['count' => 2]);

        $log = $service->log('boost', 'save', '/path/boost.json', $old, $new);

        expect($log->change_summary)->toContain("Modified 'count'");
    });

    it('handles mixed array and object values', function (): void {
        $service = new ConfigAuditLogService;
        $old = json_encode(['items' => ['a', 'b']]);
        $new = json_encode(['items' => ['a', 'b', 'c']]);

        $log = $service->log('boost', 'save', '/path/boost.json', $old, $new);

        // Nested arrays show as modified
        expect($log->change_summary)->toContain('items');
    });

    it('handles deleted project gracefully', function (): void {
        $service = new ConfigAuditLogService;
        $project = Project::factory()->create();

        $log = $service->log('opencode_project', 'save', '/path/config.json', 'old', 'new', null, $project);

        $project->delete();

        // Should still be able to retrieve the log
        $retrieved = ConfigAuditLog::find($log->id);
        expect($retrieved->project_id)->toBe($project->id);
    });
});
