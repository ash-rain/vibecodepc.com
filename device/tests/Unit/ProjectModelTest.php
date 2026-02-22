<?php

declare(strict_types=1);

use App\Models\Project;
use VibecodePC\Common\Enums\ProjectStatus;

it('returns true for isProvisioning when status is Scaffolding', function () {
    $project = Project::factory()->create(['status' => ProjectStatus::Scaffolding]);

    expect($project->isProvisioning())->toBeTrue();
});

it('returns true for isProvisioning when status is Cloning', function () {
    $project = Project::factory()->create(['status' => ProjectStatus::Cloning]);

    expect($project->isProvisioning())->toBeTrue();
});

it('returns false for isProvisioning when status is Created', function () {
    $project = Project::factory()->create(['status' => ProjectStatus::Created]);

    expect($project->isProvisioning())->toBeFalse();
});

it('returns false for isProvisioning when status is Running', function () {
    $project = Project::factory()->create(['status' => ProjectStatus::Running]);

    expect($project->isProvisioning())->toBeFalse();
});

it('returns false for isProvisioning when status is Error', function () {
    $project = Project::factory()->create(['status' => ProjectStatus::Error]);

    expect($project->isProvisioning())->toBeFalse();
});
