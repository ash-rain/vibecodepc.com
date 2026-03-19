<?php

declare(strict_types=1);

use App\Models\AnalyticsEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ============================================================================
// Event Creation Tests
// ============================================================================

it('can create an analytics event', function () {
    $event = AnalyticsEvent::create([
        'event_type' => 'test.event',
        'category' => 'test',
        'properties' => ['key' => 'value'],
        'occurred_at' => now(),
    ]);

    expect($event)->toBeInstanceOf(AnalyticsEvent::class)
        ->and($event->event_type)->toBe('test.event')
        ->and($event->category)->toBe('test')
        ->and($event->properties)->toBe(['key' => 'value'])
        ->and($event->occurred_at)->toBeInstanceOf(DateTime::class);
});

it('can create an event using factory', function () {
    $event = AnalyticsEvent::factory()->create();

    expect($event->id)->not->toBeNull()
        ->and($event->event_type)->toBeString()
        ->and($event->category)->toBeIn(['tunnel', 'wizard', 'project']);
});

it('can create an event without properties', function () {
    $event = AnalyticsEvent::create([
        'event_type' => 'simple.event',
        'category' => 'test',
        'occurred_at' => now(),
    ]);

    expect($event->properties)->toBeNull();
});

it('can create an event without category', function () {
    $event = AnalyticsEvent::create([
        'event_type' => 'uncategorized.event',
        'properties' => ['data' => 'test'],
        'occurred_at' => now(),
    ]);

    expect($event->category)->toBeNull();
});

it('creates event with timestamps automatically', function () {
    $beforeCreate = now()->subSecond();
    $event = AnalyticsEvent::factory()->create();
    $afterCreate = now()->addSecond();

    expect($event->created_at)->toBeInstanceOf(DateTime::class)
        ->and($event->created_at->greaterThanOrEqualTo($beforeCreate))->toBeTrue()
        ->and($event->created_at->lessThanOrEqualTo($afterCreate))->toBeTrue();
});

// ============================================================================
// Property Storage Tests
// ============================================================================

it('stores properties as json', function () {
    $properties = [
        'user_id' => 123,
        'action' => 'click',
        'metadata' => ['page' => 'dashboard', 'section' => 'header'],
    ];

    $event = AnalyticsEvent::create([
        'event_type' => 'test.event',
        'properties' => $properties,
        'occurred_at' => now(),
    ]);

    expect($event->properties)->toBeArray()
        ->and($event->properties['user_id'])->toBe(123)
        ->and($event->properties['action'])->toBe('click')
        ->and($event->properties['metadata'])->toBeArray()
        ->and($event->properties['metadata']['page'])->toBe('dashboard');
});

it('casts properties to array automatically', function () {
    $event = AnalyticsEvent::create([
        'event_type' => 'test.event',
        'properties' => ['key' => 'value'],
        'occurred_at' => now(),
    ]);

    expect($event->properties)->toBeArray()
        ->and($event->properties)->toHaveKey('key');
});

it('handles null properties correctly', function () {
    $event = AnalyticsEvent::factory()->create(['properties' => null]);

    expect($event->properties)->toBeNull();
});

it('handles empty array properties correctly', function () {
    $event = AnalyticsEvent::factory()->create(['properties' => []]);

    expect($event->properties)->toBeArray()->toBeEmpty();
});

it('handles complex nested properties', function () {
    $complexProperties = [
        'level_1' => [
            'level_2' => [
                'level_3' => 'deep_value',
                'numbers' => [1, 2, 3, 4, 5],
            ],
            'boolean' => true,
            'null_value' => null,
        ],
    ];

    $event = AnalyticsEvent::create([
        'event_type' => 'complex.event',
        'properties' => $complexProperties,
        'occurred_at' => now(),
    ]);

    expect($event->properties['level_1']['level_2']['level_3'])->toBe('deep_value')
        ->and($event->properties['level_1']['level_2']['numbers'])->toBe([1, 2, 3, 4, 5])
        ->and($event->properties['level_1']['boolean'])->toBeTrue()
        ->and($event->properties['level_1']['null_value'])->toBeNull();
});

it('persists properties correctly on retrieval', function () {
    $originalProperties = ['session_id' => 'abc123', 'timestamp' => time()];

    $event = AnalyticsEvent::factory()->create([
        'event_type' => 'persist.test',
        'properties' => $originalProperties,
    ]);

    $retrieved = AnalyticsEvent::find($event->id);

    expect($retrieved->properties)->toBe($originalProperties);
});

// ============================================================================
// Casts Tests
// ============================================================================

it('casts occurred_at to datetime', function () {
    $specificTime = now()->setTime(14, 30, 0);

    $event = AnalyticsEvent::factory()->create([
        'occurred_at' => $specificTime,
    ]);

    expect($event->occurred_at)->toBeInstanceOf(Carbon\Carbon::class)
        ->and($event->occurred_at->format('H:i'))->toBe('14:30');
});

