<div class="space-y-6">
    {{-- Status bar --}}
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-white">Code Editor</h2>
                <div class="flex items-center gap-3 mt-1">
                    @if ($isRunning)
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
                <button
                    wire:click="restart"
                    wire:loading.attr="disabled"
                    class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-white text-sm rounded-lg transition-colors"
                >Restart</button>
                @if ($isRunning)
                    <a href="{{ $editorUrl }}" target="_blank" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-gray-950 font-medium text-sm rounded-lg transition-colors">
                        Open in New Tab
                    </a>
                @endif
            </div>
        </div>
    </div>

    {{-- Editor iframe --}}
    @if ($isRunning)
        <div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden" style="height: calc(100vh - 16rem);">
            <iframe
                src="{{ $editorUrl }}"
                class="w-full h-full border-0"
                allow="clipboard-read; clipboard-write"
            ></iframe>
        </div>
    @else
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-12 text-center">
            <svg class="w-12 h-12 text-gray-700 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
            </svg>
            <h3 class="text-white font-medium mb-1">Code Editor is not running</h3>
            <p class="text-gray-500 text-sm mb-4">Click restart to start the code-server service.</p>
            <button
                wire:click="restart"
                class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-gray-950 font-medium text-sm rounded-lg transition-colors"
            >Start Editor</button>
        </div>
    @endif
</div>
