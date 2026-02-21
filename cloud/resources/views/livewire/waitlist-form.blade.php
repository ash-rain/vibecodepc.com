<div>
    @if ($submitted)
        <div class="flex items-center gap-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-6 py-4">
            <svg class="h-6 w-6 shrink-0 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-emerald-100 font-medium">You're on the list! We'll notify you when VibeCodePC launches.</p>
        </div>
    @else
        <form wire:submit="submit" class="flex flex-col sm:flex-row gap-3 w-full max-w-lg">
            <div class="flex-1">
                <input
                    wire:model="email"
                    type="email"
                    placeholder="you@email.com"
                    class="w-full rounded-xl border border-white/10 bg-white/5 px-5 py-3.5 text-white placeholder-gray-500 outline-none transition focus:border-emerald-500/50 focus:ring-2 focus:ring-emerald-500/20"
                />
                @error('email')
                    <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <button
                type="submit"
                wire:loading.attr="disabled"
                class="shrink-0 rounded-xl bg-emerald-500 px-8 py-3.5 font-semibold text-gray-950 transition hover:bg-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 disabled:opacity-50"
            >
                <span wire:loading.remove>Join Waitlist</span>
                <span wire:loading class="flex items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Joining...
                </span>
            </button>
        </form>
    @endif
</div>
