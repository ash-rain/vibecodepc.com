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
