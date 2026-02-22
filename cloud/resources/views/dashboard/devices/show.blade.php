@extends('layouts.app')

@section('content')
    <div class="relative">
        {{-- Background texture --}}
        <div class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.02)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.02)_1px,transparent_1px)] bg-[size:64px_64px] pointer-events-none"></div>

        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8 space-y-8">

            {{-- Header --}}
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <a href="{{ route('dashboard') }}" class="flex h-9 w-9 items-center justify-center rounded-xl border border-white/5 bg-white/[0.02] text-gray-500 transition hover:border-white/10 hover:text-white">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                        </svg>
                    </a>
                    <div>
                        <div class="flex items-center gap-3">
                            <h1 class="text-xl font-bold tracking-tight">Device</h1>
                            <code class="rounded-md bg-white/5 border border-white/10 px-2 py-0.5 text-xs font-mono text-gray-400">{{ Str::limit($device->uuid, 20) }}</code>
                            @if ($device->is_online)
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-400">
                                    <span class="relative flex h-1.5 w-1.5">
                                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                                        <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                    </span>
                                    Online
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/5 px-2.5 py-0.5 text-xs font-medium text-gray-500">
                                    <span class="h-1.5 w-1.5 rounded-full bg-gray-600"></span>
                                    Offline
                                </span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-gray-500">Paired {{ $device->paired_at?->diffForHumans() ?? 'N/A' }}</p>
                    </div>
                </div>

                @if ($device->tunnel_url)
                    <a href="{{ $device->tunnel_url }}" target="_blank" class="hidden sm:inline-flex items-center gap-2 rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-2 text-sm font-medium text-emerald-400 transition hover:bg-emerald-500/20">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                        </svg>
                        Open Tunnel
                    </a>
                @endif
            </div>

            {{-- Health Metrics (4-up cards) --}}
            @if ($device->is_online)
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    {{-- CPU --}}
                    <div class="relative overflow-hidden rounded-2xl border border-white/5 bg-white/[0.02] p-5">
                        @php $cpuVal = $device->cpu_percent ?? 0; @endphp
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium uppercase tracking-wider text-gray-500">CPU</span>
                            <span @class(['text-2xl font-bold font-mono', 'text-red-400' => $cpuVal > 80, 'text-amber-400' => $cpuVal > 60 && $cpuVal <= 80, 'text-emerald-400' => $cpuVal <= 60])>{{ number_format($cpuVal, 1) }}<span class="text-sm text-gray-500">%</span></span>
                        </div>
                        <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-white/5">
                            <div @class(['h-full rounded-full transition-all', 'bg-red-500' => $cpuVal > 80, 'bg-amber-500' => $cpuVal > 60 && $cpuVal <= 80, 'bg-emerald-500' => $cpuVal <= 60]) style="width: {{ min(100, $cpuVal) }}%"></div>
                        </div>
                    </div>

                    {{-- RAM --}}
                    <div class="relative overflow-hidden rounded-2xl border border-white/5 bg-white/[0.02] p-5">
                        @php $ramPercent = $device->ram_usage_percent ?? 0; @endphp
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Memory</span>
                            <span class="text-2xl font-bold font-mono text-gray-100">{{ number_format($ramPercent, 1) }}<span class="text-sm text-gray-500">%</span></span>
                        </div>
                        <div class="mt-1 text-right text-[10px] text-gray-600">{{ number_format(($device->ram_used_mb ?? 0) / 1024, 1) }} / {{ number_format(($device->ram_total_mb ?? 0) / 1024, 1) }} GB</div>
                        <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-white/5">
                            <div @class(['h-full rounded-full transition-all', 'bg-red-500' => $ramPercent > 85, 'bg-amber-500' => $ramPercent > 65 && $ramPercent <= 85, 'bg-emerald-500' => $ramPercent <= 65]) style="width: {{ min(100, $ramPercent) }}%"></div>
                        </div>
                    </div>

                    {{-- Disk --}}
                    <div class="relative overflow-hidden rounded-2xl border border-white/5 bg-white/[0.02] p-5">
                        @php $diskPercent = $device->disk_usage_percent ?? 0; @endphp
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Storage</span>
                            <span class="text-2xl font-bold font-mono text-gray-100">{{ number_format($diskPercent, 1) }}<span class="text-sm text-gray-500">%</span></span>
                        </div>
                        <div class="mt-1 text-right text-[10px] text-gray-600">{{ number_format($device->disk_used_gb ?? 0, 1) }} / {{ number_format($device->disk_total_gb ?? 0, 1) }} GB</div>
                        <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-white/5">
                            <div @class(['h-full rounded-full transition-all', 'bg-red-500' => $diskPercent > 90, 'bg-amber-500' => $diskPercent > 70 && $diskPercent <= 90, 'bg-emerald-500' => $diskPercent <= 70]) style="width: {{ min(100, $diskPercent) }}%"></div>
                        </div>
                    </div>

                    {{-- Temperature --}}
                    <div class="relative overflow-hidden rounded-2xl border border-white/5 bg-white/[0.02] p-5">
                        @php $temp = $device->cpu_temp ?? 0; @endphp
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Temp</span>
                            <span @class(['text-2xl font-bold font-mono', 'text-red-400' => $temp > 70, 'text-amber-400' => $temp > 55 && $temp <= 70, 'text-emerald-400' => $temp <= 55])>{{ number_format($temp, 1) }}<span class="text-sm text-gray-500">&deg;C</span></span>
                        </div>
                        <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-white/5">
                            @php $tempPercent = min(100, ($temp / 85) * 100); @endphp
                            <div @class(['h-full rounded-full transition-all', 'bg-red-500' => $temp > 70, 'bg-amber-500' => $temp > 55 && $temp <= 70, 'bg-emerald-500' => $temp <= 55]) style="width: {{ $tempPercent }}%"></div>
                        </div>
                    </div>
                </div>
            @else
                <div class="rounded-2xl border border-white/5 bg-white/[0.02] p-8 text-center">
                    <p class="text-sm text-gray-500">Device is offline. Health metrics will appear when it reconnects.</p>
                    <p class="mt-1 text-xs text-gray-600">Last seen {{ $device->last_heartbeat_at?->diffForHumans() ?? 'never' }}</p>
                </div>
            @endif

            {{-- Two-column: Device Info + Tunnel Routes --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                {{-- Device Info --}}
                <div class="rounded-2xl border border-white/5 bg-white/[0.02] p-6">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-5">Device Information</h3>
                    <dl class="space-y-4">
                        @foreach ([
                            'UUID' => ['value' => $device->uuid, 'mono' => true],
                            'Status' => ['value' => ucfirst($device->status->value)],
                            'Firmware' => ['value' => $device->firmware_version ?? 'Unknown'],
                            'OS' => ['value' => $device->os_version ?? 'Unknown'],
                            'Paired' => ['value' => $device->paired_at?->format('M j, Y \a\t g:i A') ?? 'N/A'],
                            'Last Heartbeat' => ['value' => $device->last_heartbeat_at?->diffForHumans() ?? 'Never'],
                        ] as $label => $info)
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-sm text-gray-500 shrink-0">{{ $label }}</dt>
                                <dd @class(['text-sm text-right truncate', 'font-mono text-gray-300' => $info['mono'] ?? false, 'text-gray-300' => !($info['mono'] ?? false)])>{{ $info['value'] }}</dd>
                            </div>
                        @endforeach
                        @if ($device->ip_hint)
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-sm text-gray-500">IP Address</dt>
                                <dd class="text-sm font-mono text-gray-300">{{ $device->ip_hint }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                {{-- Tunnel Routes --}}
                <div class="rounded-2xl border border-white/5 bg-white/[0.02] p-6">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-5">Tunnel Routes</h3>

                    @if ($device->tunnelRoutes->isEmpty())
                        <div class="flex flex-col items-center justify-center py-8">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white/[0.03]">
                                <svg class="h-6 w-6 text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5a17.92 17.92 0 0 1-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" />
                                </svg>
                            </div>
                            <p class="mt-3 text-sm text-gray-500">No active tunnel routes.</p>
                            <p class="mt-1 text-xs text-gray-600">Routes appear when you deploy projects from the device.</p>
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach ($device->tunnelRoutes as $route)
                                <a href="{{ $route->full_url }}" target="_blank" class="flex items-center justify-between gap-4 rounded-xl bg-white/[0.03] p-4 transition hover:bg-white/[0.05] group">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-medium text-gray-200 truncate">{{ $route->project_name ?? $route->subdomain }}</span>
                                            <span class="rounded bg-white/5 px-1.5 py-0.5 text-[10px] font-mono text-gray-500">:{{ $route->target_port }}</span>
                                        </div>
                                        <div class="mt-1 text-xs font-mono text-emerald-500/70 truncate">{{ $route->full_url }}</div>
                                    </div>
                                    <svg class="h-4 w-4 shrink-0 text-gray-700 transition group-hover:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                    </svg>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Traffic Overview: Hourly Chart + Status Codes + Error Rate --}}
            @if ($device->tunnelRoutes->isNotEmpty() && $totalRequests24h > 0)
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {{-- Hourly Traffic (bar chart) --}}
                    <div class="lg:col-span-2 rounded-2xl border border-white/5 bg-white/[0.02] p-6">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-5">Hourly Traffic (24h)</h3>
                        @if ($hourlyStats->isNotEmpty())
                            @php $maxRequests = $hourlyStats->max('requests'); @endphp
                            <div class="flex items-end gap-1 h-32">
                                @foreach ($hourlyStats as $stat)
                                    @php $barHeight = $maxRequests > 0 ? ($stat->requests / $maxRequests) * 100 : 0; @endphp
                                    <div class="flex-1 flex flex-col items-center gap-1 group relative">
                                        <div class="w-full bg-emerald-500/60 rounded-t transition-all hover:bg-emerald-400/80" style="height: {{ max(2, $barHeight) }}%"></div>
                                        <div class="absolute -top-6 left-1/2 -translate-x-1/2 hidden group-hover:block bg-gray-800 text-[10px] text-gray-300 px-1.5 py-0.5 rounded whitespace-nowrap">
                                            {{ $stat->requests }} req &middot; {{ \Carbon\Carbon::parse($stat->hour)->format('H:i') }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-gray-600">No traffic in the last 24 hours.</p>
                        @endif
                    </div>

                    {{-- Status Codes + Error Rate --}}
                    <div class="space-y-6">
                        <div class="rounded-2xl border border-white/5 bg-white/[0.02] p-6">
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-4">Status Codes (24h)</h3>
                            <div class="space-y-2">
                                @foreach (['2xx' => 'emerald', '3xx' => 'blue', '4xx' => 'amber', '5xx' => 'red'] as $group => $color)
                                    @php $count = $statusCodeDistribution->get($group)?->count ?? 0; @endphp
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-full bg-{{ $color }}-500"></span>
                                            <span class="text-gray-400">{{ $group }}</span>
                                        </span>
                                        <span class="font-mono text-gray-300">{{ number_format($count) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="rounded-2xl border border-white/5 bg-white/[0.02] p-6">
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-3">Error Rate (24h)</h3>
                            <div class="flex items-baseline gap-1">
                                <span @class([
                                    'text-2xl font-bold font-mono',
                                    'text-emerald-400' => $errorRate < 5,
                                    'text-amber-400' => $errorRate >= 5 && $errorRate < 20,
                                    'text-red-400' => $errorRate >= 20,
                                ])>{{ $errorRate }}</span>
                                <span class="text-sm text-gray-500">%</span>
                            </div>
                            <p class="text-xs text-gray-600 mt-1">{{ number_format($totalRequests24h) }} total requests</p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Traffic Stats Per Route --}}
            @if ($device->tunnelRoutes->isNotEmpty() && $trafficStats->isNotEmpty())
                <div class="rounded-2xl border border-white/5 bg-white/[0.02] p-6">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-5">Traffic Per Route</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-white/5">
                                    @foreach (['Route', 'Total Requests', 'Avg Response Time'] as $col)
                                        <th class="px-3 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-600">{{ $col }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/[0.03]">
                                @foreach ($device->tunnelRoutes as $route)
                                    @php $stats = $trafficStats->get($route->id); @endphp
                                    @if ($stats)
                                        <tr class="transition hover:bg-white/[0.02]">
                                            <td class="px-3 py-2.5 text-xs font-mono text-emerald-500/70">{{ $route->full_url }}</td>
                                            <td class="px-3 py-2.5 text-xs font-mono text-gray-300">{{ number_format($stats->total_requests) }}</td>
                                            <td class="px-3 py-2.5 text-xs font-mono text-gray-300">{{ $stats->avg_response_time ?? '-' }} ms</td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Recent Heartbeats --}}
            @if ($recentHeartbeats->isNotEmpty())
                <div class="rounded-2xl border border-white/5 bg-white/[0.02] p-6">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-5">Heartbeat History</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-white/5">
                                    @foreach (['Time', 'CPU', 'Temp', 'RAM', 'Disk', 'Projects', 'Tunnel'] as $col)
                                        <th class="px-3 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-600">{{ $col }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/[0.03]">
                                @foreach ($recentHeartbeats->take(20) as $hb)
                                    <tr class="transition hover:bg-white/[0.02]">
                                        <td class="px-3 py-2.5 text-gray-500 whitespace-nowrap font-mono text-xs">{{ $hb->created_at?->diffForHumans(short: true) }}</td>
                                        <td class="px-3 py-2.5 font-mono text-xs">
                                            @if ($hb->cpu_percent !== null)
                                                <span @class(['text-red-400' => $hb->cpu_percent > 80, 'text-amber-400' => $hb->cpu_percent > 60 && $hb->cpu_percent <= 80, 'text-gray-300' => $hb->cpu_percent <= 60])>{{ number_format($hb->cpu_percent, 1) }}%</span>
                                            @else
                                                <span class="text-gray-700">-</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2.5 font-mono text-xs">
                                            @if ($hb->cpu_temp !== null)
                                                <span @class(['text-red-400' => $hb->cpu_temp > 70, 'text-amber-400' => $hb->cpu_temp > 55 && $hb->cpu_temp <= 70, 'text-gray-300' => $hb->cpu_temp <= 55])>{{ number_format($hb->cpu_temp, 1) }}&deg;</span>
                                            @else
                                                <span class="text-gray-700">-</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2.5 font-mono text-xs text-gray-300">{{ $hb->ram_used_mb !== null ? number_format($hb->ram_used_mb / 1024, 1) . '/' . number_format($hb->ram_total_mb / 1024, 1) . 'G' : '-' }}</td>
                                        <td class="px-3 py-2.5 font-mono text-xs text-gray-300">{{ $hb->disk_used_gb !== null ? number_format($hb->disk_used_gb, 0) . '/' . number_format($hb->disk_total_gb, 0) . 'G' : '-' }}</td>
                                        <td class="px-3 py-2.5 font-mono text-xs text-gray-300">{{ $hb->running_projects }}</td>
                                        <td class="px-3 py-2.5">
                                            @if ($hb->tunnel_active)
                                                <span class="inline-flex h-5 items-center rounded-full bg-emerald-500/10 px-2 text-[10px] font-medium text-emerald-400">Active</span>
                                            @else
                                                <span class="inline-flex h-5 items-center rounded-full bg-white/5 px-2 text-[10px] font-medium text-gray-600">Off</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

        </div>
    </div>
@endsection
