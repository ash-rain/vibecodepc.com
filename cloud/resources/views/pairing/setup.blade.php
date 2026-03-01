@extends('layouts.pairing')

@section('title', 'Setting Up Your Device')

@section('content')
    <div class="rounded-2xl border border-white/[0.06] bg-white/[0.02] p-8"
        x-data="{
            ready: false,
            tunnelUrl: '',
            message: 'Your device is starting up...',
            attempts: 0,
            maxAttempts: 90,
            async poll() {
                while (!this.ready && this.attempts < this.maxAttempts) {
                    this.attempts++
                    try {
                        const res = await fetch('{{ route('pairing.tunnel-status', $device->uuid) }}')
                        const data = await res.json()
                        if (data.ready && data.tunnel_url) {
                            this.ready = true
                            this.tunnelUrl = data.tunnel_url
                            this.message = 'Connected! Redirecting to setup wizard...'
                            setTimeout(() => {
                                window.location.href = data.tunnel_url + '/wizard'
                            }, 1500)
                            return
                        }
                    } catch (e) {}
                    if (this.attempts < 10) {
                        this.message = 'Your device is starting up...'
                    } else if (this.attempts < 30) {
                        this.message = 'Establishing secure connection...'
                    } else {
                        this.message = 'Still waiting for your device. This can take up to a minute...'
                    }
                    await new Promise(r => setTimeout(r, 3000))
                }
                if (!this.ready) {
                    this.message = 'Unable to connect. Make sure your device is powered on and connected to the internet.'
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
                Your device has been claimed. It's setting up a secure connection so you can continue setup.
            </p>

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
                    @if ($device->ip_hint)
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Local IP</dt>
                            <dd>
                                <a href="http://{{ $device->ip_hint }}" target="_blank"
                                    class="font-mono text-emerald-400 hover:underline">{{ $device->ip_hint }}</a>
                            </dd>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <dt class="text-gray-500">mDNS</dt>
                        <dd>
                            <a href="http://vibecodepc.local" target="_blank"
                                class="font-mono text-emerald-400 hover:underline">vibecodepc.local</a>
                        </dd>
                    </div>
                </dl>
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
                <div class="rounded-xl border border-amber-500/20 bg-amber-500/10 p-4 mb-4 text-left">
                    <p class="text-sm text-amber-200 mb-3">
                        Make sure your device is plugged in and connected to your network.
                        You can also access it directly on your local network:
                    </p>
                    <div class="space-y-1.5 text-sm">
                        @if ($device->ip_hint)
                            <div class="flex items-center gap-2">
                                <span class="text-amber-300/60">IP:</span>
                                <a href="http://{{ $device->ip_hint }}" target="_blank"
                                    class="font-mono text-amber-200 hover:underline">http://{{ $device->ip_hint }}</a>
                            </div>
                        @endif
                        <div class="flex items-center gap-2">
                            <span class="text-amber-300/60">mDNS:</span>
                            <a href="http://vibecodepc.local" target="_blank"
                                class="font-mono text-amber-200 hover:underline">http://vibecodepc.local</a>
                        </div>
                    </div>
                    <p class="text-xs text-amber-300/50 mt-2">
                        Or visit the
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
@endsection
