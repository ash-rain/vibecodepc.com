<?php

declare(strict_types=1);

use App\Models\Project;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

afterEach(function () {
    Cache::flush();
});

it('clears used ports cache when project is created', function () {
    // Prime the cache
    $repository = new \App\Repositories\ProjectRepository;
    $repository->getUsedPorts();
    expect(Cache::has('projects.used_ports'))->toBeTrue();

    // Create a new project
    Project::factory()->create(['port' => 9000]);

    // Cache should be cleared
    expect(Cache::has('projects.used_ports'))->toBeFalse();
});

it('clears used ports cache when project port is updated', function () {
    $project = Project::factory()->create(['port' => 8000]);

    // Prime the cache
    $repository = new \App\Repositories\ProjectRepository;
    $repository->getUsedPorts();
    expect(Cache::has('projects.used_ports'))->toBeTrue();

    // Update the port
    $project->update(['port' => 8001]);

    // Cache should be cleared
    expect(Cache::has('projects.used_ports'))->toBeFalse();
});

it('does not clear cache when non-port fields are updated', function () {
    $project = Project::factory()->create(['port' => 8000]);

    // Prime the cache
    $repository = new \App\Repositories\ProjectRepository;
    $repository->getUsedPorts();
    expect(Cache::has('projects.used_ports'))->toBeTrue();

    // Update a non-port field
    $project->update(['name' => 'New Name']);

    // Cache should still exist
    expect(Cache::has('projects.used_ports'))->toBeTrue();
});

it('clears used ports cache when project is deleted', function () {
    $project = Project::factory()->create(['port' => 8000]);

    // Prime the cache
    $repository = new \App\Repositories\ProjectRepository;
    $repository->getUsedPorts();
    expect(Cache::has('projects.used_ports'))->toBeTrue();

    // Delete the project
    $project->delete();

    // Cache should be cleared
    expect(Cache::has('projects.used_ports'))->toBeFalse();
});

it('clears used ports cache when project is restored', function () {
    $project = Project::factory()->create(['port' => 8000]);
    $project->delete();

    // Prime the cache
    $repository = new \App\Repositories\ProjectRepository;
    $repository->getUsedPorts();
    expect(Cache::has('projects.used_ports'))->toBeTrue();

    // Restore the project
    $project->restore();

    // Cache should be cleared
    expect(Cache::has('projects.used_ports'))->toBeFalse();
});

it('clears used ports cache when project is force deleted', function () {
    $project = Project::factory()->create(['port' => 8000]);

    // Prime the cache
    $repository = new \App\Repositories\ProjectRepository;
    $repository->getUsedPorts();
    expect(Cache::has('projects.used_ports'))->toBeTrue();

    // Force delete the project
    $project->forceDelete();

    // Cache should be cleared
    expect(Cache::has('projects.used_ports'))->toBeFalse();
});
