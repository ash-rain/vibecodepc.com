@extends('layouts.guest')

@section('content')
    <h2 class="text-2xl font-bold text-center text-white mb-6">Reset Password</h2>

    <form method="POST" action="{{ route('password.store') }}">
        @csrf

        <!-- Password Reset Token -->
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <!-- Email Address -->
        <div class="mb-4">
            <label for="email" class="block text-sm font-medium text-gray-400 mb-1.5">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username"
                class="w-full px-4 py-3 rounded-xl border-white/[0.08] bg-white/[0.04] text-white placeholder-gray-500 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm transition">
            @error('email')
                <p class="mt-1.5 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <!-- Password -->
        <div class="mb-4">
            <label for="password" class="block text-sm font-medium text-gray-400 mb-1.5">Password</label>
            <input id="password" type="password" name="password" required autocomplete="new-password"
                class="w-full px-4 py-3 rounded-xl border-white/[0.08] bg-white/[0.04] text-white placeholder-gray-500 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm transition">
            @error('password')
                <p class="mt-1.5 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <!-- Confirm Password -->
        <div class="mb-6">
            <label for="password_confirmation" class="block text-sm font-medium text-gray-400 mb-1.5">Confirm Password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                class="w-full px-4 py-3 rounded-xl border-white/[0.08] bg-white/[0.04] text-white placeholder-gray-500 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm transition">
            @error('password_confirmation')
                <p class="mt-1.5 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex justify-end">
            <button type="submit" class="px-6 py-3 bg-gradient-to-r from-emerald-400 to-teal-400 text-gray-950 text-sm font-bold rounded-xl hover:from-emerald-300 hover:to-teal-300 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2 focus:ring-offset-gray-900 shadow-lg shadow-emerald-500/20 transition">
                Reset Password
            </button>
        </div>
    </form>
@endsection
