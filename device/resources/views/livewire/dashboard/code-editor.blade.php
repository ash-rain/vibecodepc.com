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
                        wire:loading.attr="disabled"
                        class="px-3 py-1 bg-gray-800 hover:bg-gray-700 disabled:opacity-50 text-white text-xs rounded-lg transition-colors"
                    >
                        <span wire:loading.remove wire:target="restart">Restart</span>
                        <span wire:loading wire:target="restart">Restarting...</span>
                    </button>
                    <a href="{{ $editorUrl }}" target="_blank" class="px-3 py-1 bg-amber-500 hover:bg-amber-600 text-gray-950 font-medium text-xs rounded-lg transition-colors">
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
        <iframe
            src="{{ $editorUrl }}"
            class="flex-1 w-full border-0"
            allow="clipboard-read; clipboard-write"
        ></iframe>
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
