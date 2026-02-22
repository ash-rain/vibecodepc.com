@extends('layouts.app')

@section('content')
    <div class="relative">
        {{-- Background texture + top glow --}}
        <div class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.02)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.02)_1px,transparent_1px)] bg-[size:72px_72px] pointer-events-none"></div>
        <div class="absolute left-1/2 top-0 -translate-x-1/2 h-96 w-[800px] rounded-full bg-emerald-500/[0.04] blur-[120px] pointer-events-none"></div>

        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8 space-y-8">

            @if ($devices->isNotEmpty())
                {{-- Welcome Header --}}
                <div class="flex items-start justify-between">
                    <div>
                        @php
                            $hour = now()->hour;
                            $greeting = match (true) {
                                $hour < 12 => 'Good morning',
                                $hour < 18 => 'Good afternoon',
                                default => 'Good evening',
                            };
                        @endphp
                        <h1 class="text-2xl font-bold tracking-tight">{{ $greeting }}, {{ Auth::user()->name }}</h1>
                        <p class="mt-1 text-sm text-gray-500">Monitor and manage your VibeCodePC fleet.</p>
                    </div>
                    <span class="hidden sm:inline-flex items-center gap-1.5 rounded-full border border-white/[0.08] bg-white/[0.03] px-3 py-1 text-xs font-semibold uppercase tracking-wider text-gray-400">
                        {{ $currentTier->label() }} plan
                    </span>
                </div>

                {{-- Upgrade Banner (free users only) --}}
                @if ($currentTier === \App\Enums\SubscriptionTier::Free)
                    <div class="relative overflow-hidden rounded-2xl border border-emerald-500/20 bg-gradient-to-br from-emerald-500/[0.08] via-teal-500/[0.05] to-cyan-500/[0.08] p-6">
                        {{-- Decorative glow blurs --}}
                        <div class="absolute -left-10 -top-10 h-40 w-40 rounded-full bg-emerald-500/20 blur-3xl"></div>
                        <div class="absolute -right-10 -bottom-10 h-40 w-40 rounded-full bg-cyan-500/20 blur-3xl"></div>

                        <div class="relative flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div class="flex items-start gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-500/20">
                                    <svg class="h-5 w-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 0 0-2.455 2.456Z" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-semibold text-white">Unlock more with a paid plan</p>
                                    <p class="mt-0.5 text-sm text-gray-400">Get more subdomains, bandwidth, and priority support.</p>
                                </div>
                            </div>
                            <a href="{{ route('billing.index') }}"
                               class="inline-flex items-center justify-center gap-2 shrink-0 rounded-xl px-5 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-emerald-500 via-teal-500 to-cyan-500 shadow-lg shadow-emerald-500/25 transition-all duration-300 hover:shadow-emerald-500/40 hover:scale-105">
                                View Plans
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                </svg>
                            </a>
                        </div>
                    </div>
                @endif

                {{-- Stats + Usage Row --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    {{-- Total Devices --}}
                    <div class="group relative overflow-hidden rounded-2xl border border-white/[0.06] bg-white/[0.02] p-6 transition-all duration-200 hover:border-white/[0.12]">
                        <div class="absolute -right-4 -top-4 h-24 w-24 rounded-full bg-emerald-500/5 blur-2xl transition group-hover:bg-emerald-500/10"></div>
                        <div class="relative">
                            <div class="flex items-center gap-2">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/[0.06]">
                                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
                                    </svg>
                                </div>
                                <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Total Devices</span>
                            </div>
                            <div class="mt-3 text-3xl font-bold">{{ $totalCount }}</div>
                        </div>
                    </div>

                    {{-- Online --}}
                    <div class="group relative overflow-hidden rounded-2xl border border-emerald-500/10 bg-emerald-500/[0.03] p-6 transition-all duration-200 hover:border-emerald-500/20">
                        <div class="absolute -right-4 -top-4 h-24 w-24 rounded-full bg-emerald-500/10 blur-2xl transition group-hover:bg-emerald-500/20"></div>
                        <div class="relative">
                            <div class="flex items-center gap-2">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-500/10">
                                    <span class="relative flex h-2.5 w-2.5">
                                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                                        <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                                    </span>
                                </div>
                                <span class="text-xs font-medium uppercase tracking-wider text-emerald-500">Online</span>
                            </div>
                            <div class="mt-3 text-3xl font-bold text-emerald-400">{{ $onlineCount }}</div>
                        </div>
                    </div>

                    {{-- Offline --}}
                    <div class="group relative overflow-hidden rounded-2xl border border-white/[0.06] bg-white/[0.02] p-6 transition-all duration-200 hover:border-white/[0.12]">
                        <div class="relative">
                            <div class="flex items-center gap-2">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/[0.06]">
                                    <span class="h-2.5 w-2.5 rounded-full bg-gray-600"></span>
                                </div>
                                <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Offline</span>
                            </div>
                            <div class="mt-3 text-3xl font-bold text-gray-500">{{ $totalCount - $onlineCount }}</div>
                        </div>
                    </div>

                    {{-- Usage Meter --}}
                    <div class="group relative overflow-hidden rounded-2xl border border-white/[0.06] bg-white/[0.02] p-6 transition-all duration-200 hover:border-white/[0.12]">
                        <div class="relative space-y-4">
                            <div class="flex items-center gap-2">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/[0.06]">
                                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                                    </svg>
                                </div>
                                <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Usage</span>
                            </div>

                            {{-- Subdomains --}}
                            <div>
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-gray-500">Subdomains</span>
                                    <span class="font-mono font-semibold text-gray-300">{{ $activeSubdomainCount }}<span class="text-gray-600">/{{ $maxSubdomains }}</span></span>
                                </div>
                                <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-white/[0.06]">
                                    @php $subdomainPercent = $maxSubdomains > 0 ? min(100, ($activeSubdomainCount / $maxSubdomains) * 100) : 0; @endphp
                                    <div class="h-full rounded-full bg-emerald-500 transition-all duration-500" style="width: {{ $subdomainPercent }}%"></div>
                                </div>
                            </div>

                            {{-- Bandwidth --}}
                            <div>
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-gray-500">Bandwidth</span>
                                    <span class="font-mono font-semibold text-gray-300">{{ $bandwidthGb }} <span class="text-gray-600">GB/mo</span></span>
                                </div>
                                <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-white/[0.06]">
                                    <div class="h-full rounded-full bg-teal-500 transition-all duration-500" style="width: 0%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Device Grid --}}
                <div>
                    <h2 class="text-lg font-semibold tracking-tight">Your Devices</h2>
                    <div id="devices" class="mt-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        @foreach ($devices as $device)
                            <a href="{{ route('dashboard.devices.show', $device) }}"
                               class="group relative overflow-hidden rounded-2xl border border-white/[0.06] bg-white/[0.02] p-6 transition-all duration-200 hover:-translate-y-0.5 hover:border-emerald-500/20 hover:bg-emerald-500/[0.02] hover:shadow-lg hover:shadow-emerald-500/5">

                                {{-- Status indicator glow --}}
                                @if ($device->is_online)
                                    <div class="absolute -right-6 -top-6 h-20 w-20 rounded-full bg-emerald-500/10 blur-2xl"></div>
                                @endif

                                <div class="relative">
                                    {{-- Header --}}
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <span class="font-mono text-sm text-gray-400" title="{{ $device->uuid }}">{{ Str::limit($device->uuid, 8, '...') }}</span>
                                            @if ($device->firmware_version)
                                                <span class="ml-2 text-xs text-gray-600">v{{ $device->firmware_version }}</span>
                                            @endif
                                        </div>
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

                                    {{-- Metrics --}}
                                    @if ($device->is_online)
                                        <div class="mt-5 grid grid-cols-2 gap-3">
                                            @if ($device->cpu_percent !== null)
                                                <div class="rounded-xl bg-white/[0.03] ring-1 ring-white/[0.04] p-3">
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-[10px] font-medium uppercase tracking-wider text-gray-500">CPU</span>
                                                        <span @class(['text-xs font-semibold font-mono', 'text-red-400' => $device->cpu_percent > 80, 'text-amber-400' => $device->cpu_percent > 60 && $device->cpu_percent <= 80, 'text-emerald-400' => $device->cpu_percent <= 60])>{{ number_format($device->cpu_percent, 0) }}%</span>
                                                    </div>
                                                    <div class="mt-2 h-1 w-full overflow-hidden rounded-full bg-white/5">
                                                        <div @class(['h-full rounded-full transition-all', 'bg-red-500' => $device->cpu_percent > 80, 'bg-amber-500' => $device->cpu_percent > 60 && $device->cpu_percent <= 80, 'bg-emerald-500' => $device->cpu_percent <= 60]) style="width: {{ min(100, $device->cpu_percent) }}%"></div>
                                                    </div>
                                                </div>
                                            @endif

                                            @if ($device->ram_usage_percent !== null)
                                                <div class="rounded-xl bg-white/[0.03] ring-1 ring-white/[0.04] p-3">
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-[10px] font-medium uppercase tracking-wider text-gray-500">RAM</span>
                                                        <span class="text-xs font-semibold font-mono text-gray-300">{{ number_format($device->ram_usage_percent, 0) }}%</span>
                                                    </div>
                                                    <div class="mt-2 h-1 w-full overflow-hidden rounded-full bg-white/5">
                                                        <div @class(['h-full rounded-full transition-all', 'bg-red-500' => $device->ram_usage_percent > 85, 'bg-amber-500' => $device->ram_usage_percent > 65 && $device->ram_usage_percent <= 85, 'bg-emerald-500' => $device->ram_usage_percent <= 65]) style="width: {{ min(100, $device->ram_usage_percent) }}%"></div>
                                                    </div>
                                                </div>
                                            @endif

                                            @if ($device->cpu_temp !== null)
                                                <div class="rounded-xl bg-white/[0.03] ring-1 ring-white/[0.04] p-3">
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-[10px] font-medium uppercase tracking-wider text-gray-500">Temp</span>
                                                        <span @class(['text-xs font-semibold font-mono', 'text-red-400' => $device->cpu_temp > 70, 'text-amber-400' => $device->cpu_temp > 55 && $device->cpu_temp <= 70, 'text-emerald-400' => $device->cpu_temp <= 55])>{{ number_format($device->cpu_temp, 0) }}&deg;C</span>
                                                    </div>
                                                    <div class="mt-2 h-1 w-full overflow-hidden rounded-full bg-white/5">
                                                        @php $tempPercent = min(100, ($device->cpu_temp / 85) * 100); @endphp
                                                        <div @class(['h-full rounded-full transition-all', 'bg-red-500' => $device->cpu_temp > 70, 'bg-amber-500' => $device->cpu_temp > 55 && $device->cpu_temp <= 70, 'bg-emerald-500' => $device->cpu_temp <= 55]) style="width: {{ $tempPercent }}%"></div>
                                                    </div>
                                                </div>
                                            @endif

                                            @if ($device->disk_usage_percent !== null)
                                                <div class="rounded-xl bg-white/[0.03] ring-1 ring-white/[0.04] p-3">
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-[10px] font-medium uppercase tracking-wider text-gray-500">Disk</span>
                                                        <span class="text-xs font-semibold font-mono text-gray-300">{{ number_format($device->disk_usage_percent, 0) }}%</span>
                                                    </div>
                                                    <div class="mt-2 h-1 w-full overflow-hidden rounded-full bg-white/5">
                                                        <div @class(['h-full rounded-full transition-all', 'bg-red-500' => $device->disk_usage_percent > 90, 'bg-amber-500' => $device->disk_usage_percent > 70 && $device->disk_usage_percent <= 90, 'bg-emerald-500' => $device->disk_usage_percent <= 70]) style="width: {{ min(100, $device->disk_usage_percent) }}%"></div>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    @else
                                        <div class="mt-5 flex items-center justify-center rounded-xl bg-white/[0.02] py-6">
                                            <p class="text-xs text-gray-600">Last seen {{ $device->last_heartbeat_at?->diffForHumans() ?? 'never' }}</p>
                                        </div>
                                    @endif

                                    {{-- Footer --}}
                                    <div class="mt-4 flex items-center justify-between">
                                        @if ($device->active_routes_count > 0)
                                            <span class="inline-flex items-center gap-1 text-xs text-emerald-500">
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5a17.92 17.92 0 0 1-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" />
                                                </svg>
                                                {{ $device->active_routes_count }} {{ Str::plural('route', $device->active_routes_count) }}
                                            </span>
                                        @else
                                            <span></span>
                                        @endif
                                        <svg class="h-4 w-4 text-gray-700 transition-all duration-200 group-hover:text-emerald-400 group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                        </svg>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @else
                {{-- Empty State --}}
                <div class="flex min-h-[70vh] items-center justify-center">
                    <div class="relative max-w-lg text-center">
                        {{-- Glow --}}
                        <div class="absolute left-1/2 top-0 -translate-x-1/2 h-64 w-96 rounded-full bg-emerald-500/5 blur-[100px]"></div>

                        <div class="relative">
                            <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-2xl border border-white/[0.06] bg-white/[0.02]">
                                <svg class="h-10 w-10 text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
                                </svg>
                            </div>

                            <h2 class="mt-6 text-2xl font-bold tracking-tight">No devices paired yet</h2>
                            <p class="mt-2 text-sm text-gray-500">Get started by setting up your VibeCodePC in three simple steps.</p>

                            <div class="mt-10 space-y-0">
                                @foreach ([
                                    ['step' => '1', 'title' => 'Plug in your VibeCodePC', 'desc' => 'Connect power and ethernet. The device boots automatically.'],
                                    ['step' => '2', 'title' => 'Scan the QR code', 'desc' => 'Use your phone to scan the QR code printed on the device.'],
                                    ['step' => '3', 'title' => 'Claim & configure', 'desc' => 'Link the device to your account and complete the setup wizard.'],
                                ] as $item)
                                    <div class="group flex items-start gap-4 rounded-xl p-4 text-left transition hover:bg-white/[0.02]">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 ring-1 ring-emerald-500/20 font-mono text-sm font-bold text-emerald-400">
                                            {{ $item['step'] }}
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-200">{{ $item['title'] }}</p>
                                            <p class="mt-0.5 text-sm text-gray-500">{{ $item['desc'] }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>
@endsection
