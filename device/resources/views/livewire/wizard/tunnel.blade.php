<div class="space-y-6">
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-8">
        <h2 class="text-xl font-semibold text-white mb-2">Tunnel Setup</h2>
        <p class="text-gray-400 text-sm mb-6">Configure your public subdomain for accessing your VibeCodePC from anywhere.</p>

        {{-- Subdomain Input --}}
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-300 mb-2">Choose your subdomain</label>
            <div class="flex items-center gap-2">
                <div class="flex-1 flex items-center bg-gray-800 border border-gray-700 rounded-lg overflow-hidden focus-within:border-amber-500 focus-within:ring-1 focus-within:ring-amber-500">
                    <input
                        wire:model="subdomain"
                        type="text"
                        class="flex-1 bg-transparent px-4 py-2.5 text-white placeholder-gray-500 focus:outline-none"
                        placeholder="your-username"
                        @if($tunnelActive) disabled @endif
                    >
                    <span class="text-gray-500 pr-4 text-sm">.vibecodepc.com</span>
                </div>
                @if(!$tunnelActive)
                    <button
                        wire:click="checkAvailability"
                        wire:loading.attr="disabled"
                        wire:target="checkAvailability"
                        class="px-4 py-2.5 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded-lg transition-colors whitespace-nowrap"
                    >
                        <span wire:loading.remove wire:target="checkAvailability">Check</span>
                        <span wire:loading wire:target="checkAvailability">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </span>
                    </button>
                @endif
            </div>
            @error('subdomain')
                <p class="text-red-400 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Status Messages --}}
        @if ($message)
            <div @class([
                'rounded-lg p-4 mb-6 text-sm',
                'bg-green-500/10 border border-green-500/20 text-green-400' => $subdomainAvailable || $connectivityVerified || $status === 'active',
                'bg-red-500/10 border border-red-500/20 text-red-400' => !$subdomainAvailable && $status === 'error',
                'bg-amber-500/10 border border-amber-500/20 text-amber-400' => in_array($status, ['provisioning', 'configuring']),
                'bg-gray-500/10 border border-gray-500/20 text-gray-400' => !$subdomainAvailable && !in_array($status, ['error', 'active', 'provisioning', 'configuring']),
            ])>
                @if (in_array($status, ['provisioning', 'configuring']))
                    <span class="inline-flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ $message }}
                    </span>
                @else
                    {{ $message }}
                @endif
            </div>
        @endif

        {{-- Setup Button --}}
        @if ($subdomainAvailable && !$tunnelActive)
            <button
                wire:click="setupTunnel"
                wire:loading.attr="disabled"
                wire:target="setupTunnel"
                class="w-full py-3 bg-amber-500 hover:bg-amber-600 text-gray-950 font-semibold rounded-lg transition-colors mb-6"
            >
                <span wire:loading.remove wire:target="setupTunnel">Setup Tunnel</span>
                <span wire:loading.inline-flex wire:target="setupTunnel" class="items-center gap-2">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Provisioning...
                </span>
            </button>
        @endif

        {{-- Test Connectivity --}}
        @if ($tunnelActive)
            <div class="bg-green-500/10 border border-green-500/20 rounded-lg p-4 mb-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-green-400 font-medium">Tunnel Active</span>
                    </div>
                    <a href="https://{{ $subdomain }}.vibecodepc.com" target="_blank" class="text-amber-400 text-sm hover:underline">
                        {{ $subdomain }}.vibecodepc.com
                    </a>
                </div>
            </div>

            @if (!$connectivityVerified)
                <button
                    wire:click="testConnectivity"
                    wire:loading.attr="disabled"
                    wire:target="testConnectivity"
                    class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded-lg transition-colors mb-6"
                >
                    <span wire:loading.remove wire:target="testConnectivity">Test Connectivity</span>
                    <span wire:loading.inline-flex wire:target="testConnectivity" class="items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Testing...
                    </span>
                </button>
            @endif
        @endif
    </div>

    {{-- Actions --}}
    <div class="flex justify-between">
        <button
            wire:click="skip"
            class="px-6 py-2.5 text-gray-400 hover:text-white transition-colors"
        >
            Skip for now
        </button>
        <button
            wire:click="complete"
            class="px-6 py-2.5 bg-amber-500 hover:bg-amber-600 text-gray-950 font-semibold rounded-lg transition-colors"
        >
            Continue
        </button>
    </div>
</div>
