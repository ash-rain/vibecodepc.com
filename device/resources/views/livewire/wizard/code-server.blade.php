<div class="space-y-6">
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-8">
        <h2 class="text-xl font-semibold text-white mb-2">VS Code Setup</h2>
        <p class="text-gray-400 text-sm mb-6">Configure code-server (VS Code in your browser) with your preferred theme and extensions.</p>

        {{-- Status --}}
        <div class="bg-gray-800/50 rounded-lg p-4 mb-6">
            <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-3">Status</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-400">Installed</dt>
                    <dd>
                        @if ($isInstalled)
                            <span class="text-green-400">Yes</span>
                        @else
                            <span class="text-red-400">Not found</span>
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-400">Running</dt>
                    <dd>
                        @if ($isRunning)
                            <span class="text-green-400">Active</span>
                        @else
                            <span class="text-gray-500">Inactive</span>
                        @endif
                    </dd>
                </div>
                @if ($version)
                    <div class="flex justify-between">
                        <dt class="text-gray-400">Version</dt>
                        <dd class="text-gray-200 font-mono">{{ $version }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        @if ($isInstalled)
            {{-- Extensions --}}
            <div class="mb-6">
                <h3 class="text-sm font-semibold text-gray-300 mb-3">Extensions</h3>
                <p class="text-gray-400 text-sm mb-3">Install recommended extensions: GitHub Copilot, Tailwind CSS IntelliSense, ESLint, Prettier.</p>
                <button
                    wire:click="installExtensions"
                    wire:loading.attr="disabled"
                    wire:target="installExtensions"
                    @class([
                        'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
                        'bg-gray-700 hover:bg-gray-600 text-white' => !$extensionsInstalled,
                        'bg-green-500/20 text-green-400 cursor-default' => $extensionsInstalled,
                    ])
                    @if($extensionsInstalled) disabled @endif
                >
                    <span wire:loading.remove wire:target="installExtensions">
                        {{ $extensionsInstalled ? 'Extensions Installed' : 'Install Extensions' }}
                    </span>
                    <span wire:loading wire:target="installExtensions" class="inline-flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Installing...
                    </span>
                </button>
            </div>

            {{-- Theme --}}
            <div class="mb-6">
                <h3 class="text-sm font-semibold text-gray-300 mb-3">Color Theme</h3>
                <div class="grid grid-cols-2 gap-2">
                    @foreach ($themes as $theme)
                        <button
                            wire:click="$set('selectedTheme', '{{ $theme['id'] }}')"
                            @class([
                                'px-4 py-3 rounded-lg text-sm text-left transition-all border',
                                'border-amber-500 bg-amber-500/10 text-amber-400' => $selectedTheme === $theme['id'],
                                'border-gray-700 bg-gray-800 text-gray-300 hover:border-gray-600' => $selectedTheme !== $theme['id'],
                            ])
                        >
                            {{ $theme['label'] }}
                        </button>
                    @endforeach
                </div>
                <button
                    wire:click="applyTheme"
                    class="mt-3 px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded-lg transition-colors"
                >
                    Apply Theme
                </button>
            </div>

            {{-- Preview --}}
            @if ($isRunning)
                <div class="mb-6">
                    <h3 class="text-sm font-semibold text-gray-300 mb-3">Preview</h3>
                    <div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden" style="height: 300px;">
                        <iframe src="{{ $codeServerUrl }}" class="w-full h-full" title="VS Code Preview"></iframe>
                    </div>
                </div>
            @endif
        @endif

        @if ($message)
            <p class="text-sm text-amber-400 mt-4">{{ $message }}</p>
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
