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
            </dl>
        </div>

        <div class="space-y-3">
            <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-4">
                <p class="text-sm text-emerald-200">
                    <span class="font-semibold">Next step:</span> Your device will start a setup tunnel automatically.
                    Once connected, you'll be redirected to the setup wizard.
                </p>
            </div>

            <a href="{{ route('pairing.setup', $device->uuid) }}"
                class="inline-block rounded-xl bg-gradient-to-r from-emerald-400 to-teal-400 text-gray-950 px-6 py-3 text-sm font-bold hover:from-emerald-300 hover:to-teal-300 shadow-lg shadow-emerald-500/20 transition-all">
                Continue to Setup
            </a>

            <div>
                <a href="{{ route('dashboard') }}"
                    class="inline-block text-sm text-gray-500 hover:text-emerald-400 transition">
                    Go to Dashboard
                </a>
            </div>
        </div>
    </div>
@endsection
