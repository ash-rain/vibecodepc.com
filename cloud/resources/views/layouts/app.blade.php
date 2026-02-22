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
        <nav class="sticky top-0 z-50 border-b border-white/5 bg-gray-950/80 backdrop-blur-xl">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 items-center justify-between">
                    <div class="flex items-center gap-8">
                        <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
                            @if (file_exists(public_path('storage/logo2.png')))
                                <img src="{{ asset('storage/logo2.png') }}" alt="VibeCodePC" class="h-8" />
                            @else
                                <span class="text-lg font-bold bg-gradient-to-r from-emerald-400 to-teal-300 bg-clip-text text-transparent">VibeCodePC</span>
                            @endif
                        </a>

                        <div class="hidden sm:flex items-center gap-1">
                            <a href="{{ route('dashboard') }}"
                                @class([
                                    'px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
                                    'bg-white/10 text-white' => request()->routeIs('dashboard') && !request()->routeIs('dashboard.*'),
                                    'text-gray-400 hover:text-white hover:bg-white/5' => !request()->routeIs('dashboard') || request()->routeIs('dashboard.*'),
                                ])
                            >Dashboard</a>

                            @if (request()->routeIs('dashboard.devices.*'))
                                <svg class="h-4 w-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                </svg>
                                <span class="px-3 py-1.5 rounded-lg text-sm font-medium bg-white/10 text-white">Device</span>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <div class="hidden sm:flex items-center gap-2">
                            <code class="rounded-md bg-emerald-500/10 border border-emerald-500/20 px-2.5 py-1 text-xs font-mono text-emerald-400">{{ Auth::user()->username }}.vibecodepc.com</code>
                        </div>

                        <div class="flex items-center gap-3 pl-3 border-l border-white/10">
                            <span class="text-sm text-gray-400">{{ Auth::user()->name }}</span>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="rounded-lg p-1.5 text-gray-500 transition hover:bg-white/5 hover:text-gray-300" title="Log out">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
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
