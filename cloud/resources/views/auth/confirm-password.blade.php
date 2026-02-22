@extends('layouts.guest')

@section('content')
    <h2 class="text-2xl font-bold text-center text-white mb-4">Confirm Password</h2>

    <p class="mb-5 text-sm text-gray-400 text-center">
        This is a secure area. Please confirm your password before continuing.
    </p>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <!-- Password -->
        <div class="mb-5">
            <label for="password" class="block text-sm font-medium text-gray-400 mb-1.5">Password</label>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                class="w-full px-4 py-3 rounded-xl border-white/[0.08] bg-white/[0.04] text-white placeholder-gray-500 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm transition">
            @error('password')
                <p class="mt-1.5 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex justify-end">
            <button type="submit" class="px-6 py-3 bg-gradient-to-r from-emerald-400 to-teal-400 text-gray-950 text-sm font-bold rounded-xl hover:from-emerald-300 hover:to-teal-300 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2 focus:ring-offset-gray-900 shadow-lg shadow-emerald-500/20 transition">
                Confirm
            </button>
        </div>
    </form>
@endsection
