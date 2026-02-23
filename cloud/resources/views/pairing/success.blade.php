@extends('layouts.pairing')

@section('title', 'Device Paired!')

@section('content')
    <div class="rounded-2xl border border-white/[0.06] bg-white/[0.02] p-8 text-center">
        <div class="mb-6">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-white/[0.04] ring-1 ring-white/[0.06] mb-5">
                <svg class="w-7 h-7 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold mb-2">Device Paired Successfully!</h1>
            <p class="text-gray-400">
                Your VibeCodePC is now linked to your account.
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
                    <dd class="text-gray-300">{{ $user->username ?? $user->email }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Subdomain</dt>
                    <dd class="font-mono text-emerald-400">{{ $user->username }}.vibecodepc.com</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-xl bg-white/[0.03] border border-white/[0.06] p-5 mb-6">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-3">Access Your Device</p>
            <a href="https://{{ $user->username }}.vibecodepc.com" target="_blank"
                class="inline-flex items-center gap-2 text-emerald-400 hover:text-emerald-300 font-mono text-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                </svg>
                {{ $user->username }}.vibecodepc.com
            </a>
            <p class="text-xs text-gray-600 mt-2">
                Your device will be accessible at this address once the tunnel is configured during setup.
            </p>
        </div>

        <div class="space-y-3">
            <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-4">
                <p class="text-sm text-emerald-200">
                    <span class="font-semibold">Next step:</span> Open the link above to reach your device — the setup
                    wizard will guide you through configuring the tunnel and AI services.
                </p>
            </div>

            <a href="{{ route('dashboard') }}"
                class="inline-block rounded-xl bg-white/[0.04] border border-white/[0.06] px-6 py-3 text-sm font-semibold text-gray-300 hover:bg-white/[0.08] hover:text-white transition-all">
                Go to Dashboard
            </a>
        </div>
    </div>
@endsection
