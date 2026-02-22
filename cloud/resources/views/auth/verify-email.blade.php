@extends('layouts.guest')

@section('content')
    <h2 class="text-2xl font-bold text-center text-white mb-4">Verify Email</h2>

    <p class="mb-5 text-sm text-gray-400 text-center">
        Before getting started, please verify your email address by clicking the link we just sent you.
    </p>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 px-4 py-3 text-sm font-medium text-emerald-400">
            A new verification link has been sent to your email address.
        </div>
    @endif

    <div class="flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="px-6 py-3 bg-gradient-to-r from-emerald-400 to-teal-400 text-gray-950 text-sm font-bold rounded-xl hover:from-emerald-300 hover:to-teal-300 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2 focus:ring-offset-gray-900 shadow-lg shadow-emerald-500/20 transition">
                Resend Email
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-sm text-gray-400 hover:text-white font-medium transition">
                Log Out
            </button>
        </form>
    </div>
@endsection
