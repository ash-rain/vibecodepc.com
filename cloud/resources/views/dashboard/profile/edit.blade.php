@extends('layouts.app')

@section('content')
    <div class="relative">
        <div class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.02)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.02)_1px,transparent_1px)] bg-[size:64px_64px] pointer-events-none"></div>

        <div class="relative mx-auto max-w-2xl px-4 sm:px-6 lg:px-8 py-8 space-y-8">

            {{-- Header --}}
            <div class="flex items-center gap-4">
                <a href="{{ route('dashboard') }}" class="flex h-9 w-9 items-center justify-center rounded-xl border border-white/5 bg-white/[0.02] text-gray-500 transition hover:border-white/10 hover:text-white">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                </a>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">Profile Settings</h1>
                    <p class="mt-1 text-sm text-gray-500">Manage your account information and password.</p>
                </div>
            </div>

            {{-- Profile Information --}}
            <div class="rounded-2xl border border-white/5 bg-white/[0.02] p-6">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-5">Profile Information</h3>

                @if (session('profile_success'))
                    <div class="mb-5 rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-400">
                        {{ session('profile_success') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('profile.update') }}" class="space-y-5">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-300">Name</label>
                        <input
                            type="text"
                            name="name"
                            id="name"
                            value="{{ old('name', $user->name) }}"
                            class="mt-2 block w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-gray-100 placeholder-gray-600 focus:border-emerald-500 focus:ring-emerald-500/20 focus:ring-2 focus:outline-none"
                        />
                        @error('name')
                            <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-300">Username</label>
                        <div class="mt-2 flex items-center">
                            <input
                                type="text"
                                name="username"
                                id="username"
                                value="{{ old('username', $user->username) }}"
                                class="block w-full rounded-l-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-gray-100 placeholder-gray-600 focus:border-emerald-500 focus:ring-emerald-500/20 focus:ring-2 focus:outline-none"
                            />
                            <span class="inline-flex items-center rounded-r-xl border border-l-0 border-white/10 bg-white/[0.03] px-4 py-2.5 text-sm text-gray-500">.vibecodepc.com</span>
                        </div>
                        @error('username')
                            <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                        <p class="mt-2 text-xs text-gray-600">Lowercase letters, numbers, and hyphens only. Must start with a letter.</p>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-300">Email</label>
                        <input
                            type="email"
                            name="email"
                            id="email"
                            value="{{ old('email', $user->email) }}"
                            class="mt-2 block w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-gray-100 placeholder-gray-600 focus:border-emerald-500 focus:ring-emerald-500/20 focus:ring-2 focus:outline-none"
                        />
                        @error('email')
                            <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <button type="submit" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
                            Save Profile
                        </button>
                    </div>
                </form>
            </div>

            {{-- Change Password --}}
            <div class="rounded-2xl border border-white/5 bg-white/[0.02] p-6">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-5">Change Password</h3>

                @if (session('password_success'))
                    <div class="mb-5 rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-400">
                        {{ session('password_success') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('profile.password') }}" class="space-y-5">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="current_password" class="block text-sm font-medium text-gray-300">Current Password</label>
                        <input
                            type="password"
                            name="current_password"
                            id="current_password"
                            class="mt-2 block w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-gray-100 placeholder-gray-600 focus:border-emerald-500 focus:ring-emerald-500/20 focus:ring-2 focus:outline-none"
                        />
                        @error('current_password')
                            <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-300">New Password</label>
                        <input
                            type="password"
                            name="password"
                            id="password"
                            class="mt-2 block w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-gray-100 placeholder-gray-600 focus:border-emerald-500 focus:ring-emerald-500/20 focus:ring-2 focus:outline-none"
                        />
                        @error('password')
                            <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-300">Confirm New Password</label>
                        <input
                            type="password"
                            name="password_confirmation"
                            id="password_confirmation"
                            class="mt-2 block w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-gray-100 placeholder-gray-600 focus:border-emerald-500 focus:ring-emerald-500/20 focus:ring-2 focus:outline-none"
                        />
                    </div>

                    <div>
                        <button type="submit" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
                            Update Password
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
@endsection
