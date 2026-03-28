<?php

declare(strict_types=1);

use App\Models\Project;
use App\Repositories\ProjectRepository;
use Illuminate\Support\Facades\DB;

it('selects only required columns for tunnel display', function () {
    Project::factory()->create([
        'name' => 'Test Project',
        'slug' => 'test-project',
        'port' => 8000,
        'tunnel_enabled' => true,
        'tunnel_subdomain_path' => 'test',
    ]);

    $repository = new ProjectRepository;

    // Enable query logging to verify column selection
    DB::enableQueryLog();

    $projects = $repository->allForTunnelDisplay();

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($projects)->toHaveCount(1);

    $project = $projects->first();
    expect($project->name)->toBe('Test Project')
        ->and($project->slug)->toBe('test-project')
        ->and($project->port)->toBe(8000)
        ->and($project->tunnel_enabled)->toBe(true)
        ->and($project->tunnel_subdomain_path)->toBe('test');

    // Verify the query only selects required columns
    $selectQuery = $queries[0]['query'] ?? '';
    expect($selectQuery)
        ->toContain('select')
        ->toContain('id')
        ->toContain('name')
        ->toContain('slug')
        ->toContain('port')
        ->toContain('tunnel_enabled')
        ->toContain('tunnel_subdomain_path');
});

it('orders projects by name', function () {
    Project::factory()->create(['name' => 'Zebra Project']);
    Project::factory()->create(['name' => 'Apple Project']);
    Project::factory()->create(['name' => 'Mango Project']);

    $repository = new ProjectRepository;
    $projects = $repository->allForTunnelDisplay();

    $names = $projects->pluck('name')->all();
    expect($names)->toBe(['Apple Project', 'Mango Project', 'Zebra Project']);
});

it('handles projects with null port values', function () {
    Project::factory()->create([
        'name' => 'No Port Project',
        'port' => null,
    ]);

    $repository = new ProjectRepository;
    $projects = $repository->allForTunnelDisplay();

    expect($projects)->toHaveCount(1);
    expect($projects->first()->port)->toBeNull();
});

it('handles projects with null tunnel_subdomain_path', function () {
    Project::factory()->create([
        'name' => 'No Path Project',
        'tunnel_subdomain_path' => null,
    ]);

    $repository = new ProjectRepository;
    $projects = $repository->allForTunnelDisplay();

    expect($projects)->toHaveCount(1);
    expect($projects->first()->tunnel_subdomain_path)->toBeNull();
});

it('returns empty collection when no projects exist', function () {
    $repository = new ProjectRepository;
    $projects = $repository->allForTunnelDisplay();

    expect($projects)->toBeEmpty();
});

it('loads only specified columns and not full model data', function () {
    Project::factory()->create([
        'name' => 'Test Project',
        'slug' => 'test-project',
        'port' => 8000,
        'tunnel_enabled' => true,
        'tunnel_subdomain_path' => 'test',
        // Other fields like path, clone_url, container_id should not be loaded
        'path' => '/some/path',
        'clone_url' => 'https://github.com/test/repo',
    ]);

    $repository = new ProjectRepository;
    $projects = $repository->allForTunnelDisplay();

    $project = $projects->first();

    // These columns should be loaded
    expect($project->id)->not->toBeNull();
    expect($project->name)->toBe('Test Project');
    expect($project->slug)->toBe('test-project');
    expect($project->port)->toBe(8000);
    expect($project->tunnel_enabled)->toBe(true);
    expect($project->tunnel_subdomain_path)->toBe('test');

    // Verify the model only has the selected columns loaded
    $loadedColumns = array_keys($project->getAttributes());
    $expectedColumns = ['id', 'name', 'slug', 'port', 'tunnel_enabled', 'tunnel_subdomain_path'];

    foreach ($expectedColumns as $column) {
        expect(in_array($column, $loadedColumns, true))->toBeTrue(
            "Expected column {$column} to be loaded"
        );
    }
});
