<div class="space-y-6">
    <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-8">
        <div wire:loading.flex
            wire:target="checkAvailability, provisionTunnel"
            class="absolute inset-0 z-10 items-center justify-center rounded-xl bg-gray-900/80 backdrop-blur-sm"
        >
            <div class="flex flex-col items-center gap-3">
                <svg class="w-8 h-8 animate-spin text-emerald-400" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-sm text-gray-300">Setting up tunnel...</span>
            </div>
        </div>

        <h2 class="text-xl font-semibold text-white mb-2">Cloudflare Tunnel</h2>
        <p class="text-gray-400 text-sm mb-6">
            Set up a Cloudflare Tunnel to access your device from anywhere in the world.
            This enables remote access to your dashboard and projects.
            <span class="block mt-2 text-gray-500">You can skip this step and set up tunneling later from the dashboard.</span>
        </p>

        {{-- Status badges --}}
        <div class="flex items-center gap-3 mb-6">
            @if ($tunnelConfigured)
                <span class="text-xs bg-green-500/20 text-green-400 px-2.5 py-1 rounded-full">Tunnel Configured</span>
            @else
                <span class="text-xs bg-gray-500/20 text-gray-400 px-2.5 py-1 rounded-full">Not Configured</span>
            @endif

            @if ($tunnelRunning)
                <span class="text-xs bg-green-500/20 text-green-400 px-2.5 py-1 rounded-full">Running</span>
            @elseif ($tunnelConfigured)
                <span class="text-xs bg-red-500/20 text-red-400 px-2.5 py-1 rounded-full">Stopped</span>
            @endif
        </div>

        {{-- Error messages --}}
        @if ($error)
            <div class="bg-red-500/10 border border-red-500/30 rounded-lg p-4 mb-6">
                <p class="text-red-400 text-sm">{{ $error }}</p>
            </div>
        @endif

        @if ($isProvisioning)
            <div class="bg-amber-500/10 border border-amber-500/30 rounded-lg p-4 mb-6 flex items-center gap-3">
                <svg class="animate-spin h-5 w-5 text-amber-400 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="text-amber-400 text-sm">{{ $provisionStatus }}</p>
            </div>
        @endif

        {{-- Tunnel already configured - show success state --}}
        @if ($tunnelConfigured && $subdomain)
            <div class="bg-green-500/10 border border-green-500/30 rounded-lg p-6 mb-6">
                <div class="flex items-center gap-3 mb-3">
                    <svg class=" h-6 text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="1.w-65" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <h3 class="text-green-400 font-medium">Tunnel is set up!</h3>
                </div>
                <p class="text-gray-300 text-sm mb-3">Your device is accessible at:</p>
                <a href="https://{{ $subdomain }}.{{ config('vibecodepc.cloud_domain') }}" target="_blank"
                    class="text-emerald-400 font-mono text-sm hover:underline block">
                    https://{{ $subdomain }}.{{ config('vibecodepc.cloud_domain') }}
                </a>
            </div>
        @endif

        {{-- Setup form (only when not configured) --}}
        @if (!$tunnelConfigured && !$tunnelRunning)
            <div class="space-y-4">
                <div>
                    <label class="block text-sm text-gray-400 mb-2">Choose a subdomain</label>
                    <div class="flex items-center gap-2">
                        <div @class([
                            'flex-1 flex items-center bg-white/[0.04] border rounded-lg overflow-hidden focus-within:ring-1 transition-colors',
                            'border-white/[0.08] focus-within:border-emerald-500/50 focus-within:ring-emerald-500/30' => !$error || !str_contains($error, 'subdomain'),
                            'border-red-500/50 focus-within:border-red-500/50 focus-within:ring-red-500/30' => $error && str_contains($error, 'subdomain'),
                        ])>
                            <input type="text" wire:model="newSubdomain" placeholder="my-device"
                                wire:keydown.enter="{{ $subdomainAvailable ? 'provisionTunnel' : 'checkAvailability' }}"
                                class="flex-1 bg-transparent px-3 py-2.5 text-sm text-white placeholder-gray-600 focus:outline-none">
                            <span class="text-gray-500 pr-3 text-xs whitespace-nowrap">.{{ config('vibecodepc.cloud_domain') }}</span>
                        </div>

                        @if ($subdomainAvailable)
                            <button wire:click="provisionTunnel" wire:loading.attr="disabled"
                                wire:target="provisionTunnel, checkAvailability"
                                class="px-4 py-2.5 bg-emerald-500 hover:bg-emerald-400 disabled:opacity-50 text-gray-900 text-sm font-medium rounded-lg transition-colors whitespace-nowrap">
                                <span wire:loading.remove wire:target="provisionTunnel">Setup Tunnel</span>
                                <span wire:loading wire:target="provisionTunnel">Setting up...</span>
                            </button>
                        @else
                            <button wire:click="checkAvailability" wire:loading.attr="disabled"
                                wire:target="checkAvailability"
                                class="px-4 py-2.5 bg-white/[0.06] hover:bg-white/10 disabled:opacity-50 text-white text-sm rounded-lg transition-colors whitespace-nowrap">
                                <span wire:loading.remove wire:target="checkAvailability">Check</span>
                                <span wire:loading wire:target="checkAvailability">Checking...</span>
                            </button>
                        @endif
                    </div>

                    @if ($newSubdomain)
                        <a href="https://{{ $newSubdomain }}.{{ config('vibecodepc.cloud_domain') }}" target="_blank"
                            class="text-xs text-gray-300 font-mono hover:underline block mt-2">
                            https://{{ $newSubdomain }}.{{ config('vibecodepc.cloud_domain') }}
                        </a>
                    @endif

                    @if ($provisionStatus)
                        <p @class([
                            'text-xs mt-2',
                            'text-emerald-400' => $subdomainAvailable,
                            'text-amber-400' => !$subdomainAvailable,
                        ])>{{ $provisionStatus }}</p>
                    @endif
                </div>

                <p class="text-gray-500 text-xs">
                    Free for personal use. Your tunnel will be linked to your Cloudflare account.
                </p>
            </div>
        @endif
    </div>

    {{-- Actions --}}
    <div class="flex justify-between">
        <button
            wire:click="skip"
            class="px-6 py-2.5 text-gray-400 hover:text-white transition-colors"
        >
            Skip for now — use locally
        </button>
        <button
            wire:click="complete"
            class="px-6 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-gray-950 font-semibold rounded-xl transition-colors"
        >
            {{ $tunnelConfigured ? 'Continue' : 'Continue without tunnel' }}
        </button>
    </div>
</div>
