<div class="space-y-6">
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-8">
        <h2 class="text-xl font-semibold text-white mb-2">AI Services</h2>
        <p class="text-gray-400 text-sm mb-6">Connect your AI providers. You can configure multiple or skip this step and set them up later.</p>

        <div class="space-y-4">
            @foreach ($providers as $provider)
                <div x-data="{ open: false }" class="bg-gray-800/50 rounded-lg border border-gray-700">
                    {{-- Provider Header --}}
                    <button
                        @click="open = !open"
                        class="w-full flex items-center justify-between p-4 text-left"
                    >
                        <div class="flex items-center gap-3">
                            <span class="text-white font-medium">{{ $provider['name'] }}</span>
                            <span class="text-gray-500 text-sm">{{ $provider['desc'] }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($statuses[$provider['key']] === 'valid')
                                <span class="text-xs bg-green-500/20 text-green-400 px-2 py-0.5 rounded-full">Connected</span>
                            @elseif ($statuses[$provider['key']] === 'saved')
                                <span class="text-xs bg-amber-500/20 text-amber-400 px-2 py-0.5 rounded-full">Saved</span>
                            @elseif ($statuses[$provider['key']] === 'error')
                                <span class="text-xs bg-red-500/20 text-red-400 px-2 py-0.5 rounded-full">Error</span>
                            @endif
                            <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 text-gray-500 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </button>

                    {{-- Provider Body --}}
                    <div x-show="open" x-collapse class="px-4 pb-4 space-y-3">
                        @if ($provider['key'] === 'custom')
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs text-gray-400 mb-1">Display Name</label>
                                    <input
                                        wire:model="customName"
                                        type="text"
                                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none"
                                        placeholder="My Provider"
                                    >
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-400 mb-1">Base URL</label>
                                    <input
                                        wire:model="customBaseUrl"
                                        type="url"
                                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none"
                                        placeholder="https://api.example.com"
                                    >
                                </div>
                            </div>
                        @endif

                        <div class="flex gap-2">
                            <input
                                wire:model="apiKeys.{{ $provider['key'] }}"
                                type="password"
                                class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none"
                                placeholder="Enter API key..."
                            >
                            <button
                                wire:click="testConnection('{{ $provider['key'] }}')"
                                wire:loading.attr="disabled"
                                wire:target="testConnection('{{ $provider['key'] }}')"
                                class="px-3 py-2 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded-lg transition-colors whitespace-nowrap"
                            >
                                <span wire:loading.remove wire:target="testConnection('{{ $provider['key'] }}')">Test</span>
                                <span wire:loading wire:target="testConnection('{{ $provider['key'] }}')">
                                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </span>
                            </button>
                            <button
                                wire:click="saveProvider('{{ $provider['key'] }}')"
                                class="px-3 py-2 bg-amber-500 hover:bg-amber-600 text-gray-950 text-sm font-medium rounded-lg transition-colors"
                            >
                                Save
                            </button>
                            @if ($statuses[$provider['key']] !== 'none')
                                <button
                                    wire:click="removeProvider('{{ $provider['key'] }}')"
                                    class="px-3 py-2 bg-red-500/10 hover:bg-red-500/20 text-red-400 text-sm rounded-lg transition-colors"
                                >
                                    Remove
                                </button>
                            @endif
                        </div>

                        @if ($messages[$provider['key']])
                            <p @class([
                                'text-sm',
                                'text-green-400' => $statuses[$provider['key']] === 'valid',
                                'text-amber-400' => $statuses[$provider['key']] === 'saved',
                                'text-red-400' => $statuses[$provider['key']] === 'error',
                            ])>
                                {{ $messages[$provider['key']] }}
                            </p>
                        @endif

                        @if ($provider['url'])
                            <p class="text-xs text-gray-500">
                                Get your API key: <a href="{{ $provider['url'] }}" target="_blank" class="text-amber-400 hover:underline">{{ $provider['url'] }}</a>
                            </p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
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
