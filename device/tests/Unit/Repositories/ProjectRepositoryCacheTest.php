<?php

declare(strict_types=1);

use App\Models\Project;
use App\Repositories\ProjectRepository;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

afterEach(function () {
    Cache::flush();
});

it('caches used ports', function () {
    Project::factory()->create(['port' => 8000]);
    Project::factory()->create(['port' => 8001]);

    $repository = new ProjectRepository;

    // First call should hit database and cache result
    $ports1 = $repository->getUsedPorts();
    expect($ports1)->toContain(8000, 8001)
        ->and(Cache::has('projects.used_ports'))->toBeTrue();

    // Second call should return cached result without hitting database
    $ports2 = $repository->getUsedPorts();
    expect($ports2)->toContain(8000, 8001);
});

it('clears cache when clearUsedPortsCache is called', function () {
    Project::factory()->create(['port' => 8000]);

    $repository = new ProjectRepository;
    $repository->getUsedPorts();

    expect(Cache::has('projects.used_ports'))->toBeTrue();

    $repository->clearUsedPortsCache();

    expect(Cache::has('projects.used_ports'))->toBeFalse();
});

it('respects cache ttl of 60 seconds', function () {
    Project::factory()->create(['port' => 8000]);

    $repository = new ProjectRepository;
    $repository->getUsedPorts();

    // Verify cache entry has TTL
    $cacheItem = Cache::get('projects.used_ports');
    expect($cacheItem)->not->toBeNull();

    // After 61 seconds, cache should expire
    test()->travel(61)->seconds();

    expect(Cache::has('projects.used_ports'))->toBeFalse();
});

it('filters out null ports when caching', function () {
    Project::factory()->create(['port' => null]);
    Project::factory()->create(['port' => 8000]);

    $repository = new ProjectRepository;
    $ports = $repository->getUsedPorts();

    expect($ports)->toContain(8000)
        ->and($ports)->not->toContain(null);
});
