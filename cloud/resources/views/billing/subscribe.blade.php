@extends('layouts.app')

@section('content')
    <div class="relative">
        <div class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.02)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.02)_1px,transparent_1px)] bg-[size:64px_64px] pointer-events-none"></div>

        <div class="relative mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 py-8 space-y-8">
            <div class="flex items-center gap-4">
                <a href="{{ route('billing.index') }}" class="flex h-9 w-9 items-center justify-center rounded-xl border border-white/5 bg-white/[0.02] text-gray-500 transition hover:border-white/10 hover:text-white">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                </a>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">{{ $isSubscribed ? 'Change Plan' : 'Choose a Plan' }}</h1>
                    <p class="mt-1 text-sm text-gray-500">{{ $isSubscribed ? 'Switch to a different plan. Changes take effect immediately.' : 'Select the plan that fits your needs.' }}</p>
                </div>
            </div>

            @if (session('error'))
                <div class="rounded-xl border border-red-500/20 bg-red-500/10 p-4 text-sm text-red-400">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Plan Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6" x-data="{ selectedTier: null, processing: false }">
                @foreach ($tiers as $tier)
                    <div
                        @click="selectedTier = '{{ $tier['tier']->value }}'"
                        :class="selectedTier === '{{ $tier['tier']->value }}' ? 'border-emerald-500/50 bg-emerald-500/5' : 'border-white/5 bg-white/[0.02] hover:border-white/10'"
                        @class([
                            'relative rounded-2xl border p-6 cursor-pointer transition-all',
                            'ring-2 ring-emerald-500/30' => $currentTier === $tier['tier'],
                        ])
                    >
                        @if ($currentTier === $tier['tier'])
                            <span class="absolute -top-3 left-4 rounded-full bg-emerald-500 px-2.5 py-0.5 text-[10px] font-semibold text-white">Current</span>
                        @endif

                        <h3 class="text-lg font-bold text-white">{{ $tier['label'] }}</h3>
                        <div class="mt-2">
                            <span class="text-3xl font-bold text-white">${{ $tier['price'] }}</span>
                            <span class="text-sm text-gray-500">/mo</span>
                        </div>

                        <ul class="mt-5 space-y-2 text-sm text-gray-400">
                            <li class="flex items-center gap-2">
                                <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                {{ $tier['maxSubdomains'] }} subdomain{{ $tier['maxSubdomains'] > 1 ? 's' : '' }}
                            </li>
                            <li class="flex items-center gap-2">
                                <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                {{ $tier['bandwidthGb'] }} GB/mo bandwidth
                            </li>
                            <li class="flex items-center gap-2">
                                <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                HTTPS tunnels
                            </li>
                        </ul>
                    </div>
                @endforeach
            </div>

            @if ($isSubscribed)
                {{-- Plan Change Form (no payment details needed) --}}
                <div x-show="selectedTier && selectedTier !== '{{ $currentTier->value }}'" x-cloak class="rounded-2xl border border-white/5 bg-white/[0.02] p-6">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-5">Confirm Plan Change</h3>
                    <p class="text-sm text-gray-400 mb-4">Your plan will be changed immediately. The billing difference will be prorated.</p>

                    <form method="POST" action="{{ route('billing.change-plan') }}">
                        @csrf
                        <input type="hidden" name="tier" x-bind:value="selectedTier">

                        <button
                            type="submit"
                            class="w-full px-6 py-3 bg-emerald-500 hover:bg-emerald-600 text-white font-medium text-sm rounded-xl transition-colors"
                            onclick="return confirm('Change your plan? This will take effect immediately.')"
                        >
                            Change Plan
                        </button>
                    </form>
                </div>

                <div x-show="selectedTier === '{{ $currentTier->value }}'" x-cloak class="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-4">
                    <p class="text-sm text-amber-400">You are already on this plan.</p>
                </div>
            @else
                {{-- Payment Form (for new subscribers) --}}
                <div x-show="selectedTier" x-cloak class="rounded-2xl border border-white/5 bg-white/[0.02] p-6">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-5">Payment Details</h3>

                    <form id="payment-form" method="POST" action="{{ route('billing.process') }}">
                        @csrf
                        <input type="hidden" name="tier" x-bind:value="selectedTier">
                        <input type="hidden" name="payment_method" id="payment-method-input">

                        <div id="card-element" class="rounded-lg bg-gray-800 border border-gray-700 p-4 mb-4"></div>
                        <div id="card-errors" class="text-red-400 text-sm mb-4"></div>

                        <button
                            type="submit"
                            id="subscribe-btn"
                            :disabled="processing || !selectedTier"
                            class="w-full px-6 py-3 bg-emerald-500 hover:bg-emerald-600 disabled:opacity-50 text-white font-medium text-sm rounded-xl transition-colors"
                        >
                            <span x-show="!processing">Subscribe</span>
                            <span x-show="processing">Processing...</span>
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>

    @unless ($isSubscribed)
        @push('scripts')
        <script src="https://js.stripe.com/v3/"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const stripe = Stripe('{{ config('cashier.key') }}');
                const elements = stripe.elements();
                const card = elements.create('card', {
                    style: {
                        base: {
                            color: '#e5e7eb',
                            fontFamily: 'Inter, sans-serif',
                            fontSize: '14px',
                            '::placeholder': { color: '#6b7280' },
                        },
                        invalid: { color: '#f87171' },
                    },
                });
                card.mount('#card-element');

                const form = document.getElementById('payment-form');
                form.addEventListener('submit', async function (e) {
                    e.preventDefault();

                    const { setupIntent, error } = await stripe.confirmCardSetup(
                        '{{ $intent->client_secret }}',
                        { payment_method: { card } }
                    );

                    if (error) {
                        document.getElementById('card-errors').textContent = error.message;
                        return;
                    }

                    document.getElementById('payment-method-input').value = setupIntent.payment_method;
                    form.submit();
                });
            });
        </script>
        @endpush
    @endunless
@endsection
