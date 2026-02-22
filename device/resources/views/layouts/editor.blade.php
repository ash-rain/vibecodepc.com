<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Code Editor — VibeCodePC')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-gray-950 text-white antialiased h-screen flex flex-col overflow-hidden">
    {{ $slot }}

    @livewireScripts
</body>
</html>
