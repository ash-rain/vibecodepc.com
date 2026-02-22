@extends('layouts.guest')

@section('content')
    <h2 class="text-2xl font-bold text-center text-white mb-4">Forgot Password</h2>

    <p class="mb-5 text-sm text-gray-400 text-center">
        Enter your email address and we'll send you a password reset link.
    </p>

    <!-- Session Status -->
    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 px-4 py-3 text-sm font-medium text-emerald-400">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email Address -->
        <div class="mb-5">
            <label for="email" class="block text-sm font-medium text-gray-400 mb-1.5">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                class="w-full px-4 py-3 rounded-xl border-white/[0.08] bg-white/[0.04] text-white placeholder-gray-500 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm transition">
            @error('email')
                <p class="mt-1.5 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-between">
            <a href="{{ route('login') }}" class="text-sm text-gray-400 hover:text-emerald-400 transition">
                Back to login
            </a>

            <button type="submit" class="px-6 py-3 bg-gradient-to-r from-emerald-400 to-teal-400 text-gray-950 text-sm font-bold rounded-xl hover:from-emerald-300 hover:to-teal-300 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2 focus:ring-offset-gray-900 shadow-lg shadow-emerald-500/20 transition">
                Send Reset Link
            </button>
        </div>
    </form>
@endsection
