@extends('layouts.guest')

@section('content')
    <h2 class="text-2xl font-bold text-center text-white mb-6">Create Account</h2>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <div class="mb-4">
            <label for="name" class="block text-sm font-medium text-gray-400 mb-1.5">Name</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"
                class="w-full px-4 py-3 rounded-xl border-white/[0.08] bg-white/[0.04] text-white placeholder-gray-500 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm transition">
            @error('name')
                <p class="mt-1.5 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <!-- Username -->
        <div class="mb-4">
            <label for="username" class="block text-sm font-medium text-gray-400 mb-1.5">Username</label>
            <input id="username" type="text" name="username" value="{{ old('username') }}" required autocomplete="username"
                class="w-full px-4 py-3 rounded-xl border-white/[0.08] bg-white/[0.04] text-white placeholder-gray-500 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm transition"
                placeholder="e.g. my-username">
            <p class="mt-1.5 text-xs text-gray-500">Lowercase letters, numbers, and hyphens. Must start with a letter. 2-40 characters.</p>
            @error('username')
                <p class="mt-1.5 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <!-- Email Address -->
        <div class="mb-4">
            <label for="email" class="block text-sm font-medium text-gray-400 mb-1.5">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email"
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

        <div class="flex items-center justify-between">
            <a href="{{ route('login') }}" class="text-sm text-gray-400 hover:text-emerald-400 transition">
                Already registered?
            </a>

            <button type="submit" class="px-6 py-3 bg-gradient-to-r from-emerald-400 to-teal-400 text-gray-950 text-sm font-bold rounded-xl hover:from-emerald-300 hover:to-teal-300 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2 focus:ring-offset-gray-900 shadow-lg shadow-emerald-500/20 transition">
                Register
            </button>
        </div>
    </form>
@endsection
