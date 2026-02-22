<?php

declare(strict_types=1);

use VibecodePC\Common\Enums\ProjectStatus;

it('has Scaffolding case with correct label and color', function () {
    $status = ProjectStatus::Scaffolding;

    expect($status->label())->toBe('Scaffolding')
        ->and($status->color())->toBe('blue');
});

it('has Cloning case with correct label and color', function () {
    $status = ProjectStatus::Cloning;

    expect($status->label())->toBe('Cloning')
        ->and($status->color())->toBe('blue');
});

it('has correct labels for all cases', function (ProjectStatus $status, string $expectedLabel) {
    expect($status->label())->toBe($expectedLabel);
})->with([
    [ProjectStatus::Scaffolding, 'Scaffolding'],
    [ProjectStatus::Cloning, 'Cloning'],
    [ProjectStatus::Created, 'Created'],
    [ProjectStatus::Running, 'Running'],
    [ProjectStatus::Stopped, 'Stopped'],
    [ProjectStatus::Error, 'Error'],
]);

it('has correct colors for all cases', function (ProjectStatus $status, string $expectedColor) {
    expect($status->color())->toBe($expectedColor);
})->with([
    [ProjectStatus::Scaffolding, 'blue'],
    [ProjectStatus::Cloning, 'blue'],
    [ProjectStatus::Created, 'gray'],
    [ProjectStatus::Running, 'green'],
    [ProjectStatus::Stopped, 'amber'],
    [ProjectStatus::Error, 'red'],
]);
