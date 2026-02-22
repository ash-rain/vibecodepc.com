<div wire:poll.5s="poll" class="space-y-6">
    {{-- Header --}}
    <div>
        <h2 class="text-lg font-semibold text-white">Containers</h2>
        <p class="text-gray-400 text-sm mt-0.5">Monitor and manage Docker containers across all projects.</p>
    </div>

    {{-- Summary stats --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-4">
            <div class="text-2xl font-bold text-green-400">{{ $totalRunning }}</div>
            <div class="text-xs text-gray-400 mt-1">Running</div>
        </div>
        <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-4">
            <div class="text-2xl font-bold text-amber-400">{{ $totalStopped }}</div>
            <div class="text-xs text-gray-400 mt-1">Stopped</div>
        </div>
        <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-4">
            <div class="text-2xl font-bold text-red-400">{{ $totalError }}</div>
            <div class="text-xs text-gray-400 mt-1">Error</div>
        </div>
    </div>

    {{-- Container list --}}
    @if (empty($containers))
        <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-12 text-center">
            <svg class="w-12 h-12 text-gray-700 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
            <h3 class="text-white font-medium mb-1">No containers</h3>
            <p class="text-gray-500 text-sm">Create a project to see its container here.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($containers as $container)
                <div
                    x-data="{
                        open: false,
                        tab: 'logs',
                        toggle() {
                            this.open = !this.open;
                            if (this.open && this.tab === 'logs') {
                                $wire.subscribeToLogs({{ $container['id'] }});
                            } else if (!this.open) {
                                $wire.unsubscribeFromLogs({{ $container['id'] }});
                            }
                        },
                        switchTab(newTab) {
                            this.tab = newTab;
                            if (newTab === 'logs') {
                                $wire.subscribeToLogs({{ $container['id'] }});
                            } else {
                                $wire.unsubscribeFromLogs({{ $container['id'] }});
                            }
                        },
                    }"
                    class="bg-white/[0.02] rounded-2xl border border-white/[0.06]"
                >
                    {{-- Error banner --}}
                    @if (! empty($actionErrors[$container['id']]))
                        <div class="flex items-start gap-3 px-5 py-3 bg-red-500/5 border-b border-red-500/10">
                            <svg class="w-4 h-4 text-red-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-medium text-red-400">Action failed</p>
                                <pre class="mt-1 text-xs text-red-300/80 whitespace-pre-wrap font-mono break-words">{{ $actionErrors[$container['id']] }}</pre>
                            </div>
                            <button wire:click="dismissError({{ $container['id'] }})" class="text-red-400/60 hover:text-red-400 shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    @endif

                    {{-- Row header --}}
                    <div class="flex items-center gap-3 px-5 py-4 cursor-pointer" @click="toggle()">
                        {{-- Status dot --}}
                        <span @class([
                            'w-2.5 h-2.5 rounded-full shrink-0',
                            'bg-green-400' => $container['status_color'] === 'green',
                            'bg-amber-400' => $container['status_color'] === 'amber',
                            'bg-gray-400' => $container['status_color'] === 'gray',
                            'bg-red-400' => $container['status_color'] === 'red',
                        ])></span>

                        {{-- Name & framework --}}
                        <div class="min-w-0 flex-1">
                            <span class="text-white font-medium">{{ $container['name'] }}</span>
                            <span class="text-gray-500 text-xs ml-2">{{ $container['framework_label'] }}</span>
                        </div>

                        {{-- Resource usage --}}
                        <div class="hidden sm:flex items-center gap-4 text-xs text-gray-400">
                            <span>CPU: {{ $container['cpu'] }}</span>
                            <span>MEM: {{ $container['memory'] }}</span>
                        </div>

                        {{-- Port --}}
                        @if ($container['port'])
                            <span class="hidden sm:inline text-xs text-gray-500">:{{ $container['port'] }}</span>
                        @endif

                        {{-- Status pill --}}
                        <span @class([
                            'text-xs px-2 py-0.5 rounded-full',
                            'bg-green-500/20 text-green-400' => $container['status_color'] === 'green',
                            'bg-amber-500/20 text-amber-400' => $container['status_color'] === 'amber',
                            'bg-gray-500/20 text-gray-400' => $container['status_color'] === 'gray',
                            'bg-red-500/20 text-red-400' => $container['status_color'] === 'red',
                        ])>{{ $container['status'] }}</span>

                        {{-- Action buttons --}}
                        <div class="flex items-center gap-1.5" @click.stop>
                            @if ($container['status'] === 'Running')
                                <button
                                    wire:click="stopProject({{ $container['id'] }})"
                                    wire:loading.attr="disabled"
                                    class="px-2.5 py-1 bg-red-500/10 hover:bg-red-500/20 text-red-400 text-xs rounded-lg transition-colors"
                                >Stop</button>
                                <button
                                    wire:click="restartProject({{ $container['id'] }})"
                                    wire:loading.attr="disabled"
                                    class="px-2.5 py-1 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 text-xs rounded-lg transition-colors"
                                >Restart</button>
                            @else
                                <button
                                    wire:click="startProject({{ $container['id'] }})"
                                    wire:loading.attr="disabled"
                                    class="px-2.5 py-1 bg-green-500/10 hover:bg-green-500/20 text-green-400 text-xs rounded-lg transition-colors"
                                >Start</button>
                            @endif
                        </div>

                        {{-- Expand chevron --}}
                        <svg
                            :class="open && 'rotate-180'"
                            class="w-4 h-4 text-gray-500 transition-transform shrink-0"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>

                    {{-- Expanded section --}}
                    <div x-show="open" x-cloak x-collapse class="border-t border-white/[0.06]">
                        {{-- Tabs --}}
                        <div class="flex gap-4 px-5 pt-3">
                            <button
                                @click="switchTab('logs')"
                                :class="tab === 'logs' ? 'text-emerald-400 border-emerald-400' : 'text-gray-500 border-transparent hover:text-gray-300'"
                                class="text-xs font-medium pb-2 border-b-2 transition-colors"
                            >Logs</button>
                            <button
                                @click="switchTab('command')"
                                :class="tab === 'command' ? 'text-emerald-400 border-emerald-400' : 'text-gray-500 border-transparent hover:text-gray-300'"
                                class="text-xs font-medium pb-2 border-b-2 transition-colors"
                            >Command</button>
                        </div>

                        {{-- Logs tab --}}
                        <div x-show="tab === 'logs'" class="p-4">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-gray-500">Last 100 lines</span>
                                    @if (! empty($openLogPanels[$container['id']]))
                                        <span class="inline-flex items-center gap-1 text-xs text-emerald-400/60">
                                            <span class="relative flex h-1.5 w-1.5">
                                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                                                <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            </span>
                                            Live
                                        </span>
                                    @endif
                                </div>
                                <button
                                    wire:click="loadLogs({{ $container['id'] }})"
                                    class="text-xs text-gray-400 hover:text-white transition-colors"
                                >Refresh</button>
                            </div>
                            <div
                                x-ref="logScroller{{ $container['id'] }}"
                                x-effect="if (tab === 'logs') $nextTick(() => { const el = $refs.logScroller{{ $container['id'] }}; if (el) el.scrollTop = el.scrollHeight; })"
                                class="bg-gray-950 rounded-lg p-3 max-h-64 overflow-y-auto font-mono text-xs text-gray-300 whitespace-pre-wrap"
                            >
                                @if (! empty($logs[$container['id']]))
                                    @foreach ($logs[$container['id']] as $line)
                                        <div>{{ $line }}</div>
                                    @endforeach
                                @else
                                    <span class="text-gray-600">No logs available.</span>
                                @endif
                            </div>
                        </div>

                        {{-- Command tab --}}
                        <div x-show="tab === 'command'" class="p-4">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="text-gray-500 text-sm font-mono">$</span>
                                <input
                                    type="text"
                                    wire:model="commandInputs.{{ $container['id'] }}"
                                    wire:keydown.enter="runCommand({{ $container['id'] }})"
                                    placeholder="Enter command..."
                                    class="flex-1 bg-gray-950 border border-white/10 rounded-lg px-3 py-1.5 text-sm text-white font-mono placeholder-gray-600 focus:outline-none focus:border-emerald-500/50"
                                >
                                <button
                                    wire:click="runCommand({{ $container['id'] }})"
                                    wire:loading.attr="disabled"
                                    class="px-3 py-1.5 bg-emerald-500 hover:bg-emerald-400 text-gray-950 text-xs font-medium rounded-xl transition-colors"
                                >Run</button>
                            </div>
                            @if (! empty($commandOutputs[$container['id']]))
                                <div class="bg-gray-950 rounded-lg p-3 max-h-48 overflow-y-auto font-mono text-xs text-gray-300 whitespace-pre-wrap">{{ $commandOutputs[$container['id']] }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
