<div class="flex flex-col h-screen">
    {{-- Branded header bar --}}
    <header class="h-12 bg-gray-900 border-b border-gray-800 flex items-center justify-between px-4 shrink-0">
        <div class="flex items-center gap-3">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 text-white hover:text-amber-400 transition-colors">
                <div class="w-7 h-7 bg-amber-500 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-gray-950" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                    </svg>
                </div>
                <span class="font-bold text-sm">VibeCodePC</span>
            </a>
            <span class="text-gray-600">&middot;</span>
            <span class="text-sm font-medium text-gray-400">Code Editor</span>
            @if (! $isInstalled)
                <span class="text-xs bg-yellow-500/20 text-yellow-400 px-2 py-0.5 rounded-full">Not Installed</span>
            @elseif ($isRunning)
                <span class="text-xs bg-green-500/20 text-green-400 px-2 py-0.5 rounded-full">Running</span>
            @else
                <span class="text-xs bg-red-500/20 text-red-400 px-2 py-0.5 rounded-full">Stopped</span>
            @endif
            @if ($version)
                <span class="text-xs text-gray-500">v{{ $version }}</span>
            @endif
            @if ($hasCopilot)
                <span class="text-xs bg-blue-500/20 text-blue-400 px-2 py-0.5 rounded-full">Copilot Active</span>
            @endif
        </div>

        <div class="flex items-center gap-2">
            @if ($isInstalled)
                @if ($isRunning)
                    <button
                        wire:click="restart"
                        wire:confirm="This will restart code-server and reload the editor. Any unsaved changes may be lost."
                        wire:loading.attr="disabled"
                        class="px-3 py-1 bg-gray-800 hover:bg-gray-700 disabled:opacity-50 text-white text-xs rounded-lg transition-colors"
                    >
                        <span wire:loading.remove wire:target="restart">Restart</span>
                        <span wire:loading wire:target="restart">Restarting...</span>
                    </button>
                    <a
                        href="{{ $editorUrl }}"
                        target="_blank"
                        wire:loading.class="pointer-events-none opacity-50"
                        wire:target="restart"
                        class="px-3 py-1 bg-amber-500 hover:bg-amber-600 text-gray-950 font-medium text-xs rounded-lg transition-colors"
                    >
                        Open in New Tab
                    </a>
                @else
                    <button
                        wire:click="start"
                        wire:loading.attr="disabled"
                        class="px-3 py-1 bg-amber-500 hover:bg-amber-600 disabled:opacity-50 text-gray-950 font-medium text-xs rounded-lg transition-colors"
                    >
                        <span wire:loading.remove wire:target="start">Start Editor</span>
                        <span wire:loading wire:target="start">Starting...</span>
                    </button>
                @endif
            @endif
        </div>
    </header>

    {{-- Extensions Panel --}}
    @if ($isInstalled)
        <div x-data="{ open: false }" class="bg-gray-900 border-b border-gray-800 shrink-0">
            <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-2 text-left hover:bg-gray-800/50 transition-colors">
                <span class="text-xs font-medium text-gray-400">Extensions ({{ count($extensions) }})</span>
                <svg :class="open ? 'rotate-180' : ''" class="w-3.5 h-3.5 text-gray-500 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div x-show="open" x-collapse class="px-4 pb-3 space-y-3">
                @if ($extensionMessage)
                    <p class="text-xs text-amber-400">{{ $extensionMessage }}</p>
                @endif

                <div class="flex gap-2">
                    <input
                        wire:model="newExtensionId"
                        type="text"
                        placeholder="Extension ID (e.g. ms-python.python)"
                        class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 py-1.5 text-xs text-white placeholder-gray-500 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none"
                    >
                    <button
                        wire:click="installExtension"
                        wire:loading.attr="disabled"
                        wire:target="installExtension"
                        class="px-3 py-1.5 bg-amber-500 hover:bg-amber-600 disabled:opacity-50 text-gray-950 font-medium text-xs rounded-lg transition-colors"
                    >
                        <span wire:loading.remove wire:target="installExtension">Install</span>
                        <span wire:loading wire:target="installExtension">Installing...</span>
                    </button>
                </div>

                @if (count($extensions) > 0)
                    <div class="max-h-48 overflow-y-auto space-y-1">
                        @foreach ($extensions as $ext)
                            <div class="flex items-center justify-between py-1 px-2 rounded bg-gray-800/50 text-xs">
                                <div>
                                    <span class="text-white">{{ $ext['id'] }}</span>
                                    <span class="text-gray-500 ml-1">v{{ $ext['version'] }}</span>
                                </div>
                                <button
                                    wire:click="removeExtension('{{ $ext['id'] }}')"
                                    wire:loading.attr="disabled"
                                    class="text-red-400 hover:text-red-300 disabled:opacity-50 transition-colors"
                                >Remove</button>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-gray-500">No extensions installed.</p>
                @endif
            </div>
        </div>
    @endif

    {{-- Error message --}}
    @if ($error)
        <div class="bg-red-500/10 border-b border-red-500/30 px-4 py-3 shrink-0">
            <div class="flex items-center gap-3">
                <svg class="w-4 h-4 text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-red-400 text-sm">Failed to start code-server: {{ $error }}</p>
            </div>
        </div>
    @endif

    {{-- Editor iframe or placeholder --}}
    @if ($isRunning)
        <div class="relative flex-1">
            <iframe
                wire:key="editor-{{ $iframeKey }}"
                src="{{ $editorUrl }}"
                class="w-full h-full border-0"
                allow="clipboard-read; clipboard-write"
            ></iframe>

            {{-- Spinner overlay during restart/start --}}
            <div
                wire:loading.flex
                wire:target="restart, start"
                class="absolute inset-0 items-center justify-center bg-gray-950/80 z-10"
            >
                <div class="text-center">
                    <svg class="w-8 h-8 text-amber-500 animate-spin mx-auto mb-3" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="text-white text-sm font-medium">Restarting code-server...</p>
                </div>
            </div>
        </div>
    @elseif (! $isInstalled)
        <div class="flex-1 flex items-center justify-center">
            <div class="text-center">
                <svg class="w-12 h-12 text-yellow-500/50 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                </svg>
                <h3 class="text-white font-medium mb-1">code-server is not installed</h3>
                <p class="text-gray-500 text-sm">Install code-server on this device to use the browser-based editor.</p>
                <code class="block mt-3 text-xs text-gray-400 bg-gray-800 rounded-lg px-4 py-2 inline-block">curl -fsSL https://code-server.dev/install.sh | sh</code>
            </div>
        </div>
    @else
        <div class="flex-1 flex items-center justify-center">
            <div class="text-center">
                <svg class="w-12 h-12 text-gray-700 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                </svg>
                <h3 class="text-white font-medium mb-1">Code Editor is not running</h3>
                <p class="text-gray-500 text-sm mb-4">Start code-server to use the browser-based editor.</p>
                <button
                    wire:click="start"
                    wire:loading.attr="disabled"
                    class="px-4 py-2 bg-amber-500 hover:bg-amber-600 disabled:opacity-50 text-gray-950 font-medium text-sm rounded-lg transition-colors"
                >
                    <span wire:loading.remove wire:target="start">Start Editor</span>
                    <span wire:loading wire:target="start">Starting...</span>
                </button>
            </div>
        </div>
    @endif
</div>
