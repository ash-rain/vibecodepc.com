@extends('layouts.pairing')

@section('title', 'Device Already Claimed')

@section('content')
<div class="rounded-2xl border border-white/[0.06] bg-white/[0.02] p-8 text-center">
    <div class="mb-6">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-red-500/10 ring-1 ring-red-500/20 mb-5">
            <svg class="w-7 h-7 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
        </div>
        <h1 class="text-2xl font-bold mb-2">Device Already Claimed</h1>
        <p class="text-gray-400">
            This device has already been paired to another account.
        </p>
    </div>

    <p class="text-sm text-gray-500 mb-6">
        If you believe this is an error, please contact support.
    </p>

    <a href="/"
        class="inline-block rounded-xl bg-white/[0.04] border border-white/[0.06] px-6 py-3 text-sm font-semibold text-gray-300 hover:bg-white/[0.08] hover:text-white transition-all">
        Go Home
    </a>
</div>
@endsection
