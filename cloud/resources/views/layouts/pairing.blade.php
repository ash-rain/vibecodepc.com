<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Pair Your Device') - VibeCodePC</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-gray-950 text-white antialiased">
    {{-- Decorative background glow --}}
    <div class="fixed inset-0 overflow-hidden pointer-events-none" aria-hidden="true">
        <div class="absolute top-[-20%] left-1/2 -translate-x-1/2 h-[500px] w-[800px] rounded-full bg-emerald-500/[0.07] blur-3xl"></div>
    </div>

    <div class="relative min-h-screen flex flex-col items-center justify-center px-4 py-12">
        <div class="mb-8">
            <a href="/"
                class="text-2xl font-bold bg-gradient-to-br from-emerald-400 to-teal-300 bg-clip-text text-transparent">
                VibeCodePC
            </a>
        </div>

        <div class="w-full max-w-md">
            @if (session('error'))
                <div class="mb-4 rounded-xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-400">
                    {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </div>
    </div>
</body>

</html>
