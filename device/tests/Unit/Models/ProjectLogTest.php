<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\ProjectLog;

it('belongs to a project', function () {
    $project = Project::factory()->create();
    $log = ProjectLog::factory()->for($project)->create();

    expect($log->project)->toBeInstanceOf(Project::class)
        ->and($log->project->id)->toBe($project->id);
});

it('casts metadata to array', function () {
    $metadata = [
        'key' => 'value',
        'nested' => ['data' => 'test'],
        'number' => 42,
    ];

    $log = ProjectLog::factory()->create([
        'metadata' => $metadata,
    ]);

    expect($log->metadata)->toBeArray()
        ->and($log->metadata['key'])->toBe('value')
        ->and($log->metadata['nested'])->toBeArray()
        ->and($log->metadata['nested']['data'])->toBe('test')
        ->and($log->metadata['number'])->toBe(42);
});

it('casts metadata to array from database json', function () {
    $log = ProjectLog::factory()->create([
        'metadata' => ['key' => 'value', 'number' => 123],
    ]);

    // Force fresh retrieval from database
    $log->refresh();

    expect($log->metadata)->toBeArray()
        ->and($log->metadata['key'])->toBe('value')
        ->and($log->metadata['number'])->toBe(123);
});

it('handles null metadata', function () {
    $log = ProjectLog::factory()->create([
        'metadata' => null,
    ]);

    expect($log->metadata)->toBeNull();
});

it('handles empty array metadata', function () {
    $log = ProjectLog::factory()->create([
        'metadata' => [],
    ]);

    expect($log->metadata)->toBeArray()
        ->and($log->metadata)->toBeEmpty();
});

it('stores and retrieves log attributes', function () {
    $project = Project::factory()->create();
    $log = ProjectLog::factory()->create([
        'project_id' => $project->id,
        'type' => 'error',
        'message' => 'Something went wrong',
        'metadata' => ['error_code' => 500],
    ]);

    expect($log->type)->toBe('error')
        ->and($log->message)->toBe('Something went wrong')
        ->and($log->project_id)->toBe($project->id);
});

it('can access project relationship after refresh', function () {
    $project = Project::factory()->create();
    $log = ProjectLog::factory()->for($project)->create();

    $log->refresh();

    expect($log->project)->toBeInstanceOf(Project::class)
        ->and($log->project->id)->toBe($project->id);
});

it('can update metadata', function () {
    $log = ProjectLog::factory()->create([
        'metadata' => ['initial' => 'data'],
    ]);

    $log->update(['metadata' => ['updated' => 'info', 'new_key' => 'value']]);

    $log->refresh();

    expect($log->metadata)->toBeArray()
        ->and($log->metadata['updated'])->toBe('info')
        ->and($log->metadata['new_key'])->toBe('value')
        ->and(isset($log->metadata['initial']))->toBeFalse();
});

it('can clear metadata', function () {
    $log = ProjectLog::factory()->create([
        'metadata' => ['key' => 'value'],
    ]);

    $log->update(['metadata' => null]);

    $log->refresh();

    expect($log->metadata)->toBeNull();
});

it('factory creates log with default values', function () {
    $log = ProjectLog::factory()->create();

    expect($log->type)->toBeIn(['info', 'error', 'warning', 'scaffold', 'docker'])
        ->and($log->message)->toBeString()
        ->and($log->metadata)->toBeNull()
        ->and($log->project_id)->not->toBeNull();
});

it('factory creates log with custom type', function () {
    $log = ProjectLog::factory()->create(['type' => 'scaffold']);

    expect($log->type)->toBe('scaffold');
});

it('factory creates log with custom message', function () {
    $log = ProjectLog::factory()->create(['message' => 'Custom log message']);

    expect($log->message)->toBe('Custom log message');
});

it('factory creates log with project relationship', function () {
    $project = Project::factory()->create();
    $log = ProjectLog::factory()->for($project)->create();

    expect($log->project_id)->toBe($project->id)
        ->and($log->project->id)->toBe($project->id);
});

it('can query logs for a specific project', function () {
    $project1 = Project::factory()->create();
    $project2 = Project::factory()->create();

    ProjectLog::factory()->count(3)->for($project1)->create();
    ProjectLog::factory()->count(2)->for($project2)->create();

    $project1Logs = ProjectLog::where('project_id', $project1->id)->get();
    $project2Logs = ProjectLog::where('project_id', $project2->id)->get();

    expect($project1Logs)->toHaveCount(3)
        ->and($project2Logs)->toHaveCount(2);
});

