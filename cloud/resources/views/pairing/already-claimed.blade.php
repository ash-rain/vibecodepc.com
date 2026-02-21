@extends('layouts.pairing')

@section('title', 'Device Already Claimed')

@section('content')
<div class="bg-gray-900 rounded-xl border border-gray-800 p-8 text-center">
    <div class="mb-6">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-red-500/10 mb-4">
            <svg class="w-8 h-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
        class="inline-block rounded-lg bg-gray-700 px-6 py-3 text-sm font-semibold text-white hover:bg-gray-600 transition-colors">
        Go Home
    </a>
</div>
@endsection
