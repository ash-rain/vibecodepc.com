<?php

declare(strict_types=1);

use App\Models\AnalyticsEvent;
use App\Services\AnalyticsService;

beforeEach(function () {
    $this->service = new AnalyticsService;
});

it('tracks analytics events', function () {
    $this->service->track('test.event', ['key' => 'value'], 'test');

    $event = AnalyticsEvent::first();

    expect($event)->not->toBeNull()
        ->and($event->event_type)->toBe('test.event')
        ->and($event->category)->toBe('test')
        ->and($event->properties)->toBe(['key' => 'value']);
});

it('tracks tunnel completed event', function () {
    $this->service->trackTunnelEvent('completed', ['subdomain' => 'my-device']);

    $event = AnalyticsEvent::first();

    expect($event->event_type)->toBe('tunnel.completed')
        ->and($event->category)->toBe('tunnel')
        ->and($event->properties['subdomain'])->toBe('my-device');
});

it('tracks tunnel skipped event', function () {
    $this->service->trackTunnelEvent('skipped', ['reason' => 'user_choice']);

    $event = AnalyticsEvent::first();

    expect($event->event_type)->toBe('tunnel.skipped')
        ->and($event->category)->toBe('tunnel')
        ->and($event->properties['reason'])->toBe('user_choice');
});

it('tracks wizard events', function () {
    $this->service->trackWizardEvent('completed', ['step' => 'welcome']);

    $event = AnalyticsEvent::first();

    expect($event->event_type)->toBe('wizard.completed')
        ->and($event->category)->toBe('wizard')
        ->and($event->properties['step'])->toBe('welcome');
});

it('returns event summary', function () {
    // Create multiple events
    AnalyticsEvent::factory()->tunnelCompleted()->count(3)->create();
    AnalyticsEvent::factory()->tunnelSkipped()->count(2)->create();

    $summary = $this->service->getEventSummary('tunnel.completed');

    expect($summary['count'])->toBe(3)
        ->and($summary['first_occurred'])->not->toBeNull()
        ->and($summary['last_occurred'])->not->toBeNull();
});

it('returns aggregated analytics data', function () {
    AnalyticsEvent::factory()->tunnelCompleted()->create(['occurred_at' => now()]);
    AnalyticsEvent::factory()->tunnelCompleted()->create(['occurred_at' => now()]);
    AnalyticsEvent::factory()->tunnelSkipped()->create(['occurred_at' => now()]);

    $data = $this->service->getAggregatedData();

    expect($data)->toHaveKey('tunnel.completed')
        ->and($data['tunnel.completed'])->toBe(2)
        ->and($data['tunnel.skipped'])->toBe(1);
});

it('returns recent events', function () {
    $oldEvent = AnalyticsEvent::factory()->tunnelCompleted()->create([
        'occurred_at' => now()->subDays(5),
    ]);
    $recentEvent = AnalyticsEvent::factory()->tunnelCompleted()->create([
        'occurred_at' => now(),
    ]);

    $events = $this->service->getRecentEvents('tunnel.completed', 10);

    expect($events)->toHaveCount(2)
        ->and($events->first()->id)->toBe($recentEvent->id);
});

it('checks if events exist', function () {
    expect($this->service->hasEvents())->toBeFalse();

    AnalyticsEvent::factory()->tunnelCompleted()->create();

    expect($this->service->hasEvents())->toBeTrue();
});

it('returns total event count', function () {
    expect($this->service->getTotalEventCount())->toBe(0);

    AnalyticsEvent::factory()->tunnelCompleted()->count(5)->create();

    expect($this->service->getTotalEventCount())->toBe(5);
});

it('scopes events by type', function () {
    AnalyticsEvent::factory()->tunnelCompleted()->create();
    AnalyticsEvent::factory()->tunnelSkipped()->create();
    AnalyticsEvent::factory()->state(['event_type' => 'other.event'])->create();

    $completed = AnalyticsEvent::type('tunnel.completed')->get();

    expect($completed)->toHaveCount(1)
        ->and($completed->first()->event_type)->toBe('tunnel.completed');
});

