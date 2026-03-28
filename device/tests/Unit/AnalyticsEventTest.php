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

// Event Creation Tests
it('creates event with required fields', function () {
    $event = AnalyticsEvent::create([
        'event_type' => 'test.event',
        'category' => 'test',
        'occurred_at' => now(),
    ]);

    expect($event->id)->toBeInt()
        ->and($event->event_type)->toBe('test.event')
        ->and($event->category)->toBe('test')
        ->and($event->properties)->toBeNull()
        ->and($event->occurred_at)->toBeInstanceOf(\Carbon\Carbon::class);
});

it('creates event with all fields', function () {
    $now = now();
    $event = AnalyticsEvent::create([
        'event_type' => 'wizard.completed',
        'category' => 'wizard',
        'properties' => ['step' => 'welcome', 'user_id' => 123],
        'occurred_at' => $now,
    ]);

    expect($event->event_type)->toBe('wizard.completed')
        ->and($event->category)->toBe('wizard')
        ->and($event->properties)->toBe(['step' => 'welcome', 'user_id' => 123])
        ->and($event->occurred_at)->toBeInstanceOf(\Carbon\Carbon::class);
});

it('creates event using factory', function () {
    $event = AnalyticsEvent::factory()->create();

    expect($event->id)->toBeInt()
        ->and($event->event_type)->not->toBeEmpty()
        ->and($event->occurred_at)->toBeInstanceOf(\Carbon\Carbon::class);
});

it('persists event to database', function () {
    $event = AnalyticsEvent::create([
        'event_type' => 'persist.test',
        'occurred_at' => now(),
    ]);

    $retrieved = AnalyticsEvent::find($event->id);

    expect($retrieved)->not->toBeNull()
        ->and($retrieved->event_type)->toBe('persist.test');
});

it('allows null properties', function () {
    $event = AnalyticsEvent::create([
        'event_type' => 'no.properties',
        'properties' => null,
        'occurred_at' => now(),
    ]);

    expect($event->properties)->toBeNull();
});

it('allows empty array properties', function () {
    $event = AnalyticsEvent::create([
        'event_type' => 'empty.properties',
        'properties' => [],
        'occurred_at' => now(),
    ]);

    expect($event->properties)->toBe([]);
});

it('handles event type with special characters', function () {
    $event = AnalyticsEvent::create([
        'event_type' => 'test:event@v1.0',
        'occurred_at' => now(),
    ]);

    expect($event->event_type)->toBe('test:event@v1.0');
});

it('sets timestamps automatically', function () {
    $event = AnalyticsEvent::create([
        'event_type' => 'timestamp.test',
        'occurred_at' => now(),
    ]);

    expect($event->created_at)->toBeInstanceOf(\Carbon\Carbon::class)
        ->and($event->updated_at)->toBeInstanceOf(\Carbon\Carbon::class);
});

// Aggregation Query Tests
it('counts events by type', function () {
    AnalyticsEvent::factory()->tunnelCompleted()->create();
    AnalyticsEvent::factory()->tunnelCompleted()->create();
    AnalyticsEvent::factory()->tunnelSkipped()->create();
    AnalyticsEvent::factory()->wizardEvent()->create();

    $count = AnalyticsEvent::type('tunnel.completed')->count();

    expect($count)->toBe(2);
});

it('counts total events', function () {
    AnalyticsEvent::factory()->count(5)->create();

    $count = AnalyticsEvent::count();

    expect($count)->toBe(5);
});

it('aggregates events by event_type', function () {
    AnalyticsEvent::factory()->tunnelCompleted()->create();
    AnalyticsEvent::factory()->tunnelCompleted()->create();
    AnalyticsEvent::factory()->tunnelSkipped()->create();
    AnalyticsEvent::factory()->wizardEvent()->create();

    $aggregated = AnalyticsEvent::selectRaw('event_type, COUNT(*) as count')
        ->groupBy('event_type')
        ->pluck('count', 'event_type')
        ->toArray();

    expect($aggregated)->toBe([
        'tunnel.completed' => 2,
        'tunnel.skipped' => 1,
        'wizard.completed' => 1,
    ]);
});