it('casts properties to array', function () {
    $event = AnalyticsEvent::factory()->create([
        'properties' => ['foo' => 'bar'],
    ]);

    expect($event->properties)->toBeArray()
        ->and($event->properties['foo'])->toBe('bar');
});

// ============================================================================
// Query Scope Tests
// ============================================================================

it('scopes events by type', function () {
    AnalyticsEvent::factory()->tunnelCompleted()->create();
    AnalyticsEvent::factory()->tunnelSkipped()->create();
    AnalyticsEvent::factory()->wizardEvent()->create();
    AnalyticsEvent::factory()->state(['event_type' => 'custom.event'])->create();

    $tunnelCompleted = AnalyticsEvent::type('tunnel.completed')->get();

    expect($tunnelCompleted)->toHaveCount(1)
        ->and($tunnelCompleted->first()->event_type)->toBe('tunnel.completed');
});

it('scopes events by category', function () {
    AnalyticsEvent::factory()->tunnelCompleted()->create();
    AnalyticsEvent::factory()->wizardEvent()->create();
    AnalyticsEvent::factory()->state(['category' => 'system'])->create();

    $tunnelEvents = AnalyticsEvent::category('tunnel')->get();

    expect($tunnelEvents)->toHaveCount(1)
        ->and($tunnelEvents->first()->category)->toBe('tunnel');
});

it('scopes events by occurred between dates', function () {
    $yesterday = now()->subDay();
    $today = now();
    $tomorrow = now()->addDay();

    AnalyticsEvent::factory()->create(['occurred_at' => $yesterday]);
    AnalyticsEvent::factory()->create(['occurred_at' => $today]);
    AnalyticsEvent::factory()->create(['occurred_at' => $tomorrow]);

    $events = AnalyticsEvent::occurredBetween($today, $tomorrow)->get();

    expect($events)->toHaveCount(2);
});

it('chains multiple scopes together', function () {
    AnalyticsEvent::factory()->tunnelCompleted()->create(['occurred_at' => now()->subDays(2)]);
    AnalyticsEvent::factory()->tunnelCompleted()->create(['occurred_at' => now()]);
    AnalyticsEvent::factory()->tunnelSkipped()->create(['occurred_at' => now()]);
    AnalyticsEvent::factory()->wizardEvent()->create(['occurred_at' => now()]);

    $results = AnalyticsEvent::type('tunnel.completed')
        ->category('tunnel')
        ->occurredBetween(now()->subDay(), now()->addDay())
        ->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->event_type)->toBe('tunnel.completed');
});

// ============================================================================
// Aggregation Query Tests
// ============================================================================

it('counts events by type', function () {
    AnalyticsEvent::factory()->tunnelCompleted()->count(3)->create();
    AnalyticsEvent::factory()->tunnelSkipped()->count(2)->create();
    AnalyticsEvent::factory()->wizardEvent()->count(5)->create();

    $tunnelCompletedCount = AnalyticsEvent::type('tunnel.completed')->count();
    $tunnelSkippedCount = AnalyticsEvent::type('tunnel.skipped')->count();
    $wizardCount = AnalyticsEvent::type('wizard.completed')->count();

    expect($tunnelCompletedCount)->toBe(3)
        ->and($tunnelSkippedCount)->toBe(2)
        ->and($wizardCount)->toBe(5);
});

it('groups events by event_type', function () {
    AnalyticsEvent::factory()->tunnelCompleted()->count(3)->create();
    AnalyticsEvent::factory()->tunnelSkipped()->count(2)->create();
    AnalyticsEvent::factory()->wizardEvent()->count(1)->create();

    $grouped = AnalyticsEvent::selectRaw('event_type, COUNT(*) as count')
        ->groupBy('event_type')
        ->pluck('count', 'event_type')
        ->toArray();

    expect($grouped)->toHaveCount(3)
        ->and($grouped['tunnel.completed'])->toBe(3)
        ->and($grouped['tunnel.skipped'])->toBe(2)
        ->and($grouped['wizard.completed'])->toBe(1);
});

it('orders events by occurred_at', function () {
    $oldest = AnalyticsEvent::factory()->create(['occurred_at' => now()->subDays(5)]);
    $middle = AnalyticsEvent::factory()->create(['occurred_at' => now()->subDays(3)]);
    $newest = AnalyticsEvent::factory()->create(['occurred_at' => now()]);

    $ordered = AnalyticsEvent::orderBy('occurred_at')->get();

    expect($ordered->first()->id)->toBe($oldest->id)
        ->and($ordered->last()->id)->toBe($newest->id);
});

