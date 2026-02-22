@extends('layouts.app')

@section('content')
    <div class="relative">
        <div class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.02)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.02)_1px,transparent_1px)] bg-[size:64px_64px] pointer-events-none"></div>

        <div class="relative mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-8 space-y-8">
            <div>
                <h1 class="text-xl font-bold tracking-tight">Billing & Subscription</h1>
                <p class="mt-1 text-sm text-gray-500">Manage your plan, payment method, and invoices.</p>
            </div>

            @if (session('success'))
                <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-4 text-sm text-emerald-400">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="rounded-xl border border-red-500/20 bg-red-500/10 p-4 text-sm text-red-400">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Current Plan --}}
            <div class="rounded-2xl border border-white/[0.06] bg-white/[0.02] p-6">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-5">Current Plan</h3>
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <div class="text-lg font-bold text-white">{{ $currentTier->label() }}</div>
                        <div class="text-sm text-gray-400 mt-1">
                            {{ $currentTier->maxSubdomains() }} subdomain{{ $currentTier->maxSubdomains() > 1 ? 's' : '' }} &middot;
                            {{ $currentTier->monthlyBandwidthGb() }} GB/mo bandwidth
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        @if ($currentTier !== \App\Enums\SubscriptionTier::Free)
                            @if ($user->subscription('default')?->onGracePeriod())
                                <form method="POST" action="{{ route('billing.resume') }}">
                                    @csrf
                                    <button type="submit" class="px-4 py-2 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 text-sm rounded-lg transition-colors">Resume</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('billing.cancel') }}">
                                    @csrf
                                    <button type="submit" class="px-4 py-2 bg-red-500/10 hover:bg-red-500/20 text-red-400 text-sm rounded-lg transition-colors"
                                        onclick="return confirm('Are you sure you want to cancel your subscription?')">Cancel</button>
                                </form>
                            @endif
                        @endif
                        <a href="{{ route('billing.subscribe') }}" class="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-medium rounded-lg transition-colors">
                            {{ $currentTier === \App\Enums\SubscriptionTier::Free ? 'Upgrade' : 'Change Plan' }}
                        </a>
                    </div>
                </div>
            </div>

            {{-- Why Upgrade — Free users only --}}
            @if ($currentTier === \App\Enums\SubscriptionTier::Free)
                <div class="relative overflow-hidden rounded-2xl border border-emerald-500/20 bg-gradient-to-br from-emerald-500/[0.06] via-transparent to-cyan-500/[0.06]">
                    {{-- Decorative glow --}}
                    <div class="absolute -left-16 -top-16 h-48 w-48 rounded-full bg-emerald-500/15 blur-3xl pointer-events-none"></div>
                    <div class="absolute -right-16 -bottom-16 h-48 w-48 rounded-full bg-cyan-500/15 blur-3xl pointer-events-none"></div>

                    <div class="relative p-6 sm:p-8">
                        <div class="flex items-center gap-2.5 mb-6">
                            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-500/20">
                                <svg class="h-5 w-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 0 0-2.455 2.456Z" />
                                </svg>
                            </div>
                            <h3 class="text-base font-bold text-white">Why upgrade?</h3>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            {{-- More subdomains --}}
                            <div class="rounded-xl border border-white/[0.06] bg-white/[0.03] p-5 transition-all duration-200 hover:border-white/[0.12]">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-500/10 mb-3">
                                    <svg class="h-5 w-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5a17.92 17.92 0 0 1-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" />
                                    </svg>
                                </div>
                                <p class="text-sm font-semibold text-white">More subdomains</p>
                                <p class="mt-1 text-xs text-gray-400">Deploy up to 50 projects on <span class="font-mono text-gray-300">*.vibecodepc.com</span> — free is limited to 1.</p>
                            </div>

                            {{-- More bandwidth --}}
                            <div class="rounded-xl border border-white/[0.06] bg-white/[0.03] p-5 transition-all duration-200 hover:border-white/[0.12]">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-500/10 mb-3">
                                    <svg class="h-5 w-5 text-teal-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                                    </svg>
                                </div>
                                <p class="text-sm font-semibold text-white">200 GB bandwidth</p>
                                <p class="mt-1 text-xs text-gray-400">Scale from 1 GB/mo to up to 200 GB/mo for serious traffic and media-heavy projects.</p>
                            </div>

                            {{-- Priority support --}}
                            <div class="rounded-xl border border-white/[0.06] bg-white/[0.03] p-5 transition-all duration-200 hover:border-white/[0.12]">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-cyan-500/10 mb-3">
                                    <svg class="h-5 w-5 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
                                    </svg>
                                </div>
                                <p class="text-sm font-semibold text-white">Priority support</p>
                                <p class="mt-1 text-xs text-gray-400">Jump the queue with priority email support and faster response times from our team.</p>
                            </div>

                            {{-- Custom domains --}}
                            <div class="rounded-xl border border-white/[0.06] bg-white/[0.03] p-5 transition-all duration-200 hover:border-white/[0.12]">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-500/10 mb-3">
                                    <svg class="h-5 w-5 text-violet-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                                    </svg>
                                </div>
                                <p class="text-sm font-semibold text-white">Custom domains</p>
                                <p class="mt-1 text-xs text-gray-400">Connect your own domain and run projects on <span class="font-mono text-gray-300">yourdomain.com</span> with automatic HTTPS.</p>
                            </div>

                            {{-- Team collaboration --}}
                            <div class="rounded-xl border border-white/[0.06] bg-white/[0.03] p-5 transition-all duration-200 hover:border-white/[0.12]">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500/10 mb-3">
                                    <svg class="h-5 w-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                                    </svg>
                                </div>
                                <p class="text-sm font-semibold text-white">Team collaboration</p>
                                <p class="mt-1 text-xs text-gray-400">Share devices across team members and manage access from a single dashboard.</p>
                            </div>

                            {{-- Analytics --}}
                            <div class="rounded-xl border border-white/[0.06] bg-white/[0.03] p-5 transition-all duration-200 hover:border-white/[0.12]">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-500/10 mb-3">
                                    <svg class="h-5 w-5 text-rose-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z" />
                                    </svg>
                                </div>
                                <p class="text-sm font-semibold text-white">Advanced analytics</p>
                                <p class="mt-1 text-xs text-gray-400">Detailed traffic insights, request logs, and performance metrics for every tunnel route.</p>
                            </div>
                        </div>

                        {{-- Pricing teaser --}}
                        <div class="mt-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 rounded-xl border border-white/[0.06] bg-white/[0.02] p-4">
                            <p class="text-sm text-gray-400">Plans start at <span class="font-semibold text-white">$5/mo</span> — cancel anytime, no contracts.</p>
                            <a href="{{ route('billing.subscribe') }}"
                               class="inline-flex items-center justify-center gap-2 shrink-0 rounded-xl px-5 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-emerald-500 via-teal-500 to-cyan-500 shadow-lg shadow-emerald-500/25 transition-all duration-300 hover:shadow-emerald-500/40 hover:scale-105">
                                View Plans
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Invoices --}}
            <div class="rounded-2xl border border-white/5 bg-white/[0.02] p-6">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-5">Invoices</h3>

                @if ($invoices->isEmpty())
                    <p class="text-sm text-gray-500">No invoices yet.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-white/5">
                                    <th class="px-3 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-600">Date</th>
                                    <th class="px-3 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-600">Amount</th>
                                    <th class="px-3 py-2.5 text-right text-[10px] font-semibold uppercase tracking-wider text-gray-600">Download</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/[0.03]">
                                @foreach ($invoices as $invoice)
                                    <tr class="transition hover:bg-white/[0.02]">
                                        <td class="px-3 py-2.5 text-gray-300">{{ $invoice->date()->toFormattedDateString() }}</td>
                                        <td class="px-3 py-2.5 text-gray-300">{{ $invoice->total() }}</td>
                                        <td class="px-3 py-2.5 text-right">
                                            <a href="{{ $invoice->invoicePdf() }}" target="_blank" class="text-emerald-400 hover:text-emerald-300 text-xs">PDF</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
