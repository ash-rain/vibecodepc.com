@extends('layouts.pairing')

@section('title', 'Set Up Your Device')

@section('content')
    @if ($tunnelUrl)
        {{-- Phase 2: Tunnel provisioned — poll until device connects --}}
        <div class="rounded-2xl border border-white/[0.06] bg-white/[0.02] p-8"
            x-data="{
                ready: false,
                message: 'Waiting for your device to connect...',
                attempts: 0,
                maxAttempts: 90,
                async poll() {
                    while (!this.ready && this.attempts < this.maxAttempts) {
                        this.attempts++
                        try {
                            const res = await fetch('{{ route('pairing.tunnel-status', $device->uuid) }}')
                            const data = await res.json()
                            if (data.ready) {
                                this.ready = true
                                this.message = 'Connected! Redirecting to your device...'
                                setTimeout(() => {
                                    window.location.href = data.tunnel_url + '/wizard'
                                }, 1500)
                                return
                            }
                        } catch (e) {}
                        this.message = 'Waiting for your device to connect...'
                        await new Promise(r => setTimeout(r, 3000))
                    }
                    if (!this.ready) {
                        this.message = 'Taking longer than expected. Make sure your device is powered on and connected to the internet.'
                    }
                }
            }" x-init="poll()">

            <div class="text-center">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-white/[0.04] ring-1 ring-white/[0.06] mb-5">
                    <template x-if="!ready">
                        <svg class="w-7 h-7 text-emerald-400 animate-spin" xmlns="http://www.w3.org/2000/svg"
                            fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                    </template>
                    <template x-if="ready">
                        <svg class="w-7 h-7 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </template>
                </div>

                <h1 class="text-2xl font-bold mb-2"
                    x-text="ready ? 'Device Connected!' : 'Connecting to Your Device'"></h1>

                <p class="text-gray-400 mb-6">
                    Your tunnel has been provisioned. The device needs to pick up the connection.
                </p>

                <div class="rounded-xl bg-white/[0.03] border border-white/[0.06] p-4 mb-6">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1">Your device address</p>
                    <p class="font-mono text-emerald-400">{{ $subdomain }}.vibecodepc.com</p>
                </div>

                <div class="flex items-center justify-center gap-2 text-sm text-gray-500 mb-6">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-75"
                            :class="ready ? 'bg-emerald-400' : 'bg-teal-400'"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full"
                            :class="ready ? 'bg-emerald-500' : 'bg-teal-500'"></span>
                    </span>
                    <span x-text="message"></span>
                </div>

                <template x-if="attempts >= maxAttempts && !ready">
                    <div class="rounded-xl border border-amber-500/20 bg-amber-500/10 p-4 mb-4">
                        <p class="text-sm text-amber-200">
                            You can also access your device directly if it's on the same network, or visit the
                            <a href="{{ route('dashboard') }}"
                                class="underline hover:text-amber-100 transition">dashboard</a>
                            to manage your device.
                        </p>
                    </div>
                </template>

                <a href="{{ route('dashboard') }}"
                    class="inline-block text-sm text-gray-500 hover:text-emerald-400 transition">
                    Skip to Dashboard
                </a>
            </div>
        </div>
    @else
        {{-- Phase 1: Choose subdomain and provision --}}
        <div class="rounded-2xl border border-white/[0.06] bg-white/[0.02] p-8"
            x-data="{
                subdomain: '{{ old('subdomain', $subdomain) }}',
                ownUsername: '{{ $subdomain }}',
                available: null,
                checking: false,
                reason: '',
                get valid() {
                    return this.subdomain.length >= 3
                        && this.subdomain.length <= 30
                        && /^[a-z0-9][a-z0-9-]*[a-z0-9]$/.test(this.subdomain)
                },
                reset() {
                    this.available = null
                    this.reason = ''
                },
                async check() {
                    let val = this.subdomain.toLowerCase().trim()
                    this.subdomain = val
                    if (!this.valid) return

                    if (val === this.ownUsername) {
                        this.available = true
                        this.reason = ''
                        return
                    }

                    this.checking = true
                    try {
                        const res = await fetch('/api/subdomains/' + encodeURIComponent(val) + '/availability')
                        const data = await res.json()
                        this.available = data.available
                        this.reason = data.reason || ''
                    } catch {
                        this.available = null
                        this.reason = 'Could not check availability.'
                    }
                    this.checking = false
                }
            }">
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-white/[0.04] ring-1 ring-white/[0.06] mb-5">
                    <svg class="w-7 h-7 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h1 class="text-2xl font-bold mb-2">Set Up Your Tunnel</h1>
                <p class="text-gray-400">
                    Choose a subdomain for accessing your device remotely. This is how you and others will reach your
                    projects.
                </p>
            </div>

            <div class="rounded-xl bg-white/[0.03] border border-white/[0.06] p-4 mb-6 text-left">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Device ID</dt>
                        <dd class="font-mono text-gray-300">{{ Str::limit($device->uuid, 12) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Account</dt>
                        <dd class="text-gray-300">{{ $user->email }}</dd>
                    </div>
                </dl>
            </div>

            <form method="POST" action="{{ route('pairing.provision', $device->uuid) }}">
                @csrf
                <div class="mb-5">
                    <label for="subdomain"
                        class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">Your
                        Subdomain</label>
                    <div class="flex items-stretch">
                        <input type="text" name="subdomain" id="subdomain"
                            x-model="subdomain"
                            x-on:input="reset()"
                            x-on:keydown.enter.prevent="check()"
                            pattern="[a-z0-9][a-z0-9-]*[a-z0-9]" minlength="3" maxlength="30"
                            class="flex-1 min-w-0 rounded-l-xl border bg-white/[0.04] px-4 py-3 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition"
                            :class="available === true ? 'border-emerald-500/40' : available === false ? 'border-red-500/40' : 'border-white/[0.08]'"
                            placeholder="your-name" required autofocus />
                        <span
                            class="inline-flex items-center border-y border-white/[0.08] bg-white/[0.03] px-3 py-3 text-xs text-gray-500 whitespace-nowrap font-mono">
                            .vibecodepc.com
                        </span>
                        <button type="button"
                            x-on:click="check()"
                            :disabled="!valid || checking"
                            class="inline-flex items-center justify-center rounded-r-xl border border-l-0 border-white/[0.08] bg-white/[0.04] px-4 py-3 text-sm font-medium transition disabled:opacity-30 disabled:cursor-not-allowed text-gray-400 hover:bg-white/[0.08] hover:text-gray-200 whitespace-nowrap">
                            <template x-if="checking">
                                <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            </template>
                            <template x-if="!checking">
                                <span>Check</span>
                            </template>
                        </button>
                    </div>

                    {{-- Availability feedback --}}
                    <div class="mt-2 text-sm" x-cloak>
                        <p x-show="available === true" class="text-emerald-400 flex items-center gap-1.5">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7" />
                            </svg>
                            <span x-text="subdomain + '.vibecodepc.com is available!'"></span>
                        </p>
                        <p x-show="available === false" class="text-red-400 flex items-center gap-1.5">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            <span x-text="reason || 'Not available.'"></span>
                        </p>
                    </div>

                    @error('subdomain')
                        <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                    <p class="mt-2 text-xs text-gray-600">
                        Lowercase letters, numbers, and hyphens. Must start and end with a letter or number.
                    </p>
                </div>

                <button type="submit"
                    :disabled="available !== true"
                    class="w-full rounded-xl px-6 py-3 text-sm font-bold transition-all"
                    :class="available === true
                        ? 'bg-gradient-to-r from-emerald-400 to-teal-400 text-gray-950 hover:from-emerald-300 hover:to-teal-300 shadow-lg shadow-emerald-500/20 cursor-pointer'
                        : 'bg-white/[0.04] border border-white/[0.06] text-gray-600 cursor-not-allowed'">
                    Set Up Tunnel
                </button>
            </form>
        </div>
    @endif
@endsection
