@extends('layouts.guest')

@section('content')
    <h2 class="text-2xl font-bold text-center text-white mb-6">Log In</h2>

    <!-- Session Status -->
    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 px-4 py-3 text-sm font-medium text-emerald-400">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div class="mb-4">
            <label for="email" class="block text-sm font-medium text-gray-400 mb-1.5">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                class="w-full px-4 py-3 rounded-xl border-white/[0.08] bg-white/[0.04] text-white placeholder-gray-500 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm transition">
            @error('email')
                <p class="mt-1.5 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <!-- Password -->
        <div class="mb-4">
            <label for="password" class="block text-sm font-medium text-gray-400 mb-1.5">Password</label>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                class="w-full px-4 py-3 rounded-xl border-white/[0.08] bg-white/[0.04] text-white placeholder-gray-500 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm transition">
            @error('password')
                <p class="mt-1.5 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <!-- Remember Me -->
        <div class="mb-5 flex items-center">
            <input id="remember_me" type="checkbox" name="remember"
                class="rounded-md border-white/[0.15] bg-white/[0.04] text-emerald-500 shadow-sm focus:ring-emerald-500 focus:ring-offset-gray-900">
            <label for="remember_me" class="ms-2 text-sm text-gray-400">Remember me</label>
        </div>

        <div class="flex items-center justify-between">
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-sm text-gray-400 hover:text-emerald-400 transition">
                    Forgot your password?
                </a>
            @endif

            <button type="submit" class="px-6 py-3 bg-gradient-to-r from-emerald-400 to-teal-400 text-gray-950 text-sm font-bold rounded-xl hover:from-emerald-300 hover:to-teal-300 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2 focus:ring-offset-gray-900 shadow-lg shadow-emerald-500/20 transition">
                Log In
            </button>
        </div>

        <div class="mt-8 text-center border-t border-white/[0.06] pt-5">
            <span class="text-sm text-gray-500">Don't have an account?</span>
            <a href="{{ route('register') }}" class="text-sm text-emerald-400 hover:text-emerald-300 font-semibold transition ml-1">Register</a>
        </div>
    </form>
@endsection
