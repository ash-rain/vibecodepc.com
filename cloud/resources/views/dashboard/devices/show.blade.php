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
                                <div class="rounded-xl bg-white/[0.03] p-4"
                                     x-data="{
                                        health: null,
                                        checking: false,
                                        confirmDelete: false,
                                        confirmReprovision: false,
                                        async check() {
                                            this.checking = true
                                            try {
                                                const res = await fetch(@js(route('dashboard.devices.routes.health', [$device, $route])), {
                                                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                                })
                                                this.health = await res.json()
                                            } catch {
                                                this.health = { status: 'unreachable', message: 'Check failed.' }
                                            }
                                            this.checking = false
                                        }
                                     }">
                                    {{-- Route info row --}}
                                    <div class="flex items-center justify-between gap-3">
                                        <a href="{{ $route->full_url }}" target="_blank" class="min-w-0 group">
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-medium text-gray-200 truncate group-hover:text-white transition">{{ $route->project_name ?? $route->subdomain }}</span>
                                                <span class="rounded bg-white/5 px-1.5 py-0.5 text-[10px] font-mono text-gray-500">:{{ $route->target_port }}</span>
                                            </div>
                                            <div class="mt-1 text-xs font-mono text-emerald-500/70 truncate group-hover:text-emerald-400 transition">{{ $route->full_url }}</div>
                                        </a>
                                        <a href="{{ $route->full_url }}" target="_blank" class="shrink-0 text-gray-700 hover:text-emerald-400 transition">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                            </svg>
                                        </a>
                                    </div>

                                    {{-- Actions row --}}
                                    <div class="mt-3 flex items-center gap-2 border-t border-white/[0.04] pt-3">
                                        {{-- Health check button --}}
                                        <button @click="check()" :disabled="checking"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-white/[0.06] bg-white/[0.02] px-2.5 py-1.5 text-[11px] font-medium text-gray-400 transition hover:bg-white/[0.06] hover:text-gray-200 disabled:opacity-40 disabled:cursor-wait">
                                            <template x-if="checking">
                                                <svg class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                </svg>
                                            </template>
                                            <template x-if="!checking">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4" />
                                                </svg>
                                            </template>
                                            Health Check
                                        </button>

                                        {{-- Re-provision button --}}
                                        <button @click="confirmReprovision = true"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-amber-500/15 bg-amber-500/5 px-2.5 py-1.5 text-[11px] font-medium text-amber-400/80 transition hover:bg-amber-500/10 hover:text-amber-300">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                                            </svg>
                                            Re-provision
                                        </button>

                                        {{-- Delete button --}}
                                        <button @click="confirmDelete = true"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-red-500/15 bg-red-500/5 px-2.5 py-1.5 text-[11px] font-medium text-red-400/80 transition hover:bg-red-500/10 hover:text-red-300 ml-auto">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                            Delete
                                        </button>
                                    </div>

                                    {{-- Health check result --}}
                                    <div x-show="health" x-transition.opacity class="mt-3" x-cloak>
                                        <div x-show="health?.status === 'healthy'"
                                             class="flex items-center gap-2 rounded-lg border border-emerald-500/20 bg-emerald-500/5 px-3 py-2 text-[11px] text-emerald-400">
                                            <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4" />
                                            </svg>
                                            <span x-text="health?.message"></span>
                                            <span class="ml-auto font-mono text-emerald-500/60" x-text="'HTTP ' + health?.http_status"></span>
                                        </div>
                                        <div x-show="health?.status === 'tunnel_error'"
                                             class="flex items-center gap-2 rounded-lg border border-red-500/20 bg-red-500/5 px-3 py-2 text-[11px] text-red-400">
                                            <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15.75h.007v.008H12v-.008z" />
                                            </svg>
                                            <span x-text="health?.message"></span>
                                        </div>
                                        <div x-show="health?.status === 'unreachable'"
                                             class="flex items-center gap-2 rounded-lg border border-amber-500/20 bg-amber-500/5 px-3 py-2 text-[11px] text-amber-400">
                                            <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15.75h.007v.008H12v-.008z" />
                                            </svg>
                                            <span x-text="health?.message"></span>
                                        </div>
                                        <div x-show="health?.status === 'reprovisioning'"
                                             class="flex items-center gap-2 rounded-lg border border-blue-500/20 bg-blue-500/5 px-3 py-2 text-[11px] text-blue-400">
                                            <svg class="h-3.5 w-3.5 shrink-0 animate-spin" viewBox="0 0 24 24" fill="none">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                            </svg>
                                            <span x-text="health?.message"></span>
                                        </div>
                                        <div x-show="health?.status === 'no_tunnel'"
                                             class="flex items-center gap-2 rounded-lg border border-white/[0.06] bg-white/[0.02] px-3 py-2 text-[11px] text-gray-500">
                                            <span x-text="health?.message"></span>
                                        </div>
                                    </div>

                                    {{-- Re-provision confirmation modal --}}
                                    <template x-teleport="body">
                                        <div x-show="confirmReprovision" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4" x-cloak>
                                            <div class="absolute inset-0 bg-gray-950/80 backdrop-blur-sm" @click="confirmReprovision = false"></div>
                                            <div x-show="confirmReprovision"
                                                 x-transition:enter="transition ease-out duration-200"
                                                 x-transition:enter-start="opacity-0 scale-95"
                                                 x-transition:enter-end="opacity-100 scale-100"
                                                 x-transition:leave="transition ease-in duration-150"
                                                 x-transition:leave-start="opacity-100 scale-100"
                                                 x-transition:leave-end="opacity-0 scale-95"
                                                 class="relative w-full max-w-sm rounded-2xl border border-white/[0.06] bg-gray-950 p-6 shadow-2xl"
                                                 @click.stop>
                                                <h4 class="text-sm font-semibold text-gray-100">Re-provision Tunnel</h4>
                                                <p class="mt-2 text-xs text-gray-400">
                                                    This will recreate the Cloudflare tunnel configuration for this device. The tunnel may be briefly unavailable during re-provisioning.
                                                </p>
                                                <p class="mt-2 text-xs text-gray-500">
                                                    Use this if the tunnel is returning Cloudflare errors or is otherwise unreachable.
                                                </p>
                                                <div class="mt-5 flex items-center justify-end gap-3 pt-4 border-t border-white/[0.06]">
                                                    <button type="button" @click="confirmReprovision = false"
                                                        class="rounded-xl border border-white/[0.08] bg-white/[0.04] px-4 py-2 text-sm font-medium text-gray-400 transition hover:bg-white/[0.08] hover:text-gray-200">
                                                        Cancel
                                                    </button>
                                                    <form method="POST" action="{{ route('dashboard.devices.routes.reprovision', [$device, $route]) }}">
                                                        @csrf
                                                        <button type="submit"
                                                            class="rounded-xl border border-amber-500/20 bg-amber-500/10 px-4 py-2 text-sm font-medium text-amber-400 transition hover:bg-amber-500/20">
                                                            Re-provision
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    {{-- Delete confirmation modal --}}
                                    <template x-teleport="body">
                                        <div x-show="confirmDelete" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4" x-cloak>
                                            <div class="absolute inset-0 bg-gray-950/80 backdrop-blur-sm" @click="confirmDelete = false"></div>
                                            <div x-show="confirmDelete"
                                                 x-transition:enter="transition ease-out duration-200"
                                                 x-transition:enter-start="opacity-0 scale-95"
                                                 x-transition:enter-end="opacity-100 scale-100"
                                                 x-transition:leave="transition ease-in duration-150"
                                                 x-transition:leave-start="opacity-100 scale-100"
                                                 x-transition:leave-end="opacity-0 scale-95"
                                                 class="relative w-full max-w-sm rounded-2xl border border-white/[0.06] bg-gray-950 p-6 shadow-2xl"
                                                 @click.stop>
                                                <h4 class="text-sm font-semibold text-gray-100">Delete Tunnel Route</h4>
                                                <p class="mt-2 text-xs text-gray-400">
                                                    This will permanently delete the route <span class="font-mono text-gray-300">{{ $route->full_url }}</span> and all its traffic logs.
                                                </p>
                                                <p class="mt-2 text-xs text-gray-500">
                                                    If this is the last route for its subdomain, the DNS record will also be removed.
                                                </p>
                                                <div class="mt-5 flex items-center justify-end gap-3 pt-4 border-t border-white/[0.06]">
                                                    <button type="button" @click="confirmDelete = false"
                                                        class="rounded-xl border border-white/[0.08] bg-white/[0.04] px-4 py-2 text-sm font-medium text-gray-400 transition hover:bg-white/[0.08] hover:text-gray-200">
                                                        Cancel
                                                    </button>
                                                    <form method="POST" action="{{ route('dashboard.devices.routes.destroy', [$device, $route]) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit"
                                                            class="rounded-xl border border-red-500/20 bg-red-500/10 px-4 py-2 text-sm font-medium text-red-400 transition hover:bg-red-500/20">
                                                            Delete Route
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
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

            {{-- Heartbeat History Chart --}}
            @if ($recentHeartbeats->isNotEmpty())
                @php
                    $chartHeartbeats = $recentHeartbeats->reverse()->values();
                @endphp
                <div class="rounded-2xl border border-white/5 bg-white/[0.02] p-6"
                     x-data="{
                        chart: null,
                        loading: false,
                        period: 'today',
                        customFrom: '',
                        customTo: '',
                        visible: { cpu: true, temp: true, ram: true, disk: true },
                        url: @js(route('dashboard.devices.heartbeats', $device)),
                        datasetConfig: [
                            { label: 'CPU %', key: 'cpu', borderColor: 'rgb(52, 211, 153)', backgroundColor: 'rgba(52, 211, 153, 0.08)' },
                            { label: 'Temp °C', key: 'temp', borderColor: 'rgb(251, 191, 36)', backgroundColor: 'rgba(251, 191, 36, 0.08)' },
                            { label: 'RAM %', key: 'ram', borderColor: 'rgb(96, 165, 250)', backgroundColor: 'rgba(96, 165, 250, 0.08)' },
                            { label: 'Disk %', key: 'disk', borderColor: 'rgb(192, 132, 252)', backgroundColor: 'rgba(192, 132, 252, 0.08)' },
                        ],
                        init() {
                            const ctx = this.$refs.canvas.getContext('2d');
                            this.chart = new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: @js($chartHeartbeats->map(fn ($hb) => $hb->created_at?->format('H:i'))),
                                    datasets: this.datasetConfig.map((cfg, idx) => ({
                                        label: cfg.label,
                                        data: @js([
                                            $chartHeartbeats->map(fn ($hb) => $hb->cpu_percent),
                                            $chartHeartbeats->map(fn ($hb) => $hb->cpu_temp),
                                            $chartHeartbeats->map(fn ($hb) => $hb->ram_total_mb > 0 ? round(($hb->ram_used_mb / $hb->ram_total_mb) * 100, 1) : null),
                                            $chartHeartbeats->map(fn ($hb) => $hb->disk_total_gb > 0 ? round(($hb->disk_used_gb / $hb->disk_total_gb) * 100, 1) : null),
                                        ])[idx],
                                        borderColor: cfg.borderColor,
                                        backgroundColor: cfg.backgroundColor,
                                        fill: true, tension: 0.35, pointRadius: 0, pointHoverRadius: 4, borderWidth: 2,
                                    })),
                                },
                                options: this.chartOptions(),
                            });
                        },
                        chartOptions() {
                            return {
                                responsive: true,
                                maintainAspectRatio: false,
                                interaction: { mode: 'index', intersect: false },
                                plugins: {
                                    legend: { display: false },
                                    tooltip: {
                                        backgroundColor: 'rgba(17, 24, 39, 0.95)',
                                        borderColor: 'rgba(255, 255, 255, 0.1)',
                                        borderWidth: 1,
                                        titleColor: 'rgba(255, 255, 255, 0.7)',
                                        bodyColor: 'rgba(255, 255, 255, 0.9)',
                                        titleFont: { family: 'JetBrains Mono, monospace', size: 11 },
                                        bodyFont: { family: 'JetBrains Mono, monospace', size: 12 },
                                        padding: 10,
                                        cornerRadius: 8,
                                        callbacks: {
                                            label: (ctx) => {
                                                const val = ctx.parsed.y;
                                                if (val === null) return null;
                                                const unit = ctx.dataset.label.includes('Temp') ? '°C' : '%';
                                                return ` ${ctx.dataset.label}: ${val.toFixed(1)}${unit}`;
                                            }
                                        }
                                    },
                                },
                                scales: {
                                    x: {
                                        ticks: { color: 'rgba(255,255,255,0.25)', font: { family: 'JetBrains Mono, monospace', size: 10 }, maxRotation: 0, maxTicksLimit: 10 },
                                        grid: { color: 'rgba(255,255,255,0.04)' },
                                        border: { color: 'rgba(255,255,255,0.06)' },
                                    },
                                    y: {
                                        min: 0, max: 100,
                                        ticks: { color: 'rgba(255,255,255,0.25)', font: { family: 'JetBrains Mono, monospace', size: 10 }, stepSize: 25, callback: (v) => v + '%' },
                                        grid: { color: 'rgba(255,255,255,0.04)' },
                                        border: { color: 'rgba(255,255,255,0.06)' },
                                    },
                                },
                            };
                        },
                        async setPeriod(p) {
                            if (p === 'custom') { this.period = 'custom'; return; }
                            this.period = p;
                            await this.fetchData();
                        },
                        async applyCustom() {
                            if (!this.customFrom || !this.customTo) return;
                            await this.fetchData();
                        },
                        async fetchData() {
                            this.loading = true;
                            try {
                                const params = new URLSearchParams({ period: this.period });
                                if (this.period === 'custom') {
                                    params.set('from', this.customFrom);
                                    params.set('to', this.customTo);
                                }
                                const res = await fetch(`${this.url}?${params}`, {
                                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                });
                                const data = await res.json();
                                this.chart.data.labels = data.labels;
                                ['cpu', 'temp', 'ram', 'disk'].forEach((key, idx) => {
                                    this.chart.data.datasets[idx].data = data[key];
                                    this.chart.data.datasets[idx].hidden = !this.visible[key];
                                });
                                this.chart.update();
                            } finally {
                                this.loading = false;
                            }
                        },
                        toggle(key, idx) {
                            this.visible[key] = !this.visible[key];
                            this.chart.data.datasets[idx].hidden = !this.visible[key];
                            this.chart.update();
                        },
                     }">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500">Heartbeat History</h3>

                        <div class="flex flex-wrap items-center gap-2">
                            {{-- Period selector --}}
                            <div class="flex items-center rounded-lg border border-white/[0.06] bg-white/[0.02] p-0.5">
                                <template x-for="p in [
                                    { key: 'today', label: 'Today' },
                                    { key: '48h', label: '48h' },
                                    { key: 'week', label: 'Week' },
                                    { key: 'month', label: 'Month' },
                                    { key: 'custom', label: 'Custom' },
                                ]" :key="p.key">
                                    <button
                                        @click="setPeriod(p.key)"
                                        class="px-2.5 py-1 rounded-md text-[11px] font-medium transition-all duration-150"
                                        :class="period === p.key
                                            ? 'bg-white/[0.1] text-white shadow-sm'
                                            : 'text-gray-500 hover:text-gray-300'"
                                        x-text="p.label"
                                    ></button>
                                </template>
                            </div>

                            {{-- Custom date range --}}
                            <div x-show="period === 'custom'" x-transition.opacity class="flex items-center gap-2" x-cloak>
                                <input type="date" x-model="customFrom"
                                       class="h-7 rounded-md border border-white/[0.08] bg-white/[0.04] px-2 text-[11px] font-mono text-gray-300 focus:border-emerald-500/50 focus:outline-none focus:ring-1 focus:ring-emerald-500/30" />
                                <span class="text-[10px] text-gray-600">to</span>
                                <input type="date" x-model="customTo"
                                       class="h-7 rounded-md border border-white/[0.08] bg-white/[0.04] px-2 text-[11px] font-mono text-gray-300 focus:border-emerald-500/50 focus:outline-none focus:ring-1 focus:ring-emerald-500/30" />
                                <button @click="applyCustom"
                                        class="h-7 rounded-md border border-emerald-500/30 bg-emerald-500/10 px-3 text-[11px] font-medium text-emerald-400 transition hover:bg-emerald-500/20"
                                >Apply</button>
                            </div>

                            {{-- Dataset toggles --}}
                            <div class="flex items-center gap-3 sm:ml-2 sm:pl-2 sm:border-l sm:border-white/[0.06]">
                                <template x-for="(item, idx) in [
                                    { key: 'cpu', label: 'CPU', color: 'bg-emerald-400' },
                                    { key: 'temp', label: 'Temp', color: 'bg-amber-400' },
                                    { key: 'ram', label: 'RAM', color: 'bg-blue-400' },
                                    { key: 'disk', label: 'Disk', color: 'bg-purple-400' },
                                ]" :key="item.key">
                                    <button
                                        @click="toggle(item.key, idx)"
                                        class="flex items-center gap-1.5 text-[11px] font-medium transition-opacity"
                                        :class="visible[item.key] ? 'opacity-100 text-gray-300' : 'opacity-40 text-gray-600 line-through'"
                                    >
                                        <span class="h-2 w-2 rounded-full" :class="item.color"></span>
                                        <span x-text="item.label"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- Loading overlay --}}
                    <div class="relative">
                        <div x-show="loading" x-transition.opacity class="absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-gray-950/60 backdrop-blur-sm" x-cloak>
                            <div class="flex items-center gap-2 text-xs text-gray-400">
                                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Loading...
                            </div>
                        </div>
                        <div class="h-64">
                            <canvas x-ref="canvas"></canvas>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Danger Zone --}}
            <div class="rounded-2xl border border-white/[0.06] bg-white/[0.02] p-6"
                 x-data="{
                    open: false,
                    confirmUuid: '',
                    deviceUuid: @js($device->uuid),
                    get matches() { return this.confirmUuid === this.deviceUuid; },
                 }">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-5">Danger Zone</h3>

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-xl border border-white/[0.06] bg-white/[0.02] p-4">
                    <div>
                        <p class="text-sm font-medium text-gray-200">Unpair this device</p>
                        <p class="mt-1 text-xs text-gray-500">Remove this device from your account, delete all tunnels, DNS records, and monitoring data. This cannot be undone.</p>
                    </div>
                    <button
                        @click="open = true"
                        class="shrink-0 rounded-xl border border-red-500/20 bg-red-500/10 px-4 py-2 text-sm font-medium text-red-400 transition hover:bg-red-500/20 hover:border-red-500/30"
                    >
                        Unpair Device
                    </button>
                </div>

                {{-- Confirmation modal --}}
                <template x-teleport="body">
                    <div x-show="open" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4" x-cloak>
                        <div class="absolute inset-0 bg-gray-950/80 backdrop-blur-sm" @click="open = false; confirmUuid = ''"></div>
                        <div
                            x-show="open"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="relative w-full max-w-md rounded-2xl border border-white/[0.06] bg-gray-950 p-6 shadow-2xl"
                            @click.stop
                        >
                            <h4 class="text-sm font-semibold text-gray-100">Unpair Device</h4>
                            <p class="mt-1 text-xs text-gray-500">This action is permanent and cannot be undone.</p>

                            <div class="mt-5 space-y-2 text-xs text-gray-400">
                                <p>This will permanently delete:</p>
                                <ul class="space-y-1.5 text-gray-500">
                                    <li class="flex items-center gap-2">
                                        <span class="h-1 w-1 rounded-full bg-gray-600 shrink-0"></span>
                                        Cloudflare tunnel and DNS records
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <span class="h-1 w-1 rounded-full bg-gray-600 shrink-0"></span>
                                        All tunnel routes and traffic logs
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <span class="h-1 w-1 rounded-full bg-gray-600 shrink-0"></span>
                                        Heartbeat and monitoring history
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <span class="h-1 w-1 rounded-full bg-gray-600 shrink-0"></span>
                                        Device pairing with your account
                                    </li>
                                </ul>
                            </div>

                            <form method="POST" action="{{ route('dashboard.devices.destroy', $device) }}" class="mt-5">
                                @csrf
                                @method('DELETE')

                                <label class="block text-xs text-gray-400 mb-2">
                                    Type the device UUID to confirm
                                </label>
                                <code class="block mb-2 text-[11px] font-mono text-gray-600 select-all">{{ $device->uuid }}</code>
                                <input
                                    type="text"
                                    name="confirm_uuid"
                                    x-model="confirmUuid"
                                    autocomplete="off"
                                    spellcheck="false"
                                    placeholder="Paste UUID here"
                                    class="w-full rounded-xl border border-white/[0.08] bg-white/[0.04] px-4 py-2.5 font-mono text-sm text-gray-200 placeholder-gray-700 transition focus:border-white/20 focus:outline-none focus:ring-1 focus:ring-white/10"
                                />

                                @error('confirm_uuid')
                                    <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>
                                @enderror

                                <div class="mt-5 flex items-center justify-end gap-3 pt-4 border-t border-white/[0.06]">
                                    <button
                                        type="button"
                                        @click="open = false; confirmUuid = ''"
                                        class="rounded-xl border border-white/[0.08] bg-white/[0.04] px-4 py-2 text-sm font-medium text-gray-400 transition hover:bg-white/[0.08] hover:text-gray-200"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        :disabled="!matches"
                                        class="rounded-xl border border-red-500/20 bg-red-500/10 px-4 py-2 text-sm font-medium text-red-400 transition hover:bg-red-500/20 disabled:opacity-30 disabled:cursor-not-allowed"
                                    >
                                        Unpair Device
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </template>
            </div>

        </div>
    </div>
@endsection
