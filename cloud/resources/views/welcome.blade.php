<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VibeCodePC — Plug in. Scan. Code. Ship.</title>
    <meta name="description" content="A Raspberry Pi 5 that comes ready to code with AI. Scan the QR, connect your accounts, deploy to the web in minutes.">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800|jetbrains-mono:400,500,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-gray-950 text-gray-100 antialiased">

    {{-- Nav --}}
    <nav class="fixed top-0 z-50 w-full border-b border-white/5 bg-gray-950/80 backdrop-blur-xl">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
            <a href="/">
                <img src="{{ asset('storage/logo2.png') }}" alt="VibeCodePC" class="h-10" />
            </a>
            <div class="hidden items-center gap-8 text-sm text-gray-400 md:flex">
                <a href="#how-it-works" class="transition hover:text-white">How It Works</a>
                <a href="#features" class="transition hover:text-white">Features</a>
                <a href="#pricing" class="transition hover:text-white">Pricing</a>
                <a href="#waitlist" class="rounded-lg bg-white/5 px-4 py-2 font-medium text-white transition hover:bg-white/10">Join Waitlist</a>
            </div>
        </div>
    </nav>

    {{-- Hero --}}
    <section class="relative overflow-hidden pt-32 pb-20 sm:pt-40 sm:pb-28">
        {{-- Grid background --}}
        <div class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.03)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.03)_1px,transparent_1px)] bg-[size:64px_64px]"></div>
        {{-- Glow --}}
        <div class="absolute top-0 left-1/2 -translate-x-1/2 h-[600px] w-[800px] rounded-full bg-emerald-500/10 blur-[128px]"></div>

        <div class="relative mx-auto max-w-4xl px-6 text-center">
            <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-4 py-1.5 text-sm font-medium text-emerald-400">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                </span>
                Coming Soon — Join the Waitlist
            </div>

            <h1 class="text-4xl font-extrabold tracking-tight sm:text-6xl lg:text-7xl">
                Plug in. Scan.<br>
                <span class="bg-gradient-to-r from-emerald-400 to-teal-300 bg-clip-text text-transparent">Code. Ship.</span>
            </h1>

            <p class="mx-auto mt-6 max-w-2xl text-lg text-gray-400 sm:text-xl">
                A Raspberry Pi 5 that comes ready to code with AI. Scan the QR code, connect ChatGPT, Copilot &amp; more, and deploy your projects to the web — all from a tiny box on your desk.
            </p>

            <div class="mx-auto mt-10 max-w-2xl">
                <img
                    src="{{ asset('storage/pcs1.png') }}"
                    alt="Two VibeCodePC devices — a Raspberry Pi 5 in a custom case with green LED accents and visible ports"
                    class="w-full rounded-2xl border border-white/10 shadow-2xl shadow-emerald-500/10"
                    width="1024"
                    height="682"
                    loading="eager"
                >
            </div>

            <div class="mt-10 flex justify-center" id="waitlist">
                <livewire:waitlist-form />
            </div>

            <p class="mt-4 text-sm text-gray-600">No spam. Unsubscribe anytime. We'll email you once at launch.</p>
        </div>
    </section>

    {{-- How It Works --}}
    <section id="how-it-works" class="relative border-t border-white/5 py-24">
        <div class="mx-auto max-w-6xl px-6">
            <div class="text-center">
                <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">From box to deployed in 5 minutes</h2>
                <p class="mt-4 text-gray-400 text-lg">Three steps. No terminal. No Linux experience needed.</p>
            </div>

            <div class="mt-16 grid gap-8 md:grid-cols-3">
                {{-- Step 1 --}}
                <div class="group relative rounded-2xl border border-white/5 bg-white/[0.02] p-8 transition hover:border-emerald-500/20 hover:bg-emerald-500/[0.02]">
                    <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-400 font-bold font-mono text-lg">1</div>
                    <h3 class="text-xl font-semibold">Scan the QR Code</h3>
                    <p class="mt-3 text-gray-400 leading-relaxed">
                        Plug in your VibeCodePC and scan the QR code on the device with your phone. Create your account and claim your device at <strong class="text-gray-300">id.vibecodepc.com</strong>.
                    </p>
                </div>

                {{-- Step 2 --}}
                <div class="group relative rounded-2xl border border-white/5 bg-white/[0.02] p-8 transition hover:border-emerald-500/20 hover:bg-emerald-500/[0.02]">
                    <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-400 font-bold font-mono text-lg">2</div>
                    <h3 class="text-xl font-semibold">Connect Your AI &amp; Code</h3>
                    <p class="mt-3 text-gray-400 leading-relaxed">
                        A guided wizard walks you through connecting OpenAI, Anthropic, Copilot, and more. VS Code is pre-installed and configured automatically.
                    </p>
                </div>

                {{-- Step 3 --}}
                <div class="group relative rounded-2xl border border-white/5 bg-white/[0.02] p-8 transition hover:border-emerald-500/20 hover:bg-emerald-500/[0.02]">
                    <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-400 font-bold font-mono text-lg">3</div>
                    <h3 class="text-xl font-semibold">Build &amp; Deploy</h3>
                    <p class="mt-3 text-gray-400 leading-relaxed">
                        Create projects from templates, code with AI assistance, and deploy instantly to <strong class="text-gray-300">yourname.vibecodepc.com</strong> with one click.
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- Product Showcase --}}
    <section class="relative border-t border-white/5 py-24">
        <div class="mx-auto max-w-5xl px-6">
            <img
                src="{{ asset('storage/pcs2.png') }}"
                alt="Three VibeCodePC devices in different sizes — Raspberry Pi 5 units in custom armored cases with green LED lighting"
                class="w-full rounded-2xl border border-white/10 shadow-2xl shadow-emerald-500/10"
                width="1024"
                height="682"
                loading="lazy"
            >
            <p class="mt-6 text-center text-sm text-gray-500">Custom-designed enclosures. Serious hardware. Tiny footprint.</p>
        </div>
    </section>

    {{-- Features --}}
    <section id="features" class="relative border-t border-white/5 py-24">
        <div class="mx-auto max-w-6xl px-6">
            <div class="text-center">
                <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Everything you need to build</h2>
                <p class="mt-4 text-gray-400 text-lg">Pre-configured, always-on, and entirely yours.</p>
            </div>

            <div class="mt-16 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                {{-- AI-First --}}
                <div class="rounded-2xl border border-white/5 bg-white/[0.02] p-6 transition hover:border-white/10">
                    <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-lg bg-white/5">
                        <svg class="h-5 w-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456z" />
                        </svg>
                    </div>
                    <h3 class="font-semibold text-lg">AI-First Development</h3>
                    <p class="mt-2 text-sm text-gray-400 leading-relaxed">ChatGPT, Claude, Copilot, OpenRouter, HuggingFace — connect them all in the setup wizard. Your API keys, stored encrypted on your hardware.</p>
                </div>

                {{-- VS Code --}}
                <div class="rounded-2xl border border-white/5 bg-white/[0.02] p-6 transition hover:border-white/10">
                    <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-lg bg-white/5">
                        <svg class="h-5 w-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5" />
                        </svg>
                    </div>
                    <h3 class="font-semibold text-lg">VS Code in Your Browser</h3>
                    <p class="mt-2 text-sm text-gray-400 leading-relaxed">Full Visual Studio Code runs on the device. Open it from any browser on your network. Copilot and extensions pre-installed.</p>
                </div>

                {{-- Deploy --}}
                <div class="rounded-2xl border border-white/5 bg-white/[0.02] p-6 transition hover:border-white/10">
                    <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-lg bg-white/5">
                        <svg class="h-5 w-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5a17.92 17.92 0 01-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" />
                        </svg>
                    </div>
                    <h3 class="font-semibold text-lg">Deploy to the Web</h3>
                    <p class="mt-2 text-sm text-gray-400 leading-relaxed">Secure HTTPS tunnels publish your projects at yourname.vibecodepc.com. Toggle deployments on and off from the dashboard.</p>
                </div>

                {{-- Your Hardware --}}
                <div class="rounded-2xl border border-white/5 bg-white/[0.02] p-6 transition hover:border-white/10">
                    <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-lg bg-white/5">
                        <svg class="h-5 w-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.737 5.1a3.375 3.375 0 012.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 01.9 2.7m0 0a3 3 0 01-3 3m0 3h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008zm-3 6h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008z" />
                        </svg>
                    </div>
                    <h3 class="font-semibold text-lg">Your Hardware, Your Data</h3>
                    <p class="mt-2 text-sm text-gray-400 leading-relaxed">Everything runs on a Raspberry Pi 5 on your desk. No cloud dependency. No monthly compute bill. Your code and keys never leave your device.</p>
                </div>

                {{-- Templates --}}
                <div class="rounded-2xl border border-white/5 bg-white/[0.02] p-6 transition hover:border-white/10">
                    <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-lg bg-white/5">
                        <svg class="h-5 w-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                        </svg>
                    </div>
                    <h3 class="font-semibold text-lg">Project Templates</h3>
                    <p class="mt-2 text-sm text-gray-400 leading-relaxed">Start with Laravel, Next.js, Astro, Python, or plain HTML. Each template comes pre-wired with your AI services and ready to deploy.</p>
                </div>

                {{-- Low Power --}}
                <div class="rounded-2xl border border-white/5 bg-white/[0.02] p-6 transition hover:border-white/10">
                    <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-lg bg-white/5">
                        <svg class="h-5 w-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                        </svg>
                    </div>
                    <h3 class="font-semibold text-lg">Always On, Ultra Low Power</h3>
                    <p class="mt-2 text-sm text-gray-400 leading-relaxed">The Raspberry Pi 5 draws just 15W. Leave it running 24/7 for pennies. Your projects stay live, your tunnels stay open.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Pricing Teaser --}}
    <section id="pricing" class="relative border-t border-white/5 py-24">
        <div class="mx-auto max-w-3xl px-6 text-center">
            <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Simple, transparent pricing</h2>
            <p class="mt-4 text-gray-400 text-lg">One device. Optional subscription for web publishing.</p>

            <div class="mt-12 grid gap-6 sm:grid-cols-2">
                {{-- Hardware --}}
                <div class="rounded-2xl border border-white/10 bg-white/[0.02] p-8 text-left">
                    <div class="text-sm font-medium text-emerald-400 uppercase tracking-wider">The Device</div>
                    <div class="mt-4 flex items-baseline gap-2">
                        <span class="text-4xl font-extrabold">$299</span>
                        <span class="text-gray-500">one-time</span>
                    </div>
                    <ul class="mt-6 space-y-3 text-sm text-gray-400">
                        <li class="flex items-start gap-2">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                            Raspberry Pi 5 — 8 GB RAM
                        </li>
                        <li class="flex items-start gap-2">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                            256 GB NVMe SSD
                        </li>
                        <li class="flex items-start gap-2">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                            Custom case, PSU, Ethernet cable
                        </li>
                        <li class="flex items-start gap-2">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                            Pre-installed: VS Code, Docker, Node, PHP, Python
                        </li>
                        <li class="flex items-start gap-2">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                            Works fully offline — no subscription required
                        </li>
                    </ul>
                </div>

                {{-- Subscription --}}
                <div class="relative rounded-2xl border border-emerald-500/30 bg-emerald-500/[0.03] p-8 text-left">
                    <div class="absolute -top-3 right-6 rounded-full bg-emerald-500 px-3 py-0.5 text-xs font-semibold text-gray-950">Popular</div>
                    <div class="text-sm font-medium text-emerald-400 uppercase tracking-wider">Starter Plan</div>
                    <div class="mt-4 flex items-baseline gap-2">
                        <span class="text-4xl font-extrabold">$5</span>
                        <span class="text-gray-500">/month</span>
                    </div>
                    <ul class="mt-6 space-y-3 text-sm text-gray-400">
                        <li class="flex items-start gap-2">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                            Your subdomain: <strong class="text-gray-300">you.vibecodepc.com</strong>
                        </li>
                        <li class="flex items-start gap-2">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                            HTTPS tunnel to your device
                        </li>
                        <li class="flex items-start gap-2">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                            10 GB bandwidth / month
                        </li>
                        <li class="flex items-start gap-2">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                            Community support + Discord
                        </li>
                        <li class="flex items-start gap-2">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                            Pro &amp; Team tiers coming soon
                        </li>
                    </ul>
                </div>
            </div>

            <p class="mt-6 text-sm text-gray-600">Early-bird pricing for waitlist members. Final pricing announced at launch.</p>
        </div>
    </section>

    {{-- Terminal Preview --}}
    <section class="relative border-t border-white/5 py-24">
        <div class="mx-auto max-w-3xl px-6">
            <div class="overflow-hidden rounded-2xl border border-white/10 bg-gray-900 shadow-2xl shadow-emerald-500/5">
                {{-- Window chrome --}}
                <div class="flex items-center gap-2 border-b border-white/5 bg-gray-900/50 px-4 py-3">
                    <div class="h-3 w-3 rounded-full bg-red-500/70"></div>
                    <div class="h-3 w-3 rounded-full bg-yellow-500/70"></div>
                    <div class="h-3 w-3 rounded-full bg-emerald-500/70"></div>
                    <span class="ml-3 text-xs text-gray-500 font-mono">vibecodepc.local</span>
                </div>
                {{-- Terminal content --}}
                <div class="p-6 font-mono text-sm leading-relaxed">
                    <p class="text-gray-500">$ vibecodepc status</p>
                    <p class="mt-2 text-emerald-400">VibeCodePC v1.0.0 — Online</p>
                    <p class="text-gray-400 mt-1">Device ID &nbsp; <span class="text-gray-300">a1b2c3d4-e5f6-7890-abcd-ef1234567890</span></p>
                    <p class="text-gray-400">Owner &nbsp;&nbsp;&nbsp;&nbsp; <span class="text-gray-300">boyan</span></p>
                    <p class="text-gray-400">Subdomain &nbsp; <span class="text-emerald-400">boyan.vibecodepc.com</span></p>
                    <p class="mt-3 text-gray-400">AI Services:</p>
                    <p class="text-emerald-400">&nbsp; OpenAI &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; connected</p>
                    <p class="text-emerald-400">&nbsp; Anthropic &nbsp;&nbsp; connected</p>
                    <p class="text-emerald-400">&nbsp; Copilot &nbsp;&nbsp;&nbsp;&nbsp; connected</p>
                    <p class="text-gray-500">&nbsp; HuggingFace &nbsp;skipped</p>
                    <p class="mt-3 text-gray-400">Projects:</p>
                    <p class="text-gray-300">&nbsp; my-saas &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="text-emerald-400">running</span> &nbsp; port 8000 &nbsp; <span class="text-emerald-400">tunneled</span></p>
                    <p class="text-gray-300">&nbsp; portfolio &nbsp;&nbsp;&nbsp;<span class="text-emerald-400">running</span> &nbsp; port 3000 &nbsp; <span class="text-gray-500">local only</span></p>
                    <p class="text-gray-300">&nbsp; discord-bot &nbsp;<span class="text-yellow-400">stopped</span></p>
                    <p class="mt-3 text-gray-400">System: <span class="text-gray-300">CPU 12%</span> &nbsp; <span class="text-gray-300">RAM 1.8/8 GB</span> &nbsp; <span class="text-gray-300">Disk 24/256 GB</span> &nbsp; <span class="text-gray-300">Temp 48C</span></p>
                    <p class="mt-2 text-gray-500">$ <span class="animate-pulse">_</span></p>
                </div>
            </div>
        </div>
    </section>

    {{-- Final CTA --}}
    <section class="relative border-t border-white/5 py-24">
        <div class="absolute inset-0 bg-gradient-to-t from-emerald-500/5 to-transparent"></div>
        <div class="relative mx-auto max-w-2xl px-6 text-center">
            <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Ready to vibe?</h2>
            <p class="mt-4 text-gray-400 text-lg">Join the waitlist and be first to get your VibeCodePC when we launch.</p>
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
                <p class="text-sm text-gray-600">&copy; {{ date('Y') }} VibeCodePC. All rights reserved.</p>
            </div>
        </div>
    </footer>

    @livewireScripts
</body>
</html>
