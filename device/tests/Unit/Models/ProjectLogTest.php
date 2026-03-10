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

    $project->delete();

    $logStillExists = ProjectLog::where('id', $logId)->exists();

    expect($logStillExists)->toBeFalse();
});
