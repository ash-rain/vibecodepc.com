<div class="space-y-6">
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-8">
        <h2 class="text-xl font-semibold text-white mb-2">Connect GitHub</h2>
        <p class="text-gray-400 text-sm mb-6">Link your GitHub account for repository access and Copilot integration.</p>

        @if ($error)
            <div class="bg-red-500/10 border border-red-500/20 rounded-lg p-4 mb-6">
                <p class="text-red-400 text-sm">{{ $error }}</p>
            </div>
        @endif

        @if ($status === 'idle')
            <div class="text-center py-8">
                <svg class="w-16 h-16 mx-auto text-gray-600 mb-4" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                </svg>
                <button
                    wire:click="startDeviceFlow"
                    wire:loading.attr="disabled"
                    class="px-6 py-3 bg-white hover:bg-gray-100 text-gray-900 font-semibold rounded-lg transition-colors inline-flex items-center gap-2"
                >
                    <span wire:loading.remove>Connect GitHub Account</span>
                    <span wire:loading class="inline-flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Starting...
                    </span>
                </button>
            </div>

        @elseif ($status === 'polling')
            <div
                x-data="{ polling: true }"
                x-init="setInterval(() => { if (polling) $wire.checkAuthStatus() }, 5000)"
                class="text-center py-8"
            >
                <div class="bg-gray-800 rounded-lg p-6 inline-block mb-4">
                    <p class="text-sm text-gray-400 mb-2">Enter this code on GitHub:</p>
                    <p class="text-3xl font-mono font-bold text-amber-400 tracking-widest">{{ $userCode }}</p>
                </div>

                <p class="text-sm text-gray-400 mb-4">
                    Open <a href="{{ $verificationUri }}" target="_blank" class="text-amber-400 hover:underline">{{ $verificationUri }}</a> and enter the code above.
                </p>

                <div class="inline-flex items-center gap-2 text-sm text-gray-500">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Waiting for authorization...
                </div>
            </div>

        @elseif ($status === 'connected')
            <div class="bg-green-500/10 border border-green-500/20 rounded-lg p-6">
                <div class="flex items-center gap-3 mb-4">
                    <svg class="w-6 h-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-green-400 font-semibold">GitHub Connected</span>
                </div>

                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-400">Username</dt>
                        <dd class="text-white font-mono">{{ $githubUsername }}</dd>
                    </div>
                    @if ($githubName)
                        <div class="flex justify-between">
                            <dt class="text-gray-400">Name</dt>
                            <dd class="text-gray-200">{{ $githubName }}</dd>
                        </div>
                    @endif
                    @if ($githubEmail)
                        <div class="flex justify-between">
                            <dt class="text-gray-400">Email</dt>
                            <dd class="text-gray-200">{{ $githubEmail }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <dt class="text-gray-400">GitHub Copilot</dt>
                        <dd>
                            @if ($hasCopilot)
                                <span class="text-green-400">Available</span>
                            @else
                                <span class="text-gray-500">Not detected</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>
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
        @if ($status === 'connected')
            <button
                wire:click="complete"
                class="px-6 py-2.5 bg-amber-500 hover:bg-amber-600 text-gray-950 font-semibold rounded-lg transition-colors"
            >
                Continue
            </button>
        @endif
    </div>
</div>
