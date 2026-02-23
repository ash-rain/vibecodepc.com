@extends('layouts.pairing')

@section('title', 'Claim Your Device')

@section('content')
<div class="rounded-2xl border border-white/[0.06] bg-white/[0.02] p-8 text-center">
    <div class="mb-6">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-white/[0.04] ring-1 ring-white/[0.06] mb-5">
            <svg class="w-7 h-7 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/>
            </svg>
        </div>
        <h1 class="text-2xl font-bold mb-2">Claim Your VibeCodePC</h1>
        <p class="text-gray-400">
            Ready to pair this device to your account?
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

    <form method="POST" action="{{ route('pairing.claim', $device->uuid) }}">
        @csrf
        <button type="submit"
            class="w-full rounded-xl bg-gradient-to-r from-emerald-400 to-teal-400 px-6 py-3 text-sm font-bold text-gray-950 hover:from-emerald-300 hover:to-teal-300 shadow-lg shadow-emerald-500/20 transition-all">
            Claim This Device
        </button>
    </form>

    <p class="mt-4 text-xs text-gray-600">
        This will link the device to your account. You can manage it from your dashboard.
    </p>
</div>
@endsection