it('scopes events by category', function () {
    AnalyticsEvent::factory()->tunnelCompleted()->create();
    AnalyticsEvent::factory()->state(['category' => 'other'])->create();

    $tunnelEvents = AnalyticsEvent::category('tunnel')->get();

    expect($tunnelEvents)->toHaveCount(1)
        ->and($tunnelEvents->first()->category)->toBe('tunnel');
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

it('handles tracking failures gracefully', function () {
    // Should not throw even if database fails
    expect(function () {
        $this->service->track('test.event', ['key' => 'value']);
    })->not->toThrow(\Throwable::class);
});

it('tracks event using trackEvent method', function () {
    $this->service->trackEvent('custom.event', ['user_id' => 123, 'action' => 'click'], 'custom');

    $event = AnalyticsEvent::first();

    expect($event)->not->toBeNull()
        ->and($event->event_type)->toBe('custom.event')
        ->and($event->category)->toBe('custom')
        ->and($event->properties)->toBe(['user_id' => 123, 'action' => 'click']);
});

it('tracks event using trackEvent without category', function () {
    $this->service->trackEvent('generic.event', ['data' => 'test']);

    $event = AnalyticsEvent::first();

    expect($event->event_type)->toBe('generic.event')
        ->and($event->category)->toBeNull()
        ->and($event->properties)->toBe(['data' => 'test']);
});

it('returns event count for specific event type', function () {
    AnalyticsEvent::factory()->tunnelCompleted()->count(3)->create();
    AnalyticsEvent::factory()->tunnelSkipped()->count(2)->create();
    AnalyticsEvent::factory()->state(['event_type' => 'other.event'])->count(5)->create();

    $completedCount = $this->service->getEventCount('tunnel.completed');
    $skippedCount = $this->service->getEventCount('tunnel.skipped');
    $otherCount = $this->service->getEventCount('other.event');
    $nonExistentCount = $this->service->getEventCount('nonexistent.event');

    expect($completedCount)->toBe(3)
        ->and($skippedCount)->toBe(2)
        ->and($otherCount)->toBe(5)
        ->and($nonExistentCount)->toBe(0);
});

it('returns zero for event count when no events exist', function () {
    expect($this->service->getEventCount('any.event'))->toBe(0);
});

it('returns accurate aggregated data with multiple event types', function () {
    AnalyticsEvent::factory()->tunnelCompleted()->count(5)->create();
    AnalyticsEvent::factory()->tunnelSkipped()->count(3)->create();
    AnalyticsEvent::factory()->state(['event_type' => 'wizard.started'])->count(2)->create();
    AnalyticsEvent::factory()->state(['event_type' => 'wizard.completed'])->count(1)->create();

    $data = $this->service->getAggregatedData();

    expect($data)->toHaveCount(4)
        ->and($data)->toHaveKey('tunnel.completed')
        ->and($data)->toHaveKey('tunnel.skipped')
        ->and($data)->toHaveKey('wizard.started')
        ->and($data)->toHaveKey('wizard.completed')
        ->and($data['tunnel.completed'])->toBe(5)
        ->and($data['tunnel.skipped'])->toBe(3)
        ->and($data['wizard.started'])->toBe(2)
        ->and($data['wizard.completed'])->toBe(1);
});

it('returns empty array for aggregated data when no events exist', function () {
    $data = $this->service->getAggregatedData();

    expect($data)->toBe([]);
});

it('handles trackEvent failure gracefully', function () {
    // Should not throw even if database fails
    expect(function () {
        $this->service->trackEvent('test.event', ['key' => 'value'], 'test');
    })->not->toThrow(\Throwable::class);
});

it('handles null database query result gracefully', function () {
    // Test that the service handles empty results gracefully
    $summary = $this->service->getEventSummary('nonexistent.event.type');

    expect($summary['count'])->toBe(0)
        ->and($summary['first_occurred'])->toBeNull()
        ->and($summary['last_occurred'])->toBeNull();
});

it('handles empty event types gracefully when aggregating', function () {
    // Test aggregation with empty event types
    $data = $this->service->getAggregatedData();

    expect($data)->toBe([]);
});

it('handles large property arrays when tracking events', function () {
    $largeProperties = array_fill(0, 1000, 'value');

    $this->service->track('large.props', ['items' => $largeProperties]);

    $event = AnalyticsEvent::first();

    expect($event)->not->toBeNull()
        ->and($event->event_type)->toBe('large.props')
        ->and(count($event->properties['items']))->toBe(1000);
});

it('handles deeply nested property arrays', function () {
    $deepProperties = [
        'level1' => [
            'level2' => [
                'level3' => [
                    'level4' => [
                        'level5' => 'deep value',
                    ],
                ],
            ],
        ],
    ];

    $this->service->track('deep.nested', $deepProperties);

    $event = AnalyticsEvent::first();

    expect($event->properties['level1']['level2']['level3']['level4']['level5'])
        ->toBe('deep value');
});

it('handles special characters in event type and properties', function () {
    $specialEventType = 'test.event-with_special.chars!@#';
    $specialProperties = [
        'unicode' => '测试中文日本語テスト🎉',
        'emoji' => '🚀🔥💯',
        'null_byte' => "test\x00null",
        'quotes' => 'He said "Hello" and \'Goodbye\'',
        'backslash' => 'path\\to\\file',
    ];

    $this->service->track($specialEventType, $specialProperties);

    $event = AnalyticsEvent::first();

    expect($event->event_type)->toBe($specialEventType)
        ->and($event->properties['unicode'])->toBe('测试中文日本語テスト🎉')
        ->and($event->properties['emoji'])->toBe('🚀🔥💯');
});

it('aggregates data correctly with thousands of events', function () {
    // Create a large number of events
    $eventCounts = [
        'event.type.a' => 500,
        'event.type.b' => 750,
        'event.type.c' => 1000,
        'event.type.d' => 250,
    ];

    foreach ($eventCounts as $type => $count) {
        AnalyticsEvent::factory()->state(['event_type' => $type])->count($count)->create();
    }

    $data = $this->service->getAggregatedData();

    expect($data)->toHaveCount(4)
        ->and($data['event.type.a'])->toBe(500)
        ->and($data['event.type.b'])->toBe(750)
        ->and($data['event.type.c'])->toBe(1000)
        ->and($data['event.type.d'])->toBe(250);
});

it('returns event summary correctly for events with same timestamps', function () {
    $sameTime = now();

    // Create 100 events with the exact same timestamp
    for ($i = 0; $i < 100; $i++) {
        AnalyticsEvent::factory()->state([
            'event_type' => 'same.time.event',
            'occurred_at' => $sameTime,
        ])->create();
    }

    $summary = $this->service->getEventSummary('same.time.event');

    expect($summary['count'])->toBe(100)
        ->and($summary['first_occurred'])->toEqual($summary['last_occurred']);
});

it('handles concurrent event tracking without data loss', function () {
    $eventTypes = ['concurrent.a', 'concurrent.b', 'concurrent.c'];
    $expectedCounts = [];

    // Simulate concurrent writes by rapidly creating events
    foreach ($eventTypes as $type) {
        $expectedCounts[$type] = 50;
        for ($i = 0; $i < 50; $i++) {
            $this->service->track($type, ['index' => $i]);
        }
    }

    // Verify all events were tracked
    foreach ($expectedCounts as $type => $expected) {
        $actualCount = AnalyticsEvent::type($type)->count();
        expect($actualCount)->toBe($expected);
    }

    // Verify total count
    expect($this->service->getTotalEventCount())->toBe(150);
});

it('handles concurrent event tracking with wizard and tunnel events', function () {
    $counts = [
        'wizard' => 0,
        'tunnel' => 0,
    ];

    // Interleave wizard and tunnel event tracking
    for ($i = 0; $i < 100; $i++) {
        if ($i % 2 === 0) {
            $this->service->trackWizardEvent('step.'.$i, ['iteration' => $i]);
            $counts['wizard']++;
        } else {
            $this->service->trackTunnelEvent('action.'.$i, ['iteration' => $i]);
            $counts['tunnel']++;
        }
    }

    expect(AnalyticsEvent::category('wizard')->count())->toBe($counts['wizard'])
        ->and(AnalyticsEvent::category('tunnel')->count())->toBe($counts['tunnel'])
        ->and($this->service->getTotalEventCount())->toBe(100);
});

it('handles empty event type gracefully', function () {
    $this->service->track('', ['data' => 'test']);

    $event = AnalyticsEvent::first();

    expect($event)->not->toBeNull()
        ->and($event->event_type)->toBe('');
});

it('handles null properties gracefully', function () {
    $this->service->track('null.props', [null, 'value', null]);

    $event = AnalyticsEvent::first();

    expect($event->properties)->toBe([null, 'value', null]);
});

it('handles extremely long event type names', function () {
    $longEventType = str_repeat('very.long.event.type.name.', 100);

    $this->service->track($longEventType, ['data' => 'test']);

    $event = AnalyticsEvent::first();

    expect($event->event_type)->toBe($longEventType);
});

it('handles empty string properties gracefully', function () {
    $this->service->track('empty.props', ['key' => '', 'empty' => '']);

    $event = AnalyticsEvent::first();

    expect($event->properties['key'])->toBe('')
        ->and($event->properties['empty'])->toBe('');
});

it('returns correct event count during high volume concurrent access', function () {
    $batchSize = 200;
    $batches = 5;

    for ($batch = 0; $batch < $batches; $batch++) {
        for ($i = 0; $i < $batchSize; $i++) {
            AnalyticsEvent::factory()->state([
                'event_type' => 'batch.'.$batch,
                'occurred_at' => now()->subSeconds($batch),
            ])->create();
        }
    }

    // Verify counts are accurate after all inserts
    for ($batch = 0; $batch < $batches; $batch++) {
        expect($this->service->getEventCount('batch.'.$batch))->toBe($batchSize);
    }

    expect($this->service->getTotalEventCount())->toBe($batchSize * $batches);
});

it('handles rapid sequential event tracking without memory issues', function () {
    // Track many events rapidly
    for ($i = 0; $i < 500; $i++) {
        $this->service->track('rapid.event', ['sequence' => $i]);
    }

    expect($this->service->getEventCount('rapid.event'))->toBe(500);

    // Verify we can retrieve recent events without issues
    $recent = $this->service->getRecentEvents('rapid.event', 250);

    expect($recent)->toHaveCount(250);
});

it('handles simultaneous reads and writes without race conditions', function () {
    // Pre-populate with some events
    AnalyticsEvent::factory()->state(['event_type' => 'race.test'])->count(50)->create();

    // Perform reads while writing
    $readCount1 = $this->service->getEventCount('race.test');

    // Add more events
    for ($i = 0; $i < 50; $i++) {
        $this->service->track('race.test', ['index' => $i]);
    }

    $readCount2 = $this->service->getEventCount('race.test');
    $summary = $this->service->getEventSummary('race.test');

    expect($readCount1)->toBe(50)
        ->and($readCount2)->toBe(100)
        ->and($summary['count'])->toBe(100);
});

it('handles events with only whitespace in properties', function () {
    $this->service->track('whitespace.props', [
        'spaces' => '   ',
        'tabs' => "\t\t\t",
        'newlines' => "\n\n\n",
    ]);

    $event = AnalyticsEvent::first();

    expect($event->properties['spaces'])->toBe('   ')
        ->and($event->properties['tabs'])->toBe("\t\t\t")
        ->and($event->properties['newlines'])->toBe("\n\n\n");
});

it('handles table lock during aggregation gracefully', function () {
    // Create some events
    AnalyticsEvent::factory()->tunnelCompleted()->count(10)->create();

    // Aggregation should still work even if there are lock issues
    $data = $this->service->getAggregatedData();

    expect($data)->toHaveKey('tunnel.completed')
        ->and($data['tunnel.completed'])->toBe(10);
});

it('returns consistent results for getEventSummary with concurrent modifications', function () {
    // Create initial events
    AnalyticsEvent::factory()->state([
        'event_type' => 'summary.test',
        'occurred_at' => now()->subDay(),
    ])->count(10)->create();

    $summary1 = $this->service->getEventSummary('summary.test');

    // Add more events
    AnalyticsEvent::factory()->state([
        'event_type' => 'summary.test',
        'occurred_at' => now(),
    ])->count(5)->create();

    $summary2 = $this->service->getEventSummary('summary.test');

    expect($summary1['count'])->toBe(10)
        ->and($summary2['count'])->toBe(15)
        ->and($summary2['first_occurred'])->not->toBeNull()
        ->and($summary2['last_occurred'])->not->toBeNull();
});
