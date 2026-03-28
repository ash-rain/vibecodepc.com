<?php

declare(strict_types=1);

use App\Models\ConfigAuditLog;
use App\Models\Project;
use App\Models\User;

beforeEach(function (): void {
    ConfigAuditLog::query()->delete();
});

describe('model attributes', function (): void {
    it('can create a config audit log', function (): void {
        $log = ConfigAuditLog::create([
            'config_key' => 'boost',
            'action' => 'save',
            'file_path' => '/path/to/boost.json',
            'change_summary' => 'Updated boost.json',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Agent',
        ]);

        expect($log->id)->toBeInt()
            ->and($log->config_key)->toBe('boost')
            ->and($log->action)->toBe('save');
    });

    it('casts content hashes to array', function (): void {
        $log = ConfigAuditLog::create([
            'config_key' => 'boost',
            'action' => 'save',
            'file_path' => '/path/boost.json',
            'old_content_hash' => ['sha256' => 'old_hash'],
            'new_content_hash' => ['sha256' => 'new_hash'],
        ]);

        $log->refresh();

        expect($log->old_content_hash)->toBeArray()
            ->and($log->old_content_hash['sha256'])->toBe('old_hash')
            ->and($log->new_content_hash['sha256'])->toBe('new_hash');
    });

    it('allows null values for optional fields', function (): void {
        $log = ConfigAuditLog::create([
            'config_key' => 'boost',
            'action' => 'delete',
            'file_path' => '/path/boost.json',
            'user_id' => null,
            'project_id' => null,
            'old_content_hash' => null,
            'new_content_hash' => null,
            'backup_path' => null,
            'change_summary' => null,
        ]);

        expect($log->user_id)->toBeNull()
            ->and($log->project_id)->toBeNull()
            ->and($log->backup_path)->toBeNull();
    });
});

describe('relationships', function (): void {
    it('belongs to a user', function (): void {
        $user = User::factory()->create();
        $log = ConfigAuditLog::factory()->withUser()->create(['user_id' => $user->id]);

        $log->load('user');

        expect($log->user)->toBeInstanceOf(User::class)
            ->and($log->user->id)->toBe($user->id);
    });

    it('belongs to a project', function (): void {
        $project = Project::factory()->create();
        $log = ConfigAuditLog::factory()->withProject()->create(['project_id' => $project->id]);

        $log->load('project');

        expect($log->project)->toBeInstanceOf(Project::class)
            ->and($log->project->id)->toBe($project->id);
    });

    it('handles null user relationship', function (): void {
        $log = ConfigAuditLog::factory()->create(['user_id' => null]);

        $log->load('user');

        expect($log->user)->toBeNull();
    });

    it('handles null project relationship', function (): void {
        $log = ConfigAuditLog::factory()->create(['project_id' => null]);

        $log->load('project');

        expect($log->project)->toBeNull();
    });
});

describe('factory states', function (): void {
    it('creates save action log', function (): void {
        $log = ConfigAuditLog::factory()->saveAction()->create();

        expect($log->action)->toBe('save')
            ->and($log->change_summary)->toContain('Updated');
    });

    it('creates delete action log', function (): void {
        $log = ConfigAuditLog::factory()->deleteAction()->create();

        expect($log->action)->toBe('delete')
            ->and($log->new_content_hash)->toBeNull()
            ->and($log->change_summary)->toContain('Deleted');
    });

    it('creates restore action log', function (): void {
        $log = ConfigAuditLog::factory()->restoreAction()->create();

        expect($log->action)->toBe('restore')
            ->and($log->backup_path)->not->toBeNull()
            ->and($log->change_summary)->toContain('backup');
    });

    it('creates reset action log', function (): void {
        $log = ConfigAuditLog::factory()->resetAction()->create();

        expect($log->action)->toBe('reset')
            ->and($log->change_summary)->toContain('default');
    });

    it('creates log with user', function (): void {
        $user = User::factory()->create();
        $log = ConfigAuditLog::factory()->withUser()->create(['user_id' => $user->id]);

        expect($log->user_id)->toBe($user->id);
    });

    it('creates log with project', function (): void {
        $project = Project::factory()->create();
        $log = ConfigAuditLog::factory()->withProject()->create(['project_id' => $project->id]);

        expect($log->project_id)->toBe($project->id);
    });

    it('creates multiple logs with factory', function (): void {
        ConfigAuditLog::factory()->count(5)->create();

        expect(ConfigAuditLog::count())->toBe(5);
    });
});

