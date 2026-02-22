<div wire:poll.5s="poll" class="space-y-6">
    {{-- Header --}}
    <div>
        <h2 class="text-lg font-semibold text-white">Containers</h2>
        <p class="text-gray-400 text-sm mt-0.5">Monitor and manage Docker containers across all projects.</p>
    </div>

    {{-- Summary stats --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
            <div class="text-2xl font-bold text-green-400">{{ $totalRunning }}</div>
            <div class="text-xs text-gray-400 mt-1">Running</div>
        </div>
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
            <div class="text-2xl font-bold text-amber-400">{{ $totalStopped }}</div>
            <div class="text-xs text-gray-400 mt-1">Stopped</div>
        </div>
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
            <div class="text-2xl font-bold text-red-400">{{ $totalError }}</div>
            <div class="text-xs text-gray-400 mt-1">Error</div>
        </div>
    </div>

    {{-- Container list --}}
    @if (empty($containers))
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-12 text-center">
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
                    x-data="{ open: false, tab: 'logs' }"
                    class="bg-gray-900 rounded-xl border border-gray-800"
                >
                    {{-- Row header --}}
                    <div class="flex items-center gap-3 px-5 py-4 cursor-pointer" @click="open = !open; if (open && tab === 'logs' && !$wire.logs[{{ $container['id'] }}]) $wire.loadLogs({{ $container['id'] }})">
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
                                    class="px-2.5 py-1 bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 text-xs rounded-lg transition-colors"
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
                    <div x-show="open" x-cloak x-collapse class="border-t border-gray-800">
                        {{-- Tabs --}}
                        <div class="flex gap-4 px-5 pt-3">
                            <button
                                @click="tab = 'logs'; if (!$wire.logs[{{ $container['id'] }}]) $wire.loadLogs({{ $container['id'] }})"
                                :class="tab === 'logs' ? 'text-amber-400 border-amber-400' : 'text-gray-500 border-transparent hover:text-gray-300'"
                                class="text-xs font-medium pb-2 border-b-2 transition-colors"
                            >Logs</button>
                            <button
                                @click="tab = 'command'"
                                :class="tab === 'command' ? 'text-amber-400 border-amber-400' : 'text-gray-500 border-transparent hover:text-gray-300'"
                                class="text-xs font-medium pb-2 border-b-2 transition-colors"
                            >Command</button>
                        </div>

                        {{-- Logs tab --}}
                        <div x-show="tab === 'logs'" class="p-4">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs text-gray-500">Last 100 lines</span>
                                <button
                                    wire:click="loadLogs({{ $container['id'] }})"
                                    class="text-xs text-gray-400 hover:text-white transition-colors"
                                >Refresh</button>
                            </div>
                            <div class="bg-gray-950 rounded-lg p-3 max-h-64 overflow-y-auto font-mono text-xs text-gray-300 whitespace-pre-wrap">
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
                                    class="flex-1 bg-gray-950 border border-gray-700 rounded-lg px-3 py-1.5 text-sm text-white font-mono placeholder-gray-600 focus:outline-none focus:border-amber-500"
                                >
                                <button
                                    wire:click="runCommand({{ $container['id'] }})"
                                    wire:loading.attr="disabled"
                                    class="px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-gray-950 text-xs font-medium rounded-lg transition-colors"
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
