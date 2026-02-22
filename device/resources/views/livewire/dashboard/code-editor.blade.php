<div class="space-y-6">
    {{-- Status bar --}}
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-white">Code Editor</h2>
                <div class="flex items-center gap-3 mt-1">
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
            </div>
            <div class="flex items-center gap-2">
                @if ($isInstalled)
                    @if ($isRunning)
                        <button
                            wire:click="restart"
                            wire:loading.attr="disabled"
                            class="px-4 py-2 bg-gray-800 hover:bg-gray-700 disabled:opacity-50 text-white text-sm rounded-lg transition-colors"
                        >
                            <span wire:loading.remove wire:target="restart">Restart</span>
                            <span wire:loading wire:target="restart">Restarting...</span>
                        </button>
                        <a href="{{ $editorUrl }}" target="_blank" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-gray-950 font-medium text-sm rounded-lg transition-colors">
                            Open in New Tab
                        </a>
                    @else
                        <button
                            wire:click="start"
                            wire:loading.attr="disabled"
                            class="px-4 py-2 bg-amber-500 hover:bg-amber-600 disabled:opacity-50 text-gray-950 font-medium text-sm rounded-lg transition-colors"
                        >
                            <span wire:loading.remove wire:target="start">Start Editor</span>
                            <span wire:loading wire:target="start">Starting...</span>
                        </button>
                    @endif
                @endif
            </div>
        </div>
    </div>

    {{-- Error message --}}
    @if ($error)
        <div class="bg-red-500/10 border border-red-500/30 rounded-xl p-4">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-red-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                    <p class="text-red-400 text-sm font-medium">Failed to start code-server</p>
                    <p class="text-red-400/80 text-sm mt-1 whitespace-pre-line">{{ $error }}</p>
                </div>
            </div>
        </div>
    @endif

    {{-- Editor iframe or placeholder --}}
    @if ($isRunning)
        <div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden" style="height: calc(100vh - 16rem);">
            <iframe
                src="{{ $editorUrl }}"
                class="w-full h-full border-0"
                allow="clipboard-read; clipboard-write"
            ></iframe>
        </div>
    @elseif (! $isInstalled)
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-12 text-center">
            <svg class="w-12 h-12 text-yellow-500/50 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
            </svg>
            <h3 class="text-white font-medium mb-1">code-server is not installed</h3>
            <p class="text-gray-500 text-sm">Install code-server on this device to use the browser-based editor.</p>
            <code class="block mt-3 text-xs text-gray-400 bg-gray-800 rounded-lg px-4 py-2 inline-block">curl -fsSL https://code-server.dev/install.sh | sh</code>
        </div>
    @else
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-12 text-center">
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
    @endif
</div>
