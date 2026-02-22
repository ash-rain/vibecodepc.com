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
                    <h1 class="text-xl font-bold tracking-tight">Subdomain Settings</h1>
                    <p class="mt-1 text-sm text-gray-500">Choose your public subdomain on vibecodepc.com.</p>
                </div>
            </div>

            @if (session('status'))
                <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-400">
                    {{ session('status') }}
                </div>
            @endif

            {{-- Subdomain Form --}}
            <div class="rounded-2xl border border-white/5 bg-white/[0.02] p-6">
                <form method="POST" action="{{ route('dashboard.subdomain.update') }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-300">Subdomain</label>
                        <div class="mt-2 flex items-center">
                            <input
                                type="text"
                                name="username"
                                id="username"
                                value="{{ old('username', $user->username) }}"
                                class="block w-full rounded-l-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-gray-100 placeholder-gray-600 focus:border-emerald-500 focus:ring-emerald-500/20 focus:ring-2 focus:outline-none"
                                placeholder="your-name"
                            />
                            <span class="inline-flex items-center rounded-r-xl border border-l-0 border-white/10 bg-white/[0.03] px-4 py-2.5 text-sm text-gray-500">.vibecodepc.com</span>
                        </div>
                        @error('username')
                            <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                        <p class="mt-2 text-xs text-gray-600">3-30 characters. Lowercase letters, numbers, and hyphens only. Must start with a letter.</p>
                    </div>

                    {{-- Custom Domain --}}
                    <div id="custom-domain">
                        <label for="custom_domain" class="block text-sm font-medium text-gray-300">Custom Domain</label>
                        <div class="mt-2">
                            <input
                                type="text"
                                name="custom_domain"
                                id="custom_domain"
                                value="{{ old('custom_domain', $user->custom_domain) }}"
                                class="block w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-gray-100 placeholder-gray-600 focus:border-emerald-500 focus:ring-emerald-500/20 focus:ring-2 focus:outline-none"
                                placeholder="myapp.example.com"
                            />
                        </div>
                        @error('custom_domain')
                            <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                        <p class="mt-2 text-xs text-gray-600">
                            Set a CNAME record pointing to <code class="text-emerald-500">{{ $user->username }}.vibecodepc.com</code>
                        </p>
                        @if ($user->custom_domain)
                            <div class="mt-2 flex items-center gap-2">
                                @if ($user->custom_domain_verified_at)
                                    <span class="inline-flex items-center gap-1 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-400">
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                        Verified
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full border border-amber-500/20 bg-amber-500/10 px-2.5 py-0.5 text-xs font-medium text-amber-400">
                                        Pending verification
                                    </span>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
                            Save Changes
                        </button>
                        <p class="text-xs text-gray-600">Current: <code class="text-emerald-500">{{ $user->username }}.vibecodepc.com</code></p>
                    </div>
                </form>

                @if ($user->custom_domain && ! $user->custom_domain_verified_at)
                    <form method="POST" action="{{ route('dashboard.subdomain.verify-domain') }}" class="mt-4 pt-4 border-t border-white/5">
                        @csrf
                        <button type="submit" class="rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-medium text-gray-300 transition hover:bg-white/10">
                            Re-verify Domain
                        </button>
                    </form>
                @endif
            </div>

        </div>
    </div>
@endsection
