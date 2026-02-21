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