it('can query logs by type', function () {
    $project = Project::factory()->create();

    ProjectLog::factory()->count(2)->for($project)->create(['type' => 'error']);
    ProjectLog::factory()->count(3)->for($project)->create(['type' => 'info']);
    ProjectLog::factory()->for($project)->create(['type' => 'warning']);

    $errorLogs = ProjectLog::where('type', 'error')->get();
    $infoLogs = ProjectLog::where('type', 'info')->get();

    expect($errorLogs)->toHaveCount(2)
        ->and($infoLogs)->toHaveCount(3);
});

it('deleting project cascades to its logs', function () {
    $project = Project::factory()->create();
    $log = ProjectLog::factory()->for($project)->create();

    $logId = $log->id;

    // Use forceDelete since Project uses SoftDeletes
    $project->forceDelete();

    $logStillExists = ProjectLog::where('id', $logId)->exists();

    expect($logStillExists)->toBeFalse();
});

// Edge case tests for ProjectLog model

it('eager loads project relationship to avoid N+1 queries', function () {
    $projects = Project::factory()->count(3)->create();
    ProjectLog::factory()->count(5)->for($projects->first())->create();
    ProjectLog::factory()->count(3)->for($projects->last())->create();

    $logs = ProjectLog::with('project')->get();

    expect($logs)->toHaveCount(8);
    foreach ($logs as $log) {
        expect($log->project)->toBeInstanceOf(Project::class);
    }
});

it('returns empty collection when filtering by non-existent type', function () {
    $project = Project::factory()->create();
    ProjectLog::factory()->count(3)->for($project)->create(['type' => 'error']);

    $nonExistentTypeLogs = ProjectLog::where('type', 'nonexistent')->get();

    expect($nonExistentTypeLogs)->toBeEmpty();
});

it('filters by type with exact case sensitivity', function () {
    $project = Project::factory()->create();
    ProjectLog::factory()->for($project)->create(['type' => 'Error']);
    ProjectLog::factory()->for($project)->create(['type' => 'error']);
    ProjectLog::factory()->for($project)->create(['type' => 'ERROR']);

    $lowercaseLogs = ProjectLog::where('type', 'error')->get();
    $uppercaseLogs = ProjectLog::where('type', 'Error')->get();
    $allCapsLogs = ProjectLog::where('type', 'ERROR')->get();

    expect($lowercaseLogs)->toHaveCount(1)
        ->and($uppercaseLogs)->toHaveCount(1)
        ->and($allCapsLogs)->toHaveCount(1);
});

it('filters by multiple types using whereIn', function () {
    $project = Project::factory()->create();
    ProjectLog::factory()->count(2)->for($project)->create(['type' => 'error']);
    ProjectLog::factory()->count(3)->for($project)->create(['type' => 'warning']);
    ProjectLog::factory()->count(4)->for($project)->create(['type' => 'info']);

    $filteredLogs = ProjectLog::whereIn('type', ['error', 'warning'])->get();

    expect($filteredLogs)->toHaveCount(5);
    $filteredLogs->each(function ($log): void {
        expect($log->type)->toBeIn(['error', 'warning']);
    });
});

it('orders logs by created_at in ascending order', function () {
    $project = Project::factory()->create();
    $log1 = ProjectLog::factory()->for($project)->create(['created_at' => now()->subDays(2)]);
    $log2 = ProjectLog::factory()->for($project)->create(['created_at' => now()->subDay()]);
    $log3 = ProjectLog::factory()->for($project)->create(['created_at' => now()]);

    $orderedLogs = ProjectLog::where('project_id', $project->id)->orderBy('created_at')->get();

    expect($orderedLogs->pluck('id')->toArray())->toBe([$log1->id, $log2->id, $log3->id]);
});

it('orders logs by created_at in descending order', function () {
    $project = Project::factory()->create();
    $log1 = ProjectLog::factory()->for($project)->create(['created_at' => now()->subDays(2)]);
    $log2 = ProjectLog::factory()->for($project)->create(['created_at' => now()->subDay()]);
    $log3 = ProjectLog::factory()->for($project)->create(['created_at' => now()]);

    $orderedLogs = ProjectLog::where('project_id', $project->id)->orderBy('created_at', 'desc')->get();

    expect($orderedLogs->pluck('id')->toArray())->toBe([$log3->id, $log2->id, $log1->id]);
});

it('orders logs by multiple columns', function () {
    $project = Project::factory()->create();
    $log1 = ProjectLog::factory()->for($project)->create([
        'type' => 'error',
        'created_at' => now()->subDay(),
    ]);
    $log2 = ProjectLog::factory()->for($project)->create([
        'type' => 'error',
        'created_at' => now(),
    ]);
    $log3 = ProjectLog::factory()->for($project)->create([
        'type' => 'info',
        'created_at' => now()->subDay(),
    ]);

    $orderedLogs = ProjectLog::where('project_id', $project->id)
        ->orderBy('type')
        ->orderBy('created_at', 'desc')
        ->get();

    expect($orderedLogs->pluck('id')->toArray())->toBe([$log2->id, $log1->id, $log3->id]);
});

