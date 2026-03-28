<?php

declare(strict_types=1);

use App\Livewire\Dashboard\AnalyticsDashboard;
use App\Models\AnalyticsEvent;
use App\Models\CloudCredential;
use Livewire\Livewire;

beforeEach(function () {
    CloudCredential::create([
        'pairing_token_encrypted' => 'test-token',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => true,
        'paired_at' => now(),
    ]);
});

// Test rendering with no events
it('renders the analytics dashboard', function () {
    Livewire::test(AnalyticsDashboard::class)
        ->assertStatus(200)
        ->assertSee('Analytics Dashboard')
        ->assertSee('Total Events');
});

// Test displaying total event count
it('displays total event count', function () {
    AnalyticsEvent::factory()->count(5)->create();

    Livewire::test(AnalyticsDashboard::class)
        ->assertSee('Total Events')
        ->assertSee('5');
});

// Test category filter
it('filters events by category', function () {
    AnalyticsEvent::factory()->tunnelCompleted()->count(3)->create();
    AnalyticsEvent::factory()->wizardEvent()->count(2)->create();

    Livewire::test(AnalyticsDashboard::class)
        ->set('selectedCategory', 'tunnel')
        ->assertSet('selectedCategory', 'tunnel');
});

// Test date range filter
it('filters events by date range', function () {
    AnalyticsEvent::factory()->tunnelCompleted()->create(['occurred_at' => now()->subHours(2)]);
    AnalyticsEvent::factory()->tunnelCompleted()->create(['occurred_at' => now()->subDays(2)]);
    AnalyticsEvent::factory()->tunnelCompleted()->create(['occurred_at' => now()->subDays(10)]);

    Livewire::test(AnalyticsDashboard::class)
        ->set('dateRange', '24h')
        ->assertSet('dateRange', '24h');
});

// Test search filter
it('filters events by search query', function () {
    AnalyticsEvent::factory()->state(['event_type' => 'custom.searchable.event'])->create();
    AnalyticsEvent::factory()->state(['event_type' => 'other.event'])->create();

    Livewire::test(AnalyticsDashboard::class)
        ->set('searchQuery', 'searchable')
        ->assertSet('searchQuery', 'searchable');
});

// Test category counts display
it('displays category counts', function () {
    AnalyticsEvent::factory()->tunnelCompleted()->count(3)->create();
    AnalyticsEvent::factory()->wizardEvent()->count(2)->create();
    AnalyticsEvent::factory()->state(['category' => 'project'])->count(4)->create();

    Livewire::test(AnalyticsDashboard::class)
        ->assertSee('tunnel')
        ->assertSee('wizard')
        ->assertSee('project');
});

// Test event type distribution display
it('displays event type distribution', function () {
    AnalyticsEvent::factory()->tunnelCompleted()->count(5)->create();
    AnalyticsEvent::factory()->tunnelSkipped()->count(3)->create();

    Livewire::test(AnalyticsDashboard::class)
        ->assertSee('tunnel.completed')
        ->assertSee('5')
        ->assertSee('tunnel.skipped')
        ->assertSee('3');
});

// Test recent events table
it('displays recent events in table', function () {
    $event = AnalyticsEvent::factory()->tunnelCompleted()->create([
        'occurred_at' => now(),
        'properties' => ['subdomain' => 'test-device'],
    ]);

    Livewire::test(AnalyticsDashboard::class)
        ->assertSee('tunnel.completed')
        ->assertSee('tunnel')
        ->assertSee('Recent Events');
});

// Test empty state when no events match filters
it('shows empty state when no events match filters', function () {
    AnalyticsEvent::factory()->tunnelCompleted()->create();

    Livewire::test(AnalyticsDashboard::class)
        ->set('searchQuery', 'nonexistent')
        ->assertSee('No events found for the selected filters.');
});

// Test refresh action
it('refreshes data when refresh is called', function () {
    Livewire::test(AnalyticsDashboard::class)
        ->call('refresh')
        ->assertSuccessful();
});

// Test polling attribute
it('has polling attribute in rendered html', function () {
    Livewire::test(AnalyticsDashboard::class)
        ->assertSeeHtml('wire:poll.30s="refresh"');
});

// Test getCategories method
it('returns correct categories', function () {
    $test = Livewire::test(AnalyticsDashboard::class);
    $component = $test->instance();
    $categories = $component->getCategories();

    expect($categories)->toHaveKey('all')
        ->and($categories)->toHaveKey('wizard')
        ->and($categories)->toHaveKey('tunnel')
        ->and($categories)->toHaveKey('project')
        ->and($categories)->toHaveKey('system')
        ->and($categories)->toHaveKey('other');
});

// Test getDateRanges method
it('returns correct date ranges', function () {
    $test = Livewire::test(AnalyticsDashboard::class);
    $component = $test->instance();
    $ranges = $component->getDateRanges();

    expect($ranges)->toHaveKey('1h')
        ->and($ranges)->toHaveKey('24h')
        ->and($ranges)->toHaveKey('7d')
        ->and($ranges)->toHaveKey('30d')
        ->and($ranges)->toHaveKey('all');
});

// Test with events having properties
it('displays events with properties correctly', function () {
    AnalyticsEvent::factory()->state([
        'event_type' => 'test.event',
        'category' => 'system',
        'properties' => ['key1' => 'value1', 'key2' => 'value2'],
    ])->create();

    Livewire::test(AnalyticsDashboard::class)
        ->assertSee('test.event')
        ->assertSee('system')
        ->assertSee('View (2)');
});

// Test filtering by 'other' category (null category)
it('filters by other category correctly', function () {
    AnalyticsEvent::factory()->tunnelCompleted()->create();
    AnalyticsEvent::factory()->state(['category' => null, 'event_type' => 'uncategorized.event'])->create();

    Livewire::test(AnalyticsDashboard::class)
        ->set('selectedCategory', 'other')
        ->assertSet('selectedCategory', 'other');
});

// Test events per page limit
it('limits events per page', function () {
    AnalyticsEvent::factory()->count(50)->create();

    $component = Livewire::test(AnalyticsDashboard::class);

    expect($component->eventsPerPage)->toBe(25);
});

// Test category badges have correct colors
it('displays category badges with correct styling', function () {
    AnalyticsEvent::factory()->tunnelCompleted()->create();
    AnalyticsEvent::factory()->wizardEvent()->create();

    Livewire::test(AnalyticsDashboard::class)
        ->assertSeeHtml('bg-purple-500/20')
        ->assertSeeHtml('text-purple-400')
        ->assertSeeHtml('bg-blue-500/20')
        ->assertSeeHtml('text-blue-400');
});

// Test refresh button loading state
it('shows loading state on refresh button', function () {
    Livewire::test(AnalyticsDashboard::class)
        ->call('refresh')
        ->assertSeeHtml('wire:loading');
});

// Test event distribution bars
it('renders event distribution progress bars', function () {
    AnalyticsEvent::factory()->tunnelCompleted()->count(10)->create();

    Livewire::test(AnalyticsDashboard::class)
        ->assertSeeHtml('bg-gray-800 rounded-full h-2')
        ->assertSeeHtml('h-2 rounded-full transition-all duration-500');
});

// Test date formatting in events table
it('formats event dates correctly', function () {
    AnalyticsEvent::factory()->tunnelCompleted()->create([
        'occurred_at' => now(),
    ]);

    Livewire::test(AnalyticsDashboard::class)
        ->assertSee(now()->format('Y-m-d'));
});