it('orders events by occurred_at descending', function () {
    $oldest = AnalyticsEvent::factory()->create(['occurred_at' => now()->subDays(5)]);
    $middle = AnalyticsEvent::factory()->create(['occurred_at' => now()->subDays(3)]);
    $newest = AnalyticsEvent::factory()->create(['occurred_at' => now()]);

    $ordered = AnalyticsEvent::orderByDesc('occurred_at')->get();

    expect($ordered->first()->id)->toBe($newest->id)
        ->and($ordered->last()->id)->toBe($oldest->id);
});

it('filters events by type and aggregates counts', function () {
    AnalyticsEvent::factory()->tunnelCompleted()->create();
    AnalyticsEvent::factory()->tunnelSkipped()->create();
    AnalyticsEvent::factory()->state(['event_type' => 'other.event'])->create();

    $aggregated = AnalyticsEvent::selectRaw('event_type, COUNT(*) as count')
        ->groupBy('event_type')
        ->pluck('count', 'event_type')
        ->toArray();

    expect($aggregated)->toHaveCount(3)
        ->and($aggregated)->toHaveKey('tunnel.completed')
        ->and($aggregated)->toHaveKey('tunnel.skipped')
        ->and($aggregated)->toHaveKey('other.event');
});

it('aggregates events with null categories separately', function () {
    AnalyticsEvent::factory()->create([
        'event_type' => 'uncategorized',
        'category' => null,
    ]);
    AnalyticsEvent::factory()->create([
        'event_type' => 'uncategorized',
        'category' => null,
    ]);
    AnalyticsEvent::factory()->create([
        'event_type' => 'uncategorized',
        'category' => 'test',
    ]);

    $results = AnalyticsEvent::selectRaw('category, COUNT(*) as count')
        ->groupBy('category')
        ->pluck('count', 'category')
        ->toArray();

    expect($results)->toHaveCount(2)
        ->and($results['test'])->toBe(1);
});

// ============================================================================
// Factory State Tests
// ============================================================================

it('factory tunnelCompleted state creates tunnel.completed event', function () {
    $event = AnalyticsEvent::factory()->tunnelCompleted()->create();

    expect($event->event_type)->toBe('tunnel.completed')
        ->and($event->category)->toBe('tunnel')
        ->and($event->properties)->toHaveKey('subdomain')
        ->and($event->properties['subdomain'])->toBe('test-device');
});

it('factory tunnelSkipped state creates tunnel.skipped event', function () {
    $event = AnalyticsEvent::factory()->tunnelSkipped()->create();

    expect($event->event_type)->toBe('tunnel.skipped')
        ->and($event->category)->toBe('tunnel')
        ->and($event->properties)->toHaveKey('reason')
        ->and($event->properties['reason'])->toBe('user_choice');
});

it('factory wizardEvent state creates wizard.completed event', function () {
    $event = AnalyticsEvent::factory()->wizardEvent()->create();

    expect($event->event_type)->toBe('wizard.completed')
        ->and($event->category)->toBe('wizard')
        ->and($event->properties)->toHaveKey('step')
        ->and($event->properties['step'])->toBe('welcome');
});

// ============================================================================
// Edge Cases
// ============================================================================

it('handles very long event_type values', function () {
    $longEventType = str_repeat('a', 100);

    $event = AnalyticsEvent::factory()->create([
        'event_type' => $longEventType,
    ]);

    expect($event->event_type)->toBe($longEventType);
});

it('handles unicode characters in properties', function () {
    $event = AnalyticsEvent::factory()->create([
        'properties' => ['message' => 'Hello ðŸŽ‰ æµ‹è¯• émoji', 'emoji' => 'ðŸš€'],
    ]);

    expect($event->properties['message'])->toBe('Hello ðŸŽ‰ æµ‹è¯• émoji')
        ->and($event->properties['emoji'])->toBe('ðŸš€');
});

it('returns empty collection when no events match scope', function () {
    AnalyticsEvent::factory()->tunnelCompleted()->create();

    $results = AnalyticsEvent::type('nonexistent.event')->get();

    expect($results)->toBeEmpty();
});

it('handles exact boundary date in occurredBetween scope', function () {
    $exactTime = now()->setTime(12, 0, 0);

    AnalyticsEvent::factory()->create(['occurred_at' => $exactTime]);

    $results = AnalyticsEvent::occurredBetween($exactTime, $exactTime->copy()->addSecond())->get();

    expect($results)->toHaveCount(1);
});

it('fillable attributes are mass assignable', function () {
    $event = new AnalyticsEvent([
        'event_type' => 'mass.assigned',
        'category' => 'test',
        'properties' => ['key' => 'value'],
        'occurred_at' => now(),
    ]);

    expect($event->event_type)->toBe('mass.assigned')
        ->and($event->category)->toBe('test')
        ->and($event->properties)->toBe(['key' => 'value']);
});
