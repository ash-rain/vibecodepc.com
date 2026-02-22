<!DOCTYPE html>
<html lang="{{ $locale }}" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $content['meta']['title'] }}</title>
    <meta name="description" content="{{ $content['meta']['description'] }}">

    {{-- hreflang tags for SEO --}}
    <link rel="alternate" hreflang="en" href="{{ url('/') }}" />
    @foreach ($locales as $loc)
        @if ($loc !== 'en')
            <link rel="alternate" hreflang="{{ $loc }}" href="{{ url('/' . $loc) }}" />
        @endif
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ url('/') }}" />

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800|jetbrains-mono:400,500,700"
        rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="min-h-screen bg-gray-950 text-gray-100 antialiased">

    @php
        $whatsIncludedIcons = [
            '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H6.75A2.25 2.25 0 004.5 6.75v10.5a2.25 2.25 0 002.25 2.25z" />',
            '<path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />',
            '<path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-2.25-1.313M21 7.5v2.25m0-2.25l-2.25 1.313M3 7.5l2.25-1.313M3 7.5l2.25 1.313M3 7.5v2.25m9 3l2.25-1.313M12 12.75l-2.25-1.313M12 12.75V15m0 6.75l2.25-1.313M12 21.75V19.5m0 2.25l-2.25-1.313m0-16.875L12 2.25l2.25 1.313M21 14.25v2.25l-2.25 1.313m-13.5 0L3 16.5v-2.25" />',
            '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />',
            '<path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />',
            '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z" /><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.75v.75h-.75v-.75zM6.75 16.5h.75v.75h-.75v-.75zM16.5 6.75h.75v.75h-.75v-.75zM13.5 13.5h.75v.75h-.75v-.75zM13.5 19.5h.75v.75h-.75v-.75zM19.5 13.5h.75v.75h-.75v-.75zM19.5 19.5h.75v.75h-.75v-.75zM16.5 16.5h.75v.75h-.75v-.75z" />',
        ];

        $featureIcons = [
            '<path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456z" />',
            '<path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5" />',
            '<path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5a17.92 17.92 0 01-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" />',
            '<path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.737 5.1a3.375 3.375 0 012.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 01.9 2.7m0 0a3 3 0 01-3 3m0 3h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008zm-3 6h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008z" />',
            '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />',
            '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />',
        ];

        $platformIcons = [
            '<path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5a17.92 17.92 0 01-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" />',
            '<path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />',
            '<path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />',
            '<path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />',
        ];
    @endphp

    {{-- Nav --}}
    <nav class="fixed top-0 z-50 w-full border-b border-white/5 bg-gray-950/80 backdrop-blur-xl">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
            <a href="{{ $locale === 'en' ? '/' : '/' . $locale }}">
                <img src="{{ asset('storage/logo2.png') }}" alt="VibeCodePC" class="h-10" />
            </a>
            <div class="hidden items-center gap-6 text-sm text-gray-400 lg:flex">
                <a href="#how-it-works" class="transition hover:text-white">{{ $content['nav']['how_it_works'] }}</a>
                <a href="#whats-included" class="transition hover:text-white">{{ $content['nav']['whats_included'] }}</a>
                <a href="#software" class="transition hover:text-white">{{ $content['nav']['software'] }}</a>
                <a href="#features" class="transition hover:text-white">{{ $content['nav']['features'] }}</a>
                <a href="#pricing" class="transition hover:text-white">{{ $content['nav']['pricing'] }}</a>
                <a href="#specs" class="transition hover:text-white">{{ $content['nav']['specs'] }}</a>
                <a href="#faq" class="transition hover:text-white">{{ $content['nav']['faq'] }}</a>
                <a href="#waitlist"
                    class="rounded-lg bg-white/5 px-4 py-2 font-medium text-white transition hover:bg-white/10">{{ $content['nav']['join_waitlist'] }}</a>

                {{-- Language Switcher --}}
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" @click.outside="open = false"
                        class="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-gray-400 transition hover:bg-white/5 hover:text-white">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5a17.92 17.92 0 01-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" />
                        </svg>
                        <span class="uppercase text-xs font-semibold">{{ $locale }}</span>
                        <svg class="h-3 w-3 transition-transform" :class="open ? 'rotate-180' : ''" fill="none"
                            viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>
                    <div x-show="open" x-transition
                        class="absolute right-0 top-full mt-2 w-40 rounded-xl border border-white/10 bg-gray-900 py-1 shadow-xl">
                        @foreach ($content['languages'] as $code => $name)
                            <a href="{{ $code === 'en' ? '/' : '/' . $code }}"
                                class="block px-4 py-2 text-sm transition {{ $code === $locale ? 'text-emerald-400 bg-emerald-500/5' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                                {{ $name }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </nav>

    {{-- Hero --}}
    <section class="relative overflow-hidden pt-32 pb-20 sm:pt-40 sm:pb-28">
        <div
            class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.03)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.03)_1px,transparent_1px)] bg-[size:64px_64px]">
        </div>
        <div
            class="absolute top-0 left-1/2 -translate-x-1/2 h-[600px] w-[800px] rounded-full bg-emerald-500/10 blur-[128px]">
        </div>

        <div class="relative mx-auto max-w-4xl px-6 text-center">
            <div
                class="mb-6 inline-flex items-center gap-2 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-4 py-1.5 text-sm font-medium text-emerald-400">
                <span class="relative flex h-2 w-2">
                    <span
                        class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                </span>
                {{ $content['hero']['badge'] }}
            </div>

            <h1 class="text-4xl font-extrabold tracking-tight sm:text-6xl lg:text-7xl">
                {{ $content['hero']['title_line1'] }}<br>
                <span
                    class="bg-gradient-to-r from-emerald-400 to-teal-300 bg-clip-text text-transparent">{{ $content['hero']['title_line2'] }}</span>
            </h1>

            <p class="mx-auto mt-6 max-w-2xl text-lg text-gray-400 sm:text-xl">
                {{ $content['hero']['description'] }}
            </p>

            <div class="mx-auto mt-10 max-w-2xl">
                <img src="{{ asset('storage/pcs1.png') }}" alt="{{ $content['hero']['image_alt'] }}"
                    class="w-full rounded-2xl border border-white/10 shadow-2xl shadow-emerald-500/10" width="1024"
                    height="682" loading="eager">
            </div>

            <div class="mt-10 flex justify-center" id="waitlist">
                <livewire:waitlist-form />
            </div>

            <p class="mt-4 text-sm text-gray-600">{{ $content['hero']['spam_notice'] }}</p>
        </div>
    </section>

    {{-- How It Works --}}
    <section id="how-it-works" class="relative border-t border-white/5 py-24">
        <div class="mx-auto max-w-6xl px-6">
            <div class="text-center">
                <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ $content['how_it_works']['title'] }}</h2>
                <p class="mt-4 text-gray-400 text-lg">{{ $content['how_it_works']['subtitle'] }}</p>
            </div>

            <div class="mt-16 grid gap-8 md:grid-cols-3">
                @foreach ($content['how_it_works']['steps'] as $step)
                    <div
                        class="group relative rounded-2xl border border-white/5 bg-white/[0.02] p-8 transition hover:border-emerald-500/20 hover:bg-emerald-500/[0.02]">
                        <div
                            class="mb-5 flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-400 font-bold font-mono text-lg">
                            {{ $step['number'] }}</div>
                        <h3 class="text-xl font-semibold">{{ $step['title'] }}</h3>
                        <p class="mt-3 text-gray-400 leading-relaxed">{!! $step['description'] !!}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Product Showcase --}}
    <section class="relative border-t border-white/5 py-24">
        <div class="mx-auto max-w-5xl px-6">
            <img src="{{ asset('storage/pcs2.png') }}" alt="{{ $content['showcase']['image_alt'] }}"
                class="w-full rounded-2xl border border-white/10 shadow-2xl shadow-emerald-500/10" width="1024"
                height="682" loading="lazy">
            <p class="mt-6 text-center text-sm text-gray-500">{{ $content['showcase']['caption'] }}</p>
        </div>
    </section>

    {{-- What's in the Box --}}
    <section id="whats-included" class="relative border-t border-white/5 py-24">
        <div class="mx-auto max-w-6xl px-6">
            <div class="text-center">
                <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ $content['whats_included']['title'] }}
                </h2>
                <p class="mt-4 text-gray-400 text-lg">{{ $content['whats_included']['subtitle'] }}</p>
            </div>

            <div class="mt-16 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($content['whats_included']['items'] as $i => $item)
                    <div class="flex items-start gap-4 rounded-2xl border border-white/5 bg-white/[0.02] p-6">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10">
                            <svg class="h-5 w-5 text-emerald-400" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor">
                                {!! $whatsIncludedIcons[$i] !!}
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold">{{ $item['title'] }}</h3>
                            <p class="mt-1 text-sm text-gray-400">{{ $item['description'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Pre-installed Software --}}
    <section id="software" class="relative border-t border-white/5 py-24">
        <div class="mx-auto max-w-4xl px-6">
            <div class="text-center">
                <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ $content['software']['title'] }}</h2>
                <p class="mt-4 text-gray-400 text-lg">{{ $content['software']['subtitle'] }}</p>
            </div>

            <div class="mt-12 grid gap-4 sm:grid-cols-2">
                @foreach ($content['software']['items'] as $item)
                    <div
                        class="flex items-start gap-3 rounded-xl border border-white/5 bg-white/[0.02] px-5 py-4 transition hover:border-emerald-500/20">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24"
                            stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        <div>
                            <span class="font-semibold text-gray-200">{{ $item['name'] }}</span>
                            <span class="text-sm text-gray-500"> — {{ $item['detail'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Features --}}
    <section id="features" class="relative overflow-hidden border-t border-white/5 py-24">
        <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
            <img src="{{ asset('storage/logo1.png') }}" alt="" class="w-[900px] max-w-none opacity-25 blur-sm" />
        </div>
        <div class="relative mx-auto max-w-6xl px-6">
            <div class="text-center">
                <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ $content['features']['title'] }}</h2>
                <p class="mt-4 text-gray-400 text-lg">{{ $content['features']['subtitle'] }}</p>
            </div>

            <div class="mt-16 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($content['features']['items'] as $i => $item)
                    <div
                        class="rounded-2xl border border-white/5 bg-white/[0.02] p-6 transition hover:border-white/10">
                        <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-lg bg-white/5">
                            <svg class="h-5 w-5 text-emerald-400" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor">
                                {!! $featureIcons[$i] !!}
                            </svg>
                        </div>
                        <h3 class="font-semibold text-lg">{{ $item['title'] }}</h3>
                        <p class="mt-2 text-sm text-gray-400 leading-relaxed">{{ $item['description'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Who Is This For --}}
    <section id="who" class="relative border-t border-white/5 py-24">
        <div class="mx-auto max-w-6xl px-6">
            <div class="text-center">
                <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ $content['who']['title'] }}</h2>
                <p class="mt-4 text-gray-400 text-lg">{{ $content['who']['subtitle'] }}</p>
            </div>

            <div class="mt-16 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($content['who']['items'] as $item)
                    <div
                        class="rounded-2xl border border-white/5 bg-white/[0.02] p-6 transition hover:border-emerald-500/20">
                        <div class="mb-4 text-3xl">{{ $item['emoji'] }}</div>
                        <h3 class="font-semibold text-lg">{{ $item['title'] }}</h3>
                        <p class="mt-2 text-sm text-gray-400 leading-relaxed">{{ $item['description'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- The Platform --}}
    <section id="platform" class="relative border-t border-white/5 py-24">
        <div class="mx-auto max-w-5xl px-6">
            <div class="text-center">
                <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ $content['platform']['title'] }}</h2>
                <p class="mt-4 text-gray-400 text-lg">{{ $content['platform']['subtitle'] }}</p>
            </div>

            <div class="mt-16 grid gap-8 sm:grid-cols-2">
                @foreach ($content['platform']['features'] as $i => $feature)
                    <div class="flex items-start gap-4">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10">
                            <svg class="h-5 w-5 text-emerald-400" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor">
                                {!! $platformIcons[$i] !!}
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-lg">{{ $feature['title'] }}</h3>
                            <p class="mt-1 text-sm text-gray-400 leading-relaxed">{{ $feature['description'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            <p class="mt-12 text-center text-sm text-gray-600">{{ $content['platform']['note'] }}</p>
        </div>
    </section>

    {{-- Pricing Teaser --}}
    <section id="pricing" class="relative border-t border-white/5 py-24">
        <div class="mx-auto max-w-3xl px-6 text-center">
            <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ $content['pricing']['title'] }}</h2>
            <p class="mt-4 text-gray-400 text-lg">{{ $content['pricing']['subtitle'] }}</p>

            <div class="mt-12 grid gap-6 sm:grid-cols-2">
                {{-- Hardware --}}
                <div class="rounded-2xl border border-white/10 bg-white/[0.02] p-8 text-left">
                    <div class="text-sm font-medium text-emerald-400 uppercase tracking-wider">
                        {{ $content['pricing']['device']['label'] }}</div>
                    <div class="mt-4 flex items-baseline gap-2">
                        <span class="text-4xl font-extrabold">{{ $content['pricing']['device']['price'] }}</span>
                        <span class="text-gray-500">{{ $content['pricing']['device']['period'] }}</span>
                    </div>
                    <ul class="mt-6 space-y-3 text-sm text-gray-400">
                        @foreach ($content['pricing']['device']['features'] as $feature)
                            <li class="flex items-start gap-2">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" fill="none"
                                    viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                                {{ $feature }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- Subscription --}}
                <div class="relative rounded-2xl border border-emerald-500/30 bg-emerald-500/[0.03] p-8 text-left">
                    <div
                        class="absolute -top-3 right-6 rounded-full bg-emerald-500 px-3 py-0.5 text-xs font-semibold text-gray-950">
                        {{ $content['pricing']['subscription']['badge'] }}</div>
                    <div class="text-sm font-medium text-emerald-400 uppercase tracking-wider">
                        {{ $content['pricing']['subscription']['label'] }}</div>
                    <div class="mt-4 flex items-baseline gap-2">
                        <span
                            class="text-4xl font-extrabold">{{ $content['pricing']['subscription']['price'] }}</span>
                        <span class="text-gray-500">{{ $content['pricing']['subscription']['period'] }}</span>
                    </div>
                    <ul class="mt-6 space-y-3 text-sm text-gray-400">
                        @foreach ($content['pricing']['subscription']['features'] as $feature)
                            <li class="flex items-start gap-2">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" fill="none"
                                    viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                                {!! $feature !!}
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <p class="mt-6 text-sm text-gray-600">{{ $content['pricing']['footnote'] }}</p>
        </div>
    </section>

    {{-- Tech Specs --}}
    <section id="specs" class="relative border-t border-white/5 py-24">
        <div class="mx-auto max-w-4xl px-6">
            <div class="text-center">
                <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ $content['specs']['title'] }}</h2>
                <p class="mt-4 text-gray-400 text-lg">{{ $content['specs']['subtitle'] }}</p>
            </div>

            <div class="mt-12 overflow-hidden rounded-2xl border border-white/10">
                <table class="w-full text-left text-sm">
                    <tbody class="divide-y divide-white/5">
                        @foreach ($content['specs']['rows'] as $i => $row)
                            <tr @class(['bg-white/[0.02]' => $i % 2 === 0])>
                                <td class="px-6 py-4 font-medium text-gray-300 whitespace-nowrap">
                                    {{ $row['label'] }}</td>
                                <td class="px-6 py-4 text-gray-400">{{ $row['value'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-8 text-center">
                <p class="text-sm text-gray-500">{{ $content['specs']['footnote'] }}</p>
            </div>
        </div>
    </section>

    {{-- Stretch Goals --}}
    <section id="stretch-goals" class="relative border-t border-white/5 py-24">
        <div class="mx-auto max-w-4xl px-6">
            <div class="text-center">
                <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ $content['stretch_goals']['title'] }}
                </h2>
                <p class="mt-4 text-gray-400 text-lg">{{ $content['stretch_goals']['subtitle'] }}</p>
            </div>

            <div class="mt-12 space-y-4">
                @foreach ($content['stretch_goals']['goals'] as $i => $goal)
                    <div @class([
                        'relative flex items-center gap-6 rounded-2xl border p-6 transition',
                        'border-emerald-500/30 bg-emerald-500/[0.03]' => $i === 0,
                        'border-white/5 bg-white/[0.02]' => $i !== 0,
                    ])>
                        <div class="shrink-0 text-right" style="min-width: 5rem">
                            <span @class([
                                'text-lg font-bold font-mono',
                                'text-emerald-400' => $i === 0,
                                'text-gray-300' => $i !== 0,
                            ])>{{ $goal['amount'] }}</span>
                        </div>
                        <div
                            class="flex h-3 w-3 shrink-0 items-center justify-center rounded-full {{ $i === 0 ? 'bg-emerald-500' : 'border-2 border-gray-600' }}">
                        </div>
                        <div>
                            <span
                                class="font-semibold {{ $i === 0 ? 'text-emerald-400' : 'text-gray-200' }}">{{ $goal['label'] }}</span>
                            <span class="text-sm text-gray-500"> — {{ $goal['description'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Terminal Preview --}}
    <section class="relative border-t border-white/5 py-24">
        <div class="mx-auto max-w-3xl px-6">
            <div
                class="overflow-hidden rounded-2xl border border-white/10 bg-gray-900 shadow-2xl shadow-emerald-500/5">
                <div class="flex items-center gap-2 border-b border-white/5 bg-gray-900/50 px-4 py-3">
                    <div class="h-3 w-3 rounded-full bg-red-500/70"></div>
                    <div class="h-3 w-3 rounded-full bg-yellow-500/70"></div>
                    <div class="h-3 w-3 rounded-full bg-emerald-500/70"></div>
                    <span class="ml-3 text-xs text-gray-500 font-mono">{{ $content['terminal']['window_title'] }}</span>
                </div>
                <div class="p-6 font-mono text-sm leading-relaxed">
                    <p class="text-gray-500">$ vibecodepc status</p>
                    <p class="mt-2 text-emerald-400">VibeCodePC v1.0.0 — Online</p>
                    <p class="text-gray-400 mt-1">Device ID &nbsp; <span
                            class="text-gray-300">a1b2c3d4-e5f6-7890-abcd-ef1234567890</span></p>
                    <p class="text-gray-400">Owner &nbsp;&nbsp;&nbsp;&nbsp; <span class="text-gray-300">boyan</span>
                    </p>
                    <p class="text-gray-400">Subdomain &nbsp; <span
                            class="text-emerald-400">boyan.vibecodepc.com</span></p>
                    <p class="mt-3 text-gray-400">AI Services:</p>
                    <p class="text-emerald-400">&nbsp; OpenAI &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; connected</p>
                    <p class="text-emerald-400">&nbsp; Anthropic &nbsp;&nbsp; connected</p>
                    <p class="text-emerald-400">&nbsp; Copilot &nbsp;&nbsp;&nbsp;&nbsp; connected</p>
                    <p class="text-gray-500">&nbsp; HuggingFace &nbsp;skipped</p>
                    <p class="mt-3 text-gray-400">Projects:</p>
                    <p class="text-gray-300">&nbsp; my-saas &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span
                            class="text-emerald-400">running</span> &nbsp; port 8000 &nbsp; <span
                            class="text-emerald-400">tunneled</span></p>
                    <p class="text-gray-300">&nbsp; portfolio &nbsp;&nbsp;&nbsp;<span
                            class="text-emerald-400">running</span> &nbsp; port 3000 &nbsp; <span
                            class="text-gray-500">local only</span></p>
                    <p class="text-gray-300">&nbsp; discord-bot &nbsp;<span class="text-yellow-400">stopped</span></p>
                    <p class="mt-3 text-gray-400">System: <span class="text-gray-300">CPU 12%</span> &nbsp; <span
                            class="text-gray-300">RAM 1.8/16 GB</span> &nbsp; <span class="text-gray-300">Disk 24/128
                            GB</span> &nbsp; <span class="text-gray-300">Temp 48C</span></p>
                    <p class="mt-2 text-gray-500">$ <span class="animate-pulse">_</span></p>
                </div>
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section id="faq" class="relative border-t border-white/5 py-24">
        <div class="mx-auto max-w-3xl px-6">
            <div class="text-center">
                <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ $content['faq']['title'] }}</h2>
                <p class="mt-4 text-gray-400 text-lg">{{ $content['faq']['subtitle'] }}</p>
            </div>

            <div class="mt-12 space-y-4" x-data="{ open: null }">
                @foreach ($content['faq']['items'] as $i => $item)
                    @php $idx = $i + 1; @endphp
                    <div class="rounded-2xl border border-white/5 bg-white/[0.02] transition"
                        :class="open === {{ $idx }} ? 'border-emerald-500/20' : ''">
                        <button @click="open = open === {{ $idx }} ? null : {{ $idx }}"
                            class="flex w-full items-center justify-between px-6 py-5 text-left">
                            <span class="font-medium">{{ $item['question'] }}</span>
                            <svg class="h-5 w-5 shrink-0 text-gray-500 transition-transform duration-200"
                                :class="open === {{ $idx }} ? 'rotate-180' : ''" fill="none"
                                viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>
                        <div x-show="open === {{ $idx }}" x-collapse class="px-6 pb-5">
                            <p class="text-sm text-gray-400 leading-relaxed">{{ $item['answer'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Final CTA --}}
    <section class="relative overflow-hidden border-t border-white/5 py-24">
        <div class="absolute inset-0 bg-gradient-to-t from-emerald-500/5 to-transparent"></div>
        <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
            <img src="{{ asset('storage/logo1.png') }}" alt="" class="w-[700px] max-w-none opacity-25 blur-sm" />
        </div>
        <div class="relative mx-auto max-w-2xl px-6 text-center">
            <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ $content['cta']['title'] }}</h2>
            <p class="mt-4 text-gray-400 text-lg">{{ $content['cta']['description'] }}</p>
            <div class="mt-8 flex justify-center">
                <livewire:waitlist-form />
            </div>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="border-t border-white/5 py-12">
        <div class="mx-auto max-w-6xl px-6">
            <div class="flex flex-col items-center justify-between gap-6 sm:flex-row">
                <img src="{{ asset('storage/logo1.png') }}" alt="VibeCodePC" class="h-12" />
                <p class="text-sm text-gray-600">&copy; {{ date('Y') }} {{ $content['footer']['copyright'] }}</p>
            </div>
        </div>
    </footer>

    @livewireScripts
</body>

</html>