it('aggregates events by category', function () {
    AnalyticsEvent::factory()->tunnelCompleted()->create();
    AnalyticsEvent::factory()->tunnelSkipped()->create();
    AnalyticsEvent::factory()->wizardEvent()->create();

    $aggregated = AnalyticsEvent::selectRaw('category, COUNT(*) as count')
        ->groupBy('category')
        ->pluck('count', 'category')
        ->toArray();

    expect($aggregated['tunnel'])->toBe(2)
        ->and($aggregated['wizard'])->toBe(1);
});

it('gets min and max occurred_at dates', function () {
    AnalyticsEvent::factory()->create(['occurred_at' => now()->subDays(10)]);
    AnalyticsEvent::factory()->create(['occurred_at' => now()->subDays(5)]);
    AnalyticsEvent::factory()->create(['occurred_at' => now()]);

    $minDate = AnalyticsEvent::min('occurred_at');
    $maxDate = AnalyticsEvent::max('occurred_at');

    expect($minDate)->not->toBeNull()
        ->and($maxDate)->not->toBeNull()
        ->and((int) \Carbon\Carbon::parse($minDate)->diffInDays(\Carbon\Carbon::parse($maxDate)))->toBe(10);
});

it('aggregates with date grouping', function () {
    AnalyticsEvent::factory()->create(['occurred_at' => now()->subDays(2)]);
    AnalyticsEvent::factory()->create(['occurred_at' => now()->subDays(2)]);
    AnalyticsEvent::factory()->create(['occurred_at' => now()->subDays(1)]);
    AnalyticsEvent::factory()->create(['occurred_at' => now()]);

    $daily = AnalyticsEvent::selectRaw('DATE(occurred_at) as date, COUNT(*) as count')
        ->groupBy('date')
        ->orderBy('date')
        ->pluck('count', 'date')
        ->toArray();

    expect($daily)->toHaveCount(3);
});

it('returns zero count for empty results', function () {
    $count = AnalyticsEvent::type('nonexistent')->count();

    expect($count)->toBe(0);
});

it('aggregates with having clause', function () {
    AnalyticsEvent::factory()->tunnelCompleted()->create();
    AnalyticsEvent::factory()->tunnelCompleted()->create();
    AnalyticsEvent::factory()->tunnelSkipped()->create();

    $result = AnalyticsEvent::selectRaw('event_type, COUNT(*) as count')
        ->groupBy('event_type')
        ->havingRaw('COUNT(*) > 1')
        ->pluck('count', 'event_type')
        ->toArray();

    expect($result)->toHaveCount(1)
        ->and($result['tunnel.completed'])->toBe(2);
});

// Property Storage Tests
it('stores properties as array', function () {
    $properties = ['key1' => 'value1', 'key2' => 'value2'];

    $event = AnalyticsEvent::create([
        'event_type' => 'properties.test',
        'properties' => $properties,
        'occurred_at' => now(),
    ]);

    expect($event->properties)->toBeArray()
        ->and($event->properties)->toBe($properties);
});

it('retrieves properties from database', function () {
    $properties = ['nested' => ['deep' => 'value'], 'simple' => 'test'];

    $event = AnalyticsEvent::create([
        'event_type' => 'properties.retrieve',
        'properties' => $properties,
        'occurred_at' => now(),
    ]);

    $retrieved = AnalyticsEvent::find($event->id);

    expect($retrieved->properties)->toBe($properties);
});

it('stores nested properties', function () {
    $properties = [
        'user' => ['id' => 123, 'name' => 'Test User'],
        'context' => ['page' => '/dashboard', 'referrer' => '/login'],
    ];

    $event = AnalyticsEvent::create([
        'event_type' => 'nested.properties',
        'properties' => $properties,
        'occurred_at' => now(),
    ]);

    expect($event->properties['user']['id'])->toBe(123)
        ->and($event->properties['user']['name'])->toBe('Test User')
        ->and($event->properties['context']['page'])->toBe('/dashboard');
});