describe('query scopes', function (): void {
    beforeEach(function (): void {
        ConfigAuditLog::factory()->saveAction()->create(['config_key' => 'boost']);
        ConfigAuditLog::factory()->deleteAction()->create(['config_key' => 'boost']);
        ConfigAuditLog::factory()->saveAction()->create(['config_key' => 'opencode']);
    });

    it('filters by config key', function (): void {
        $logs = ConfigAuditLog::where('config_key', 'boost')->get();

        expect($logs)->toHaveCount(2);
    });

    it('filters by action', function (): void {
        $logs = ConfigAuditLog::where('action', 'delete')->get();

        expect($logs)->toHaveCount(1)
            ->and($logs->first()->action)->toBe('delete');
    });

    it('orders by created at descending', function (): void {
        ConfigAuditLog::query()->delete();

        $older = ConfigAuditLog::factory()->create([
            'created_at' => now()->subDay(),
        ]);

        $newer = ConfigAuditLog::factory()->create([
            'created_at' => now(),
        ]);

        $logs = ConfigAuditLog::orderByDesc('created_at')->get();

        expect($logs->first()->id)->toBe($newer->id)
            ->and($logs->last()->id)->toBe($older->id);
    });
});

describe('edge cases', function (): void {
    it('handles long config keys', function (): void {
        $longKey = str_repeat('a', 255);

        $log = ConfigAuditLog::factory()->create([
            'config_key' => $longKey,
        ]);

        expect($log->config_key)->toBe($longKey);
    });

    it('handles unicode in file paths', function (): void {
        $path = '/path/配置/файл.json';

        $log = ConfigAuditLog::factory()->create([
            'file_path' => $path,
        ]);

        expect($log->file_path)->toBe($path);
    });

    it('handles long user agent strings', function (): void {
        $longAgent = str_repeat('Mozilla/5.0 ', 100);

        $log = ConfigAuditLog::factory()->create([
            'user_agent' => $longAgent,
        ]);

        expect($log->user_agent)->toBe($longAgent);
    });

    it('handles hash arrays with multiple algorithms', function (): void {
        $log = ConfigAuditLog::factory()->create([
            'old_content_hash' => ['sha256' => 'hash1', 'md5' => 'hash2'],
            'new_content_hash' => ['sha256' => 'hash3', 'md5' => 'hash4'],
        ]);

        $log->refresh();

        expect($log->old_content_hash)->toHaveCount(2)
            ->and($log->old_content_hash['sha256'])->toBe('hash1')
            ->and($log->old_content_hash['md5'])->toBe('hash2');
    });

    it('handles deleted project gracefully', function (): void {
        $project = Project::factory()->create();
        $log = ConfigAuditLog::factory()->withProject()->create(['project_id' => $project->id]);

        $project->delete();

        $retrieved = ConfigAuditLog::find($log->id);
        expect($retrieved->project_id)->toBe($project->id);
    });

    it('handles deleted user gracefully', function (): void {
        $user = User::factory()->create();
        $log = ConfigAuditLog::factory()->withUser()->create(['user_id' => $user->id]);
        $originalUserId = $user->id;

        $user->delete();

        $retrieved = ConfigAuditLog::find($log->id);
        // Log entry should persist (user_id may be nullified by FK constraint)
        expect($retrieved)->not->toBeNull()
            ->and($retrieved->id)->toBe($log->id);
    });
});
