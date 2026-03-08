<?php

declare(strict_types=1);

use App\Models\AnalyticsEvent;

it('scopes events by type', function () {
    AnalyticsEvent::factory()->tunnelCompleted()->create();
    AnalyticsEvent::factory()->tunnelSkipped()->create();
    AnalyticsEvent::factory()->state(['event_type' => 'other.event'])->create();

    $completed = AnalyticsEvent::type('tunnel.completed')->get();

    expect($completed)->toHaveCount(1)
        ->and($completed->first()->event_type)->toBe('tunnel.completed');
});

it('returns empty collection when type scope matches no events', function () {
    AnalyticsEvent::factory()->tunnelCompleted()->create();
    AnalyticsEvent::factory()->tunnelSkipped()->create();

    $result = AnalyticsEvent::type('nonexistent.type')->get();

    expect($result)->toHaveCount(0);
});

it('scopes events by category', function () {
    AnalyticsEvent::factory()->tunnelCompleted()->create();
    AnalyticsEvent::factory()->state(['category' => 'other'])->create();
    AnalyticsEvent::factory()->state(['category' => 'wizard'])->create();

    $tunnelEvents = AnalyticsEvent::category('tunnel')->get();

    expect($tunnelEvents)->toHaveCount(1)
        ->and($tunnelEvents->first()->category)->toBe('tunnel');
});

it('returns empty collection when category scope matches no events', function () {
    AnalyticsEvent::factory()->tunnelCompleted()->create();
    AnalyticsEvent::factory()->tunnelSkipped()->create();

    $result = AnalyticsEvent::category('nonexistent')->get();

    expect($result)->toHaveCount(0);
});

it('scopes events by occurred between dates', function () {
    AnalyticsEvent::factory()->tunnelCompleted()->create([
        'occurred_at' => now()->subDays(10),
    ]);
    AnalyticsEvent::factory()->tunnelCompleted()->create([
        'occurred_at' => now()->subDays(2),
    ]);
    AnalyticsEvent::factory()->tunnelCompleted()->create([
        'occurred_at' => now(),
    ]);

    $events = AnalyticsEvent::occurredBetween(now()->subDays(5), now())->get();

    expect($events)->toHaveCount(2);
});

it('returns empty collection when occurred between scope matches no events', function () {
    AnalyticsEvent::factory()->tunnelCompleted()->create([
        'occurred_at' => now()->subDays(10),
    ]);
    AnalyticsEvent::factory()->tunnelCompleted()->create([
        'occurred_at' => now()->subDays(8),
    ]);

    $events = AnalyticsEvent::occurredBetween(now()->subDays(5), now())->get();

    expect($events)->toHaveCount(0);
});

it('chains type and category scopes together', function () {
    AnalyticsEvent::factory()->tunnelCompleted()->create();
    AnalyticsEvent::factory()->tunnelSkipped()->create();
    AnalyticsEvent::factory()->state([
        'event_type' => 'wizard.completed',
        'category' => 'wizard',
    ])->create();

    $result = AnalyticsEvent::type('tunnel.completed')
        ->category('tunnel')
        ->get();

    expect($result)->toHaveCount(1)
        ->and($result->first()->event_type)->toBe('tunnel.completed')
        ->and($result->first()->category)->toBe('tunnel');
});

it('chains all scopes together', function () {
    AnalyticsEvent::factory()->tunnelCompleted()->create([
        'occurred_at' => now()->subDays(10),
    ]);
    AnalyticsEvent::factory()->tunnelCompleted()->create([
        'occurred_at' => now()->subDays(2),
    ]);
    AnalyticsEvent::factory()->tunnelSkipped()->create([
        'occurred_at' => now()->subDays(1),
    ]);

    $result = AnalyticsEvent::type('tunnel.completed')
        ->category('tunnel')
        ->occurredBetween(now()->subDays(5), now())
        ->get();

    expect($result)->toHaveCount(1)
        ->and($result->first()->event_type)->toBe('tunnel.completed');
});

it('handles occurred between with exact boundary dates', function () {
    $boundaryDate = now()->subDays(5)->startOfDay();

    AnalyticsEvent::factory()->tunnelCompleted()->create([
        'occurred_at' => $boundaryDate,
    ]);
    AnalyticsEvent::factory()->tunnelCompleted()->create([
        'occurred_at' => $boundaryDate->copy()->addHour(),
    ]);

    $events = AnalyticsEvent::occurredBetween($boundaryDate, now())->get();

    expect($events)->toHaveCount(2);
});

it('handles type scope with special characters in type name', function () {
    AnalyticsEvent::factory()->state(['event_type' => 'event.type.with.dots'])->create();
    AnalyticsEvent::factory()->state(['event_type' => 'event-type-with-dashes'])->create();
    AnalyticsEvent::factory()->state(['event_type' => 'event_type_with_underscores'])->create();

    $dotted = AnalyticsEvent::type('event.type.with.dots')->get();
    $dashed = AnalyticsEvent::type('event-type-with-dashes')->get();
    $underscored = AnalyticsEvent::type('event_type_with_underscores')->get();

    expect($dotted)->toHaveCount(1)
        ->and($dashed)->toHaveCount(1)
        ->and($underscored)->toHaveCount(1);
});

it('scopes work with first() and find() methods', function () {
    $event = AnalyticsEvent::factory()->tunnelCompleted()->create();
    AnalyticsEvent::factory()->tunnelSkipped()->create();

    $found = AnalyticsEvent::type('tunnel.completed')->first();
    $notFound = AnalyticsEvent::type('nonexistent')->first();

    expect($found->id)->toBe($event->id)
        ->and($notFound)->toBeNull();
});

it('scopes preserve existing query builder constraints', function () {
    AnalyticsEvent::factory()->tunnelCompleted()->create([
        'properties' => ['subdomain' => 'test1'],
    ]);
    AnalyticsEvent::factory()->tunnelCompleted()->create([
        'properties' => ['subdomain' => 'test2'],
    ]);

    $result = AnalyticsEvent::whereJsonContains('properties->subdomain', 'test1')
        ->type('tunnel.completed')
        ->get();

    expect($result)->toHaveCount(1)
        ->and($result->first()->properties['subdomain'])->toBe('test1');
});
