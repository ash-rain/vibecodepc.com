<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard — VibeCodePC')</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|jetbrains-mono:400,500" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-gray-950 text-white antialiased font-sans min-h-screen" x-data="{ sidebarOpen: false }">
    <div class="flex min-h-screen">
        {{-- Sidebar --}}
        <x-dashboard.sidebar />

        {{-- Main content area --}}
        <div class="flex-1 flex flex-col min-w-0 lg:ml-64">
            {{-- Top bar --}}
            <x-dashboard.top-bar :title="$title ?? 'Dashboard'" />

            {{-- Page content --}}
            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                {{ $slot }}
            </main>
        </div>
    </div>

    {{-- Mobile sidebar overlay --}}
    <div
        x-show="sidebarOpen"
        x-transition:enter="transition-opacity ease-linear duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-linear duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="sidebarOpen = false"
        class="fixed inset-0 z-30 bg-black/50 lg:hidden"
    ></div>

    @livewireScripts
</body>
</html>
