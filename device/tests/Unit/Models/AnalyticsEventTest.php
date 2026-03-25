<?php

declare(strict_types=1);

use App\Models\AnalyticsEvent;

beforeEach(function (): void {
    // Clean up before each test
    AnalyticsEvent::query()->delete();
});

describe('model attributes', function (): void {
    it('can create an analytics event', function (): void {
        $event = AnalyticsEvent::create([
            'event_type' => 'test.event',
            'category' => 'test',
            'properties' => ['key' => 'value'],
            'occurred_at' => now(),
        ]);

        expect($event->id)->toBeInt()
            ->and($event->event_type)->toBe('test.event')
            ->and($event->category)->toBe('test');
    });

    it('casts properties to array', function (): void {
        $event = AnalyticsEvent::create([
            'event_type' => 'test.event',
            'category' => 'test',
            'properties' => ['nested' => ['key' => 'value']],
            'occurred_at' => now(),
        ]);

        $event->refresh();

        expect($event->properties)->toBeArray()
            ->and($event->properties['nested']['key'])->toBe('value');
    });

    it('casts occurred_at to datetime', function (): void {
        $event = AnalyticsEvent::create([
            'event_type' => 'test.event',
            'category' => 'test',
            'occurred_at' => now(),
        ]);

        expect($event->occurred_at)->toBeInstanceOf(DateTime::class);
    });

    it('allows null properties', function (): void {
        $event = AnalyticsEvent::create([
            'event_type' => 'test.event',
            'category' => 'test',
            'properties' => null,
            'occurred_at' => now(),
        ]);

        expect($event->properties)->toBeNull();
    });

    it('handles empty properties array', function (): void {
        $event = AnalyticsEvent::create([
            'event_type' => 'test.event',
            'category' => 'test',
            'properties' => [],
            'occurred_at' => now(),
        ]);

        expect($event->properties)->toBe([]);
    });
});

describe('factory states', function (): void {
    it('creates tunnel completed event', function (): void {
        $event = AnalyticsEvent::factory()->tunnelCompleted()->create();

        expect($event->event_type)->toBe('tunnel.completed')
            ->and($event->category)->toBe('tunnel')
            ->and($event->properties['subdomain'])->toBe('test-device');
    });

    it('creates tunnel skipped event', function (): void {
        $event = AnalyticsEvent::factory()->tunnelSkipped()->create();

        expect($event->event_type)->toBe('tunnel.skipped')
            ->and($event->category)->toBe('tunnel')
            ->and($event->properties['reason'])->toBe('user_choice');
    });

    it('creates wizard event', function (): void {
        $event = AnalyticsEvent::factory()->wizardEvent()->create();

        expect($event->event_type)->toBe('wizard.completed')
            ->and($event->category)->toBe('wizard')
            ->and($event->properties['step'])->toBe('welcome');
    });

    it('creates multiple events with factory', function (): void {
        AnalyticsEvent::factory()->count(5)->create();

        expect(AnalyticsEvent::count())->toBe(5);
    });
});

describe('scopes', function (): void {
    beforeEach(function (): void {
        AnalyticsEvent::factory()->create(['event_type' => 'tunnel.completed', 'category' => 'tunnel']);
        AnalyticsEvent::factory()->create(['event_type' => 'tunnel.skipped', 'category' => 'tunnel']);
        AnalyticsEvent::factory()->create(['event_type' => 'wizard.completed', 'category' => 'wizard']);
        AnalyticsEvent::factory()->create(['event_type' => 'project.created', 'category' => 'project']);
    });

    it('scopes by event type', function (): void {
        $events = AnalyticsEvent::type('tunnel.completed')->get();

        expect($events)->toHaveCount(1)
            ->and($events->first()->event_type)->toBe('tunnel.completed');
    });

    it('scopes by category', function (): void {
        $events = AnalyticsEvent::category('tunnel')->get();

        expect($events)->toHaveCount(2);
    });

    it('scopes by occurred between dates', function (): void {
        $start = now()->subDay();
        $end = now()->addDay();

        $events = AnalyticsEvent::occurredBetween($start, $end)->get();

        expect($events)->toHaveCount(4);
    });

    it('returns no results for future dates', function (): void {
        $start = now()->addDay();
        $end = now()->addDays(2);

        $events = AnalyticsEvent::occurredBetween($start, $end)->get();

        expect($events)->toHaveCount(0);
    });

    it('chains scopes together', function (): void {
        $events = AnalyticsEvent::category('tunnel')
            ->type('tunnel.completed')
            ->get();

        expect($events)->toHaveCount(1)
            ->and($events->first()->category)->toBe('tunnel')
            ->and($events->first()->event_type)->toBe('tunnel.completed');
    });
});

describe('edge cases', function (): void {
    it('handles long event type strings', function (): void {
        $longType = str_repeat('a', 255);

        $event = AnalyticsEvent::create([
            'event_type' => $longType,
            'category' => 'test',
            'occurred_at' => now(),
        ]);

        expect($event->event_type)->toBe($longType);
    });

    it('handles unicode in properties', function (): void {
        $event = AnalyticsEvent::create([
            'event_type' => 'test.event',
            'category' => 'test',
            'properties' => ['message' => 'Hello 世界', 'emoji' => '🚀'],
            'occurred_at' => now(),
        ]);

        $event->refresh();

        expect($event->properties['message'])->toBe('Hello 世界')
            ->and($event->properties['emoji'])->toBe('🚀');
    });

    it('handles deeply nested properties', function (): void {
        $deepProperties = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'level4' => [
                            'level5' => 'deep_value',
                        ],
                    ],
                ],
            ],
        ];

        $event = AnalyticsEvent::create([
            'event_type' => 'test.event',
            'category' => 'test',
            'properties' => $deepProperties,
            'occurred_at' => now(),
        ]);

        $event->refresh();

        expect($event->properties['level1']['level2']['level3']['level4']['level5'])
            ->toBe('deep_value');
    });

    it('handles special characters in event type', function (): void {
        $specialType = 'event.with-dots_and_123';

        $event = AnalyticsEvent::create([
            'event_type' => $specialType,
            'category' => 'test',
            'occurred_at' => now(),
        ]);

        expect($event->event_type)->toBe($specialType);
    });

    it('handles very old occurred_at timestamp', function (): void {
        $event = AnalyticsEvent::create([
            'event_type' => 'test.event',
            'category' => 'test',
            'occurred_at' => now()->subYears(10),
        ]);

        expect($event->occurred_at->diffInYears(now()))->toBeGreaterThanOrEqual(9)
            ->toBeLessThanOrEqual(11);
    });

    it('handles future occurred_at timestamp', function (): void {
        $event = AnalyticsEvent::create([
            'event_type' => 'test.event',
            'category' => 'test',
            'occurred_at' => now()->addDay(),
        ]);

        expect($event->occurred_at->isFuture())->toBeTrue();
    });

    it('handles empty strings in properties', function (): void {
        $event = AnalyticsEvent::create([
            'event_type' => 'test.event',
            'category' => 'test',
            'properties' => ['empty' => ''],
            'occurred_at' => now(),
        ]);

        $event->refresh();

        expect($event->properties['empty'])->toBe('');
    });
});