it('returns latest log per project', function () {
    $project = Project::factory()->create();
    ProjectLog::factory()->for($project)->create(['created_at' => now()->subDays(2)]);
    $latestLog = ProjectLog::factory()->for($project)->create(['created_at' => now()]);
    ProjectLog::factory()->for($project)->create(['created_at' => now()->subDay()]);

    $fetchedLatestLog = ProjectLog::where('project_id', $project->id)->latest()->first();

    expect($fetchedLatestLog->id)->toBe($latestLog->id);
});

it('filters logs by project relationship scope', function () {
    $project1 = Project::factory()->create(['name' => 'Project Alpha']);
    $project2 = Project::factory()->create(['name' => 'Project Beta']);

    ProjectLog::factory()->count(3)->for($project1)->create();
    ProjectLog::factory()->count(2)->for($project2)->create();

    $project1Logs = ProjectLog::whereHas('project', function ($query) use ($project1): void {
        $query->where('id', $project1->id);
    })->get();

    expect($project1Logs)->toHaveCount(3);
    $project1Logs->each(function ($log) use ($project1): void {
        expect($log->project_id)->toBe($project1->id);
    });
});

it('queries logs with complex filters', function () {
    $project = Project::factory()->create();
    ProjectLog::factory()->for($project)->create([
        'type' => 'error',
        'message' => 'Critical database failure',
        'created_at' => now()->subHour(),
    ]);
    ProjectLog::factory()->for($project)->create([
        'type' => 'error',
        'message' => 'Network timeout',
        'created_at' => now()->subMinutes(30),
    ]);
    ProjectLog::factory()->for($project)->create([
        'type' => 'info',
        'message' => 'Application started',
        'created_at' => now(),
    ]);

    $filteredLogs = ProjectLog::where('project_id', $project->id)
        ->where('type', 'error')
        ->where('message', 'like', '%database%')
        ->where('created_at', '<', now()->subMinutes(45))
        ->get();

    expect($filteredLogs)->toHaveCount(1);
    expect($filteredLogs->first()->message)->toBe('Critical database failure');
});

it('counts logs grouped by type', function () {
    $project = Project::factory()->create();
    ProjectLog::factory()->count(5)->for($project)->create(['type' => 'error']);
    ProjectLog::factory()->count(3)->for($project)->create(['type' => 'info']);
    ProjectLog::factory()->count(2)->for($project)->create(['type' => 'warning']);

    $counts = ProjectLog::where('project_id', $project->id)
        ->selectRaw('type, count(*) as count')
        ->groupBy('type')
        ->pluck('count', 'type');

    expect($counts['error'])->toBe(5)
        ->and($counts['info'])->toBe(3)
        ->and($counts['warning'])->toBe(2);
});

it('paginates logs with correct counts', function () {
    $project = Project::factory()->create();
    ProjectLog::factory()->count(25)->for($project)->create();

    $firstPage = ProjectLog::where('project_id', $project->id)->paginate(10);

    expect($firstPage)->toHaveCount(10)
        ->and($firstPage->total())->toBe(25)
        ->and($firstPage->lastPage())->toBe(3);
});

it('filters logs by date range', function () {
    $project = Project::factory()->create();
    ProjectLog::factory()->for($project)->create(['created_at' => now()->subDays(5)]);
    ProjectLog::factory()->for($project)->create(['created_at' => now()->subDays(3)]);
    ProjectLog::factory()->for($project)->create(['created_at' => now()->subDay()]);
    ProjectLog::factory()->for($project)->create(['created_at' => now()]);

    $recentLogs = ProjectLog::where('project_id', $project->id)
        ->whereBetween('created_at', [now()->subDays(2)->startOfDay(), now()])
        ->get();

    expect($recentLogs)->toHaveCount(2);
});

it('handles relationship when project has many logs', function () {
    $project = Project::factory()->create();
    ProjectLog::factory()->count(100)->for($project)->create();

    $logs = ProjectLog::where('project_id', $project->id)->get();
    $projectWithLogs = $project->loadCount('logs');

    expect($logs)->toHaveCount(100)
        ->and($projectWithLogs->logs_count)->toBe(100);
});

it('filters logs by message content', function () {
    $project = Project::factory()->create();
    ProjectLog::factory()->for($project)->create(['message' => 'Connection established']);
    ProjectLog::factory()->for($project)->create(['message' => 'Connection failed']);
    ProjectLog::factory()->for($project)->create(['message' => 'Database error occurred']);

    $connectionLogs = ProjectLog::where('project_id', $project->id)
        ->where('message', 'like', '%Connection%')
        ->get();

    expect($connectionLogs)->toHaveCount(2);
});
