@extends('layouts.pairing')

@section('title', 'Device Paired!')

@section('content')
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-8 text-center">
        <div class="mb-6">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-500/10 mb-4">
                <svg class="w-8 h-8 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold mb-2">Device Paired Successfully!</h1>
            <p class="text-gray-400">
                Your VibeCodePC is now linked to your account.
            </p>
        </div>

        <div class="bg-gray-800/50 rounded-lg p-4 mb-6 text-left">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-400">Device ID</dt>
                    <dd class="font-mono text-gray-200">{{ Str::limit($device->uuid, 12) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-400">Account</dt>
                    <dd class="text-gray-200">{{ $user->username ?? $user->email }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-400">Subdomain</dt>
                    <dd class="text-amber-400">{{ $user->username }}.vibecodepc.com</dd>
                </div>
            </dl>
        </div>

        <div class="bg-gray-800/50 rounded-lg border border-gray-700 p-5 mb-6">
            <h3 class="text-sm font-medium text-gray-300 mb-3">Access Your Device</h3>
            <a href="https://{{ $user->username }}.vibecodepc.com" target="_blank"
                class="inline-flex items-center gap-2 text-amber-400 hover:text-amber-300 font-mono text-sm transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                </svg>
                {{ $user->username }}.vibecodepc.com
            </a>
            <p class="text-xs text-gray-500 mt-2">
                Your device will be accessible at this address once the tunnel is configured during setup.
            </p>
        </div>

        <div class="space-y-3">
            <div class="rounded-lg bg-amber-500/10 border border-amber-500/20 p-4">
                <p class="text-sm text-amber-200">
                    <span class="font-semibold">Next step:</span> Open the link above to reach your device — the setup
                    wizard will guide you through configuring the tunnel and AI services.
                </p>
            </div>

            <a href="{{ route('dashboard') }}"
                class="inline-block rounded-lg bg-gray-700 px-6 py-3 text-sm font-semibold text-white hover:bg-gray-600 transition-colors">
                Go to Dashboard
            </a>
        </div>
    </div>
@endsection
