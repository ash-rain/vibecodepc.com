@extends('layouts.pairing')

@section('title', 'Claim Your Device')

@section('content')
<div class="bg-gray-900 rounded-xl border border-gray-800 p-8 text-center">
    <div class="mb-6">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-amber-500/10 mb-4">
            <svg class="w-8 h-8 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/>
            </svg>
        </div>
        <h1 class="text-2xl font-bold mb-2">Claim Your VibeCodePC</h1>
        <p class="text-gray-400">
            Ready to pair this device to your account?
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
        </dl>
    </div>

    <form method="POST" action="{{ route('pairing.claim', $device->uuid) }}">
        @csrf
        <button type="submit"
            class="w-full rounded-lg bg-amber-500 px-6 py-3 text-sm font-semibold text-gray-900 hover:bg-amber-400 transition-colors">
            Claim This Device
        </button>
    </form>

    <p class="mt-4 text-xs text-gray-500">
        This will link the device to your account. You can manage it from your dashboard.
    </p>
</div>
@endsection
