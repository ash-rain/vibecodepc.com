@extends('layouts.pairing')

@section('title', 'Your Device')

@section('content')
<div class="rounded-2xl border border-white/[0.06] bg-white/[0.02] p-8 text-center">
    <div class="mb-6">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-white/[0.04] ring-1 ring-white/[0.06] mb-5">
            <svg class="w-7 h-7 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <h1 class="text-2xl font-bold mb-2">This Is Your Device</h1>
        <p class="text-gray-400">
            You've already paired this device to your account.
        </p>
    </div>

    <div class="rounded-xl bg-white/[0.03] border border-white/[0.06] p-4 mb-6 text-left">
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between">
                <dt class="text-gray-500">Device ID</dt>
                <dd class="font-mono text-gray-300">{{ Str::limit($device->uuid, 12) }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500">Paired</dt>
                <dd class="text-gray-300">{{ $device->paired_at?->diffForHumans() ?? 'Unknown' }}</dd>
            </div>
        </dl>
    </div>

    <a href="{{ route('dashboard') }}"
        class="inline-block w-full rounded-xl bg-gradient-to-r from-emerald-400 to-teal-400 px-6 py-3 text-sm font-bold text-gray-950 hover:from-emerald-300 hover:to-teal-300 shadow-lg shadow-emerald-500/20 transition-all">
        Go to Dashboard
    </a>
</div>
@endsection
