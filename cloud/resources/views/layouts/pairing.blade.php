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
    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-12">
        <div class="mb-8">
            <a href="/" class="text-2xl font-bold text-amber-400">VibeCodePC</a>
        </div>

        <div class="w-full max-w-md">
            @if (session('error'))
                <div class="mb-4 rounded-lg bg-red-900/50 border border-red-700 px-4 py-3 text-sm text-red-200">
                    {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </div>
    </div>
</body>

</html>
