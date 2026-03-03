<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AnalyticsEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AnalyticsService
{
    /**
     * Track a wizard event.
     */
    public function trackWizardEvent(string $event, array $properties = []): void
    {
        $this->track('wizard.'.$event, $properties, 'wizard');
    }

    /**
     * Track a tunnel pairing event.
     */
    public function trackTunnelEvent(string $event, array $properties = []): void
    {
        $this->track('tunnel.'.$event, $properties, 'tunnel');
    }

    /**
     * Track any event.
     */
    public function track(string $eventType, array $properties = [], ?string $category = null): void
    {
        try {
            AnalyticsEvent::create([
                'event_type' => $eventType,
                'category' => $category,
                'properties' => $properties,
                'occurred_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to track analytics event', [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get analytics summary for a specific event type.
     *
     * @return array{count: int, first_occurred: \Carbon\Carbon|null, last_occurred: \Carbon\Carbon|null}
     */
    public function getEventSummary(string $eventType): array
    {
        $query = AnalyticsEvent::type($eventType);

        return [
            'count' => $query->count(),
            'first_occurred' => $query->min('occurred_at'),
            'last_occurred' => $query->max('occurred_at'),
        ];
    }

    /**
     * Get analytics data for sending to cloud.
     *
     * @return array<string, array{count: int}>
     */
    public function getAggregatedData(): array
    {
        $events = AnalyticsEvent::selectRaw('event_type, COUNT(*) as count')
            ->groupBy('event_type')
            ->pluck('count', 'event_type')
            ->toArray();

        return $events;
    }

    /**
     * Get recent events of a specific type.
     *
     * @return Collection<int, AnalyticsEvent>
     */
    public function getRecentEvents(string $eventType, int $limit = 100): Collection
    {
        return AnalyticsEvent::type($eventType)
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Check if any analytics events exist.
     */
    public function hasEvents(): bool
    {
        return AnalyticsEvent::exists();
    }

    /**
     * Get total event count.
     */
    public function getTotalEventCount(): int
    {
        return AnalyticsEvent::count();
    }
}