it('handles unicode in properties', function () {
    $properties = ['message' => 'Hello 世界', 'emoji' => '🎉🚀'];

    $event = AnalyticsEvent::create([
        'event_type' => 'unicode.test',
        'properties' => $properties,
        'occurred_at' => now(),
    ]);

    $retrieved = AnalyticsEvent::find($event->id);

    expect($retrieved->properties['message'])->toBe('Hello 世界')
        ->and($retrieved->properties['emoji'])->toBe('🎉🚀');
});

it('queries by nested property value', function () {
    AnalyticsEvent::factory()->create([
        'event_type' => 'query.nested',
        'properties' => ['user' => ['id' => 123, 'role' => 'admin']],
    ]);
    AnalyticsEvent::factory()->create([
        'event_type' => 'query.nested',
        'properties' => ['user' => ['id' => 456, 'role' => 'user']],
    ]);

    $result = AnalyticsEvent::whereJsonContains('properties->user->role', 'admin')->get();

    expect($result)->toHaveCount(1)
        ->and($result->first()->properties['user']['id'])->toBe(123);
});

it('updates properties', function () {
    $event = AnalyticsEvent::create([
        'event_type' => 'update.properties',
        'properties' => ['status' => 'pending'],
        'occurred_at' => now(),
    ]);

    $event->update(['properties' => ['status' => 'completed', 'duration' => 120]]);

    $retrieved = AnalyticsEvent::find($event->id);

    expect($retrieved->properties['status'])->toBe('completed')
        ->and($retrieved->properties['duration'])->toBe(120);
});

it('handles large property values', function () {
    $largeValue = str_repeat('x', 10000);
    $properties = ['large_data' => $largeValue];

    $event = AnalyticsEvent::create([
        'event_type' => 'large.properties',
        'properties' => $properties,
        'occurred_at' => now(),
    ]);

    $retrieved = AnalyticsEvent::find($event->id);

    expect($retrieved->properties['large_data'])->toBe($largeValue);
});

it('stores numeric values in properties', function () {
    $properties = ['int' => 42, 'float' => 3.14, 'zero' => 0, 'negative' => -10];

    $event = AnalyticsEvent::create([
        'event_type' => 'numeric.properties',
        'properties' => $properties,
        'occurred_at' => now(),
    ]);

    expect($event->properties['int'])->toBe(42)
        ->and($event->properties['float'])->toBe(3.14)
        ->and($event->properties['zero'])->toBe(0)
        ->and($event->properties['negative'])->toBe(-10);
});

it('stores boolean values in properties', function () {
    $properties = ['active' => true, 'verified' => false];

    $event = AnalyticsEvent::create([
        'event_type' => 'boolean.properties',
        'properties' => $properties,
        'occurred_at' => now(),
    ]);

    expect($event->properties['active'])->toBeTrue()
        ->and($event->properties['verified'])->toBeFalse();
});

it('returns null when accessing missing property key', function () {
    $event = AnalyticsEvent::create([
        'event_type' => 'missing.property',
        'properties' => ['exists' => 'yes'],
        'occurred_at' => now(),
    ]);

    expect($event->properties['nonexistent'] ?? null)->toBeNull();
});

it('handles properties with special characters in keys', function () {
    $properties = ['key.with.dots' => 'value', 'key-with-dashes' => 'value2', 'key_with_underscores' => 'value3'];

    $event = AnalyticsEvent::create([
        'event_type' => 'special.keys',
        'properties' => $properties,
        'occurred_at' => now(),
    ]);

    expect($event->properties['key.with.dots'])->toBe('value')
        ->and($event->properties['key-with-dashes'])->toBe('value2')
        ->and($event->properties['key_with_underscores'])->toBe('value3');
});

it('handles properties updated to null', function () {
    $event = AnalyticsEvent::create([
        'event_type' => 'nullify.properties',
        'properties' => ['data' => 'present'],
        'occurred_at' => now(),
    ]);

    $event->update(['properties' => null]);

    $retrieved = AnalyticsEvent::find($event->id);

    expect($retrieved->properties)->toBeNull();
});
