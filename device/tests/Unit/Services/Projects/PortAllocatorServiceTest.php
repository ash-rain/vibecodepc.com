<?php

declare(strict_types=1);

use App\Models\Project;
use App\Services\Projects\PortAllocatorService;
use VibecodePC\Common\Enums\ProjectFramework;

it('allocates the default port for a framework', function () {
    $service = new PortAllocatorService;

    expect($service->allocate(ProjectFramework::Laravel))->toBe(8000);
    expect($service->allocate(ProjectFramework::NextJs))->toBe(3000);
});

it('skips used ports', function () {
    Project::factory()->create(['port' => 8000]);

    $service = new PortAllocatorService;

    expect($service->allocate(ProjectFramework::Laravel))->toBe(8001);
});

it('validates port is within valid range 1024-65535', function () {
    $service = new PortAllocatorService;

    $port = $service->allocate(ProjectFramework::Laravel);

    expect($port)->toBeGreaterThanOrEqual(1024)
        ->and($port)->toBeLessThanOrEqual(65535);
});

it('continues searching until finding an available port', function () {
    // Fill several ports starting from Laravel default
    Project::factory()->create(['port' => 8000]);
    Project::factory()->create(['port' => 8001]);
    Project::factory()->create(['port' => 8002]);

    $service = new PortAllocatorService;

    expect($service->allocate(ProjectFramework::Laravel))->toBe(8003);
});

it('handles multiple frameworks independently', function () {
    // Laravel project using port 8000
    Project::factory()->create(['port' => 8000]);

    // NextJs project using port 3000
    Project::factory()->create(['port' => 3000]);

    $service = new PortAllocatorService;

    // Laravel should get 8001
    expect($service->allocate(ProjectFramework::Laravel))->toBe(8001);

    // NextJs should get 3001
    expect($service->allocate(ProjectFramework::NextJs))->toBe(3001);
});

it('allocates ports incrementally', function () {
    Project::factory()->create(['port' => 8000]);
    Project::factory()->create(['port' => 8002]);

    $service = new PortAllocatorService;

    // Should allocate 8001 (the first gap), not 8003
    expect($service->allocate(ProjectFramework::Laravel))->toBe(8001);
});
