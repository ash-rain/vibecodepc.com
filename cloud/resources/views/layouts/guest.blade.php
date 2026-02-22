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

<body class="font-sans antialiased text-gray-100 bg-gray-950">
    {{-- Full-screen background layer --}}
    <div class="fixed inset-0 z-0">
        {{-- Gradient base --}}
        <div class="absolute inset-0 bg-gradient-to-br from-gray-950 via-gray-900 to-gray-950"></div>
        {{-- Emerald glow top-right --}}
        <div class="absolute -top-40 -right-40 w-[600px] h-[600px] bg-emerald-500/[0.07] rounded-full blur-3xl"></div>
        {{-- Teal glow bottom-left --}}
        <div class="absolute -bottom-40 -left-40 w-[500px] h-[500px] bg-teal-500/[0.05] rounded-full blur-3xl"></div>
        {{-- Logo watermark — top left --}}
        <div class="absolute top-6 left-6">
            <img src="{{ asset('storage/logo1.png') }}" alt=""
                class="w-[600px] h-[600px] object-contain opacity-25 select-none pointer-events-none" draggable="false">
        </div>
    </div>

    {{-- Content --}}
    <div class="relative z-10 flex flex-col items-center min-h-screen px-4 pt-6 sm:justify-center sm:pt-0">
        <div class="mb-8">
            <a href="/" class="flex items-center gap-2.5">
                <div
                    class="flex items-center justify-center shadow-lg w-9 h-9 bg-gradient-to-br from-emerald-400 to-teal-500 rounded-xl shadow-emerald-500/20">
                    <svg class="w-5 h-5 text-gray-950" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                            d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                    </svg>
                </div>
                <span
                    class="text-xl font-extrabold tracking-tight text-transparent bg-gradient-to-r from-emerald-400 via-teal-300 to-cyan-400 bg-clip-text">VibeCodePC</span>
            </a>
        </div>

        <div
            class="w-full sm:max-w-md px-8 py-10 bg-white/[0.03] border border-white/[0.08] backdrop-blur-2xl overflow-hidden sm:rounded-3xl shadow-2xl shadow-black/50 ring-1 ring-white/[0.05]">
            @yield('content')
        </div>

        <p class="mt-8 text-xs tracking-wide text-gray-600">&copy; {{ date('Y') }} VibeCodePC. All rights reserved.
        </p>
    </div>
</body>

</html>
