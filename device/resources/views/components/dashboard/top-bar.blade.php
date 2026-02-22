@props(['title' => 'Dashboard'])

<header class="sticky top-0 z-20 bg-gray-950/80 backdrop-blur-xl border-b border-white/[0.06]">
    <div class="flex items-center justify-between h-16 px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-3">
            {{-- Mobile hamburger --}}
            <button
                @click="sidebarOpen = !sidebarOpen"
                class="lg:hidden p-2 -ml-2 text-gray-400 hover:text-white rounded-lg"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <h1 class="text-lg font-semibold text-white">{{ $title }}</h1>
        </div>

        {{-- Health bar --}}
        <livewire:dashboard.health-bar />
    </div>
</header>
