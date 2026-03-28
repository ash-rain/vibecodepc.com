<div wire:poll.30s="refresh" class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">Analytics Dashboard</h1>
            <p class="mt-1 text-sm text-gray-400">
                Total Events: <span class="font-semibold text-white">{{ number_format($totalEvents) }}</span>
            </p>
        </div>
        <button
            wire:click="refresh"
            wire:loading.attr="disabled"
            class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg transition-colors"
        >
            <svg wire:loading wire:target="refresh" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span wire:loading.remove wire:target="refresh">Refresh</span>
            <span wire:loading wire:target="refresh">Loading...</span>
        </button>
    </div>

    {{-- Filters --}}
    <div class="bg-gray-900 rounded-xl p-4 border border-gray-800">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            {{-- Category Filter --}}
            <div>
                <label for="category" class="block text-sm font-medium text-gray-400 mb-2">Category</label>
                <select
                    wire:model.live="selectedCategory"
                    id="category"
                    class="w-full bg-gray-800 border border-gray-700 text-white text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 px-3 py-2"
                >
                    @foreach($this->getCategories() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Date Range Filter --}}
            <div>
                <label for="dateRange" class="block text-sm font-medium text-gray-400 mb-2">Time Period</label>
                <select
                    wire:model.live="dateRange"
                    id="dateRange"
                    class="w-full bg-gray-800 border border-gray-700 text-white text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 px-3 py-2"
                >
                    @foreach($this->getDateRanges() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Search Filter --}}
            <div>
                <label for="search" class="block text-sm font-medium text-gray-400 mb-2">Search Events</label>
                <input
                    wire:model.live.debounce.300ms="searchQuery"
                    type="text"
                    id="search"
                    placeholder="Search event types..."
                    class="w-full bg-gray-800 border border-gray-700 text-white text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 px-3 py-2 placeholder-gray-500"
                >
            </div>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($categoryCounts as $category => $count)
            @if($count > 0 || $selectedCategory === $category || $selectedCategory === 'all')
                <div class="bg-gray-900 rounded-xl p-4 border border-gray-800 hover:border-gray-700 transition-colors">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-400 capitalize">{{ $category === 'other' ? 'Other' : $category }}</p>
                            <p class="mt-1 text-2xl font-bold text-white">{{ number_format($count) }}</p>
                        </div>
                        <div @class([
                            'w-10 h-10 rounded-lg flex items-center justify-center',
                            'bg-blue-500/20 text-blue-400' => $category === 'wizard',
                            'bg-purple-500/20 text-purple-400' => $category === 'tunnel',
                            'bg-green-500/20 text-green-400' => $category === 'project',
                            'bg-orange-500/20 text-orange-400' => $category === 'system',
                            'bg-gray-500/20 text-gray-400' => $category === 'other',
                        ])>
                            @if($category === 'wizard')
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                                </svg>
                            @elseif($category === 'tunnel')
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path>
                                </svg>
                            @elseif($category === 'project')
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                                </svg>
                            @elseif($category === 'system')
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"></path>
                                </svg>
                            @else
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"></path>
                                </svg>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    </div>

    {{-- Event Type Distribution --}}
    @if(count($eventTypeCounts) > 0)
        <div class="bg-gray-900 rounded-xl p-4 border border-gray-800">
            <h2 class="text-lg font-semibold text-white mb-4">Event Distribution</h2>
            <div class="space-y-3">
                @foreach(collect($eventTypeCounts)->sortDesc()->take(10) as $eventType => $count)
                    <div class="flex items-center gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm text-gray-300 truncate">{{ $eventType }}</span>
                                <span class="text-sm text-gray-400 ml-2">{{ number_format($count) }}</span>
                            </div>
                            <div class="w-full bg-gray-800 rounded-full h-2">
                                @php
                                    $maxCount = max($eventTypeCounts) ?: 1;
                                    $percentage = ($count / $maxCount) * 100;
                                    $color = match(true) {
                                        str_starts_with($eventType, 'wizard.') => 'bg-blue-500',
                                        str_starts_with($eventType, 'tunnel.') => 'bg-purple-500',
                                        str_starts_with($eventType, 'project.') => 'bg-green-500',
                                        str_starts_with($eventType, 'system.') => 'bg-orange-500',
                                        default => 'bg-gray-500',
                                    };
                                @endphp

                                <div class="{{ $color }} h-2 rounded-full transition-all duration-500" style="width: {{ $percentage }}%"></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Recent Events Table --}}
    <div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden">
        <div class="p-4 border-b border-gray-800">
            <h2 class="text-lg font-semibold text-white">Recent Events</h2>
            <p class="text-sm text-gray-400 mt-1">Showing {{ $recentEvents->count() }} events</p>
        </div>

        @if($recentEvents->isEmpty())
            <div class="p-8 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                <p class="mt-4 text-sm text-gray-400">No events found for the selected filters.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-400 uppercase bg-gray-800/50">
                        <tr>
                            <th scope="col" class="px-4 py-3">Event Type</th>
                            <th scope="col" class="px-4 py-3">Category</th>
                            <th scope="col" class="px-4 py-3">Time</th>
                            <th scope="col" class="px-4 py-3">Properties</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        @foreach($recentEvents as $event)
                            <tr class="hover:bg-gray-800/50 transition-colors">
                                <td class="px-4 py-3 font-medium text-white">
                                    {{ $event->event_type }}
                                </td>
                                <td class="px-4 py-3">
                                    @if($event->category)
                                        <span @class([
                                            'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                                            'bg-blue-500/20 text-blue-400' => $event->category === 'wizard',
                                            'bg-purple-500/20 text-purple-400' => $event->category === 'tunnel',
                                            'bg-green-500/20 text-green-400' => $event->category === 'project',
                                            'bg-orange-500/20 text-orange-400' => $event->category === 'system',
                                        ])>
                                            {{ $event->category }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-500/20 text-gray-400">
                                            other
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-400">
                                    {{ $event->occurred_at->diffForHumans() }}
                                    <span class="text-gray-600 text-xs block">{{ $event->occurred_at->format('Y-m-d H:i:s') }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    @if(!empty($event->properties))
                                        <button
                                            x-data="{ open: false }"
                                            @click="open = !open"
                                            class="text-indigo-400 hover:text-indigo-300 text-xs font-medium"
                                        >
                                            <span x-show="!open">View ({{ count($event->properties) }})</span>
                                            <span x-show="open">Hide</span>
                                        </button>
                                        <div x-show="open" x-cloak class="mt-2 p-2 bg-gray-800 rounded text-xs text-gray-300 font-mono">
                                            <pre>{{ json_encode($event->properties, JSON_PRETTY_PRINT) }}</pre>
                                        </div>
                                    @else
                                        <span class="text-gray-600">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
