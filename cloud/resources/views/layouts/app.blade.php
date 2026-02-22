<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'VibeCodePC') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800|jetbrains-mono:400,500" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-950 text-gray-100">
    <div class="min-h-screen">
        <!-- Navigation -->
        <nav class="sticky top-0 z-50 border-b border-white/[0.06] bg-gray-950/80 backdrop-blur-xl" x-data="{ mobileOpen: false, userOpen: false }">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 items-center justify-between">
                    {{-- Left: Logo + nav links --}}
                    <div class="flex items-center gap-6">
                        <a href="{{ route('dashboard') }}" class="flex items-center gap-2 shrink-0">
                            @if (file_exists(public_path('storage/logo2.png')))
                                <img src="{{ asset('storage/logo2.png') }}" alt="VibeCodePC" class="h-8" />
                            @else
                                <span class="text-lg font-bold bg-gradient-to-r from-emerald-400 to-teal-300 bg-clip-text text-transparent">VibeCodePC</span>
                            @endif
                        </a>

                        <div class="hidden sm:flex items-center gap-1">
                            <span class="h-5 w-px bg-white/[0.08]"></span>

                            <a href="{{ route('dashboard') }}"
                                @class([
                                    'ml-3 px-3 py-1.5 rounded-lg text-sm font-medium transition-all duration-150',
                                    'bg-white/[0.08] text-white shadow-sm shadow-white/[0.04]' => request()->routeIs('dashboard') && !request()->routeIs('dashboard.*'),
                                    'text-gray-400 hover:text-white hover:bg-white/[0.04]' => !request()->routeIs('dashboard') || request()->routeIs('dashboard.*'),
                                ])
                            >Devices</a>

                            <a href="{{ route('billing.index') }}"
                                @class([
                                    'px-3 py-1.5 rounded-lg text-sm font-medium transition-all duration-150',
                                    'bg-white/[0.08] text-white shadow-sm shadow-white/[0.04]' => request()->routeIs('billing.*'),
                                    'text-gray-400 hover:text-white hover:bg-white/[0.04]' => !request()->routeIs('billing.*'),
                                ])
                            >Billing</a>

                            @if (request()->routeIs('dashboard.devices.*'))
                                <svg class="h-4 w-4 text-gray-600 ml-1" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                </svg>
                                <span class="px-3 py-1.5 rounded-lg text-sm font-medium bg-white/[0.08] text-white shadow-sm shadow-white/[0.04]">Device</span>
                            @endif
                        </div>
                    </div>

                    {{-- Center: Upgrade CTA (free users only) --}}
                    @if (Auth::user()->subscriptionTier() === \App\Enums\SubscriptionTier::Free)
                        <a href="{{ route('billing.index') }}"
                           class="hidden md:inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-sm font-semibold text-white bg-gradient-to-r from-emerald-500 via-teal-500 to-cyan-500 shadow-lg shadow-emerald-500/25 transition-all duration-300 hover:shadow-emerald-500/40 hover:scale-105">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 0 0-2.455 2.456Z" />
                            </svg>
                            Upgrade Plan
                        </a>
                    @endif

                    {{-- Right: Subdomain badge + user dropdown --}}
                    <div class="flex items-center gap-3">
                        <a href="{{ route('dashboard.subdomain.edit') }}" class="hidden lg:inline-flex rounded-md bg-emerald-500/10 border border-emerald-500/20 px-2.5 py-1 text-xs font-mono text-emerald-400 transition hover:bg-emerald-500/20">{{ Auth::user()->username }}.vibecodepc.com</a>

                        {{-- User dropdown --}}
                        <div class="relative hidden sm:block" x-data="{ userOpen: false }">
                            <button @click="userOpen = !userOpen" @click.outside="userOpen = false"
                                    class="flex items-center gap-2.5 rounded-xl px-2.5 py-1.5 transition-all duration-150 hover:bg-white/[0.04]">
                                <div class="flex h-7 w-7 items-center justify-center rounded-full bg-gradient-to-br from-emerald-500 to-teal-600 text-xs font-bold text-white uppercase">
                                    {{ Str::substr(Auth::user()->name, 0, 1) }}
                                </div>
                                <span class="text-sm font-medium text-gray-300">{{ Auth::user()->name }}</span>
                                <span class="rounded-md bg-white/[0.06] px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-gray-500">{{ Auth::user()->subscriptionTier()->label() }}</span>
                                <svg class="h-4 w-4 text-gray-500 transition-transform duration-150" :class="{ 'rotate-180': userOpen }" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>

                            <div x-show="userOpen"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                 x-transition:leave-end="opacity-0 translate-y-1 scale-95"
                                 class="absolute right-0 mt-2 w-64 rounded-2xl bg-gray-900/95 backdrop-blur-xl border border-white/[0.08] shadow-2xl shadow-black/40 p-1.5"
                                 x-cloak>
                                {{-- User info header --}}
                                <div class="px-3 py-2.5 mb-1">
                                    <p class="text-sm font-semibold text-white">{{ Auth::user()->name }}</p>
                                    <p class="text-xs text-gray-500 truncate">{{ Auth::user()->email }}</p>
                                </div>

                                <div class="h-px bg-white/[0.06] mx-1.5 mb-1"></div>

                                <a href="{{ route('profile.edit') }}" class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm text-gray-400 transition hover:bg-white/[0.06] hover:text-white">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                    </svg>
                                    Profile
                                </a>

                                <a href="{{ route('dashboard.subdomain.edit') }}" class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm text-gray-400 transition hover:bg-white/[0.06] hover:text-white">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5a17.92 17.92 0 0 1-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" />
                                    </svg>
                                    Subdomain
                                </a>

                                <a href="{{ route('billing.index') }}" class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm text-gray-400 transition hover:bg-white/[0.06] hover:text-white">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                                    </svg>
                                    Billing
                                </a>

                                <div class="h-px bg-white/[0.06] mx-1.5 my-1"></div>

                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-sm text-gray-400 transition hover:bg-white/[0.06] hover:text-white">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                                        </svg>
                                        Sign out
                                    </button>
                                </form>
                            </div>
                        </div>

                        {{-- Mobile hamburger --}}
                        <button @click="mobileOpen = !mobileOpen" class="sm:hidden rounded-lg p-2 text-gray-400 transition hover:bg-white/[0.06] hover:text-white">
                            <svg x-show="!mobileOpen" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                            </svg>
                            <svg x-show="mobileOpen" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" x-cloak>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Mobile menu --}}
            <div x-show="mobileOpen"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-2"
                 class="sm:hidden border-t border-white/[0.06]"
                 x-cloak>
                <div class="mx-auto max-w-7xl px-4 py-4 space-y-1">
                    <a href="{{ route('dashboard') }}"
                        @class([
                            'block rounded-xl px-3 py-2.5 text-sm font-medium transition',
                            'bg-white/[0.08] text-white' => request()->routeIs('dashboard') && !request()->routeIs('dashboard.*'),
                            'text-gray-400 hover:bg-white/[0.04] hover:text-white' => !request()->routeIs('dashboard') || request()->routeIs('dashboard.*'),
                        ])
                    >Devices</a>

                    <a href="{{ route('billing.index') }}"
                        @class([
                            'block rounded-xl px-3 py-2.5 text-sm font-medium transition',
                            'bg-white/[0.08] text-white' => request()->routeIs('billing.*'),
                            'text-gray-400 hover:bg-white/[0.04] hover:text-white' => !request()->routeIs('billing.*'),
                        ])
                    >Billing</a>

                    <a href="{{ route('profile.edit') }}" class="block rounded-xl px-3 py-2.5 text-sm font-medium text-gray-400 transition hover:bg-white/[0.04] hover:text-white">Profile</a>
                    <a href="{{ route('dashboard.subdomain.edit') }}" class="block rounded-xl px-3 py-2.5 text-sm font-medium text-gray-400 transition hover:bg-white/[0.04] hover:text-white">Subdomain</a>

                    @if (Auth::user()->subscriptionTier() === \App\Enums\SubscriptionTier::Free)
                        <a href="{{ route('billing.index') }}"
                           class="mt-3 flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-emerald-500 via-teal-500 to-cyan-500 shadow-lg shadow-emerald-500/25">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 0 0-2.455 2.456Z" />
                            </svg>
                            Upgrade Plan
                        </a>
                    @endif

                    <div class="h-px bg-white/[0.06] my-2"></div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2 rounded-xl px-3 py-2.5 text-sm font-medium text-gray-400 transition hover:bg-white/[0.04] hover:text-white">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                            </svg>
                            Sign out
                        </button>
                    </form>
                </div>
            </div>
        </nav>

        <!-- Page Content -->
        <main>
            @yield('content')
        </main>
    </div>
</body>
</html>
