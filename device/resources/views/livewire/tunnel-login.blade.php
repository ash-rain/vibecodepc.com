<div class="flex items-center justify-center min-h-screen px-4">
    <div class="w-full max-w-md">
        <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-8">
            {{-- Header --}}
            <div class="text-center mb-8">
                <div
                    class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 mb-4">
                    <svg class="w-8 h-8 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                    </svg>
                </div>
                <h1 class="text-xl font-semibold text-white">Device Access</h1>
                <p class="text-gray-400 text-sm mt-2">Enter your device admin password to continue.</p>
            </div>

            {{-- Error --}}
            @if ($error)
                <div class="bg-red-500/10 border border-red-500/20 rounded-lg px-4 py-3 mb-6">
                    <p class="text-red-400 text-sm">{{ $error }}</p>
                </div>
            @endif

            {{-- Form --}}
            <form wire:submit="authenticate" class="space-y-6">
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-300 mb-1">
                        Admin Password
                    </label>
                    <input wire:model="password" type="password" id="password" autocomplete="current-password"
                        autofocus
                        class="w-full rounded-lg border border-white/10 bg-white/5 px-4 py-2.5 text-white placeholder-gray-500 focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/50 focus:outline-none transition-colors"
                        placeholder="Enter your password" />
                    @error('password')
                        <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                    class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-gray-950 font-semibold rounded-lg transition-colors"
                    wire:loading.attr="disabled" wire:loading.class="opacity-60 cursor-wait">
                    <svg wire:loading class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                            stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z">
                        </path>
                    </svg>
                    <span wire:loading.remove>Unlock</span>
                    <span wire:loading>Verifying…</span>
                </button>
            </form>

            <p class="text-center text-xs text-gray-500 mt-6">
                You're accessing this device through a secure tunnel. Authentication is required to protect your
                workstation.
            </p>
        </div>
    </div>
</div>
