@extends('layouts.pairing')

@section('title', 'Your Device')

@section('content')
<div class="bg-gray-900 rounded-xl border border-gray-800 p-8 text-center">
    <div class="mb-6">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-500/10 mb-4">
            <svg class="w-8 h-8 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <h1 class="text-2xl font-bold mb-2">This Is Your Device</h1>
        <p class="text-gray-400">
            You've already paired this device to your account.
        </p>
    </div>

    <div class="bg-gray-800/50 rounded-lg p-4 mb-6 text-left">
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between">
                <dt class="text-gray-400">Device ID</dt>
                <dd class="font-mono text-gray-200">{{ Str::limit($device->uuid, 12) }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-400">Paired</dt>
                <dd class="text-gray-200">{{ $device->paired_at?->diffForHumans() ?? 'Unknown' }}</dd>
            </div>
        </dl>
    </div>

    <a href="{{ route('dashboard') }}"
        class="inline-block rounded-lg bg-amber-500 px-6 py-3 text-sm font-semibold text-gray-900 hover:bg-amber-400 transition-colors">
        Go to Dashboard
    </a>
</div>
@endsection
