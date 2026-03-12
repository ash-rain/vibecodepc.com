<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\AnalyticsEvent;
use App\Services\AnalyticsService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.dashboard', ['title' => 'Analytics'])]
#[Title('Analytics — VibeCodePC')]
class AnalyticsDashboard extends Component
{
    public string $selectedCategory = 'all';

    public string $dateRange = '24h';

    public string $searchQuery = '';

    public int $eventsPerPage = 25;

    /** @var array<string, int> */
    public array $categoryCounts = [];

    /** @var array<string, int> */
    public array $eventTypeCounts = [];

    /** @var Collection<int, AnalyticsEvent> */
    public Collection $recentEvents;

    public int $totalEvents = 0;

    public function mount(AnalyticsService $analyticsService): void
    {
        $this->refreshData($analyticsService);
    }

    public function updatedSelectedCategory(AnalyticsService $analyticsService): void
    {
        $this->refreshData($analyticsService);
    }

    public function updatedDateRange(AnalyticsService $analyticsService): void
    {
        $this->refreshData($analyticsService);
    }

    public function updatedSearchQuery(AnalyticsService $analyticsService): void
    {
        $this->refreshData($analyticsService);
    }

    public function refresh(AnalyticsService $analyticsService): void
    {
        $this->refreshData($analyticsService);
    }

    public function render()
    {
        return view('livewire.dashboard.analytics-dashboard');
    }

    private function refreshData(AnalyticsService $analyticsService): void
    {
        $this->totalEvents = $analyticsService->getTotalEventCount();
        $this->categoryCounts = $this->getCategoryCounts($analyticsService);
        $this->eventTypeCounts = $this->getEventTypeCounts($analyticsService);
        $this->recentEvents = $this->getFilteredEvents();
    }

    /**
     * Get event counts grouped by category.
     *
     * @return array<string, int>
     */
    private function getCategoryCounts(AnalyticsService $analyticsService): array
    {
        $counts = [];
        $categories = ['wizard', 'tunnel', 'project', 'system'];

        foreach ($categories as $category) {
            $counts[$category] = AnalyticsEvent::category($category)->count();
        }

        $counts['other'] = AnalyticsEvent::whereNull('category')->count();

        return $counts;
    }

    /**
     * Get event counts grouped by event type.
     *
     * @return array<string, int>
     */
    private function getEventTypeCounts(AnalyticsService $analyticsService): array
    {
        return $analyticsService->getAggregatedData();
    }

    /**
     * Get filtered events based on current filters.
     *
     * @return Collection<int, AnalyticsEvent>
     */
    private function getFilteredEvents(): Collection
    {
        $query = AnalyticsEvent::query();

        // Apply category filter
        if ($this->selectedCategory !== 'all') {
            if ($this->selectedCategory === 'other') {
                $query->whereNull('category');
            } else {
                $query->category($this->selectedCategory);
            }
        }

        // Apply date range filter
        $query = $this->applyDateRangeFilter($query);

        // Apply search filter
        if ($this->searchQuery !== '') {
            $query->where('event_type', 'like', '%'.strtolower($this->searchQuery).'%');
        }

        return $query->orderByDesc('occurred_at')
            ->limit($this->eventsPerPage)
            ->get();
    }

    /**
     * Apply date range filter to query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return \Illuminate\Database\Query\Builder
     */
    private function applyDateRangeFilter($query)
    {
        return match ($this->dateRange) {
            '1h' => $query->where('occurred_at', '>=', now()->subHour()),
            '24h' => $query->where('occurred_at', '>=', now()->subDay()),
            '7d' => $query->where('occurred_at', '>=', now()->subWeek()),
            '30d' => $query->where('occurred_at', '>=', now()->subDays(30)),
            'all' => $query,
            default => $query->where('occurred_at', '>=', now()->subDay()),
        };
    }

    /**
     * Get available categories for the filter.
     *
     * @return array<string, string>
     */
    public function getCategories(): array
    {
        return [
            'all' => 'All Categories',
            'wizard' => 'Wizard',
            'tunnel' => 'Tunnel',
            'project' => 'Project',
            'system' => 'System',
            'other' => 'Other',
        ];
    }

    /**
     * Get available date ranges for the filter.
     *
     * @return array<string, string>
     */
    public function getDateRanges(): array
    {
        return [
            '1h' => 'Last Hour',
            '24h' => 'Last 24 Hours',
            '7d' => 'Last 7 Days',
            '30d' => 'Last 30 Days',
            'all' => 'All Time',
        ];
    }
}
