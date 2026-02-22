<div class="space-y-6">
    {{-- Tunnel Status --}}
    <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-lg font-semibold text-white">Cloudflare Tunnel</h2>
                <p class="text-gray-400 text-sm mt-0.5">Manage internet access to your projects.</p>
            </div>
            <div class="flex items-center gap-3">
                @if (! $tunnelConfigured)
                    <span class="text-xs bg-gray-500/20 text-gray-400 px-2 py-0.5 rounded-full">Not Configured</span>
                @elseif ($tunnelRunning)
                    <span class="text-xs bg-green-500/20 text-green-400 px-2 py-0.5 rounded-full">Running</span>
                @else
                    <span class="text-xs bg-red-500/20 text-red-400 px-2 py-0.5 rounded-full">Stopped</span>
                @endif
                @if ($tunnelConfigured)
                    <button
                        wire:click="restartTunnel"
                        wire:loading.attr="disabled"
                        class="px-3 py-1.5 bg-white/[0.06] hover:bg-white/10 disabled:opacity-50 text-white text-xs rounded-lg transition-colors"
                    >
                        <span wire:loading.remove wire:target="restartTunnel">Restart</span>
                        <span wire:loading wire:target="restartTunnel">Restarting...</span>
                    </button>
                @endif
            </div>
        </div>

        @if ($error)
            <div class="bg-red-500/10 border border-red-500/30 rounded-lg p-3 mt-4">
                <p class="text-red-400 text-sm">{{ $error }}</p>
            </div>
        @endif

        @if ($subdomain)
            <div class="bg-white/[0.03] rounded-lg p-3 text-sm">
                <span class="text-gray-500">Device URL:</span>
                <span class="text-emerald-400 font-mono ml-1">https://{{ $subdomain }}.vibecodepc.com</span>
            </div>
        @else
            <p class="text-gray-500 text-sm">No tunnel configured. Complete the setup wizard to configure tunnel access.</p>
        @endif
    </div>

    {{-- Per-Project Routing --}}
    <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-6">
        <h3 class="text-sm font-medium text-gray-400 mb-4">Project Routing</h3>

        @if (count($projects) === 0)
            <p class="text-gray-500 text-sm">No projects yet. Create a project to configure tunnel routing.</p>
        @else
            <div class="space-y-3">
                @foreach ($projects as $project)
                    <div class="flex items-center justify-between bg-white/[0.03] rounded-lg p-4">
                        <div>
                            <div class="text-sm text-white font-medium">{{ $project['name'] }}</div>
                            @if ($project['tunnel_enabled'] && $subdomain)
                                <div class="text-xs text-gray-500 font-mono mt-0.5 space-y-0.5">
                                    <div>/{{ $project['slug'] }} &rarr; localhost:{{ $project['port'] }}</div>
                                    <div class="text-emerald-400/70">{{ $project['slug'] }}--{{ $subdomain }}.vibecodepc.com</div>
                                </div>
                            @endif
                        </div>
                        <button
                            wire:click="toggleProjectTunnel({{ $project['id'] }})"
                            @class([
                                'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200',
                                'bg-emerald-500' => $project['tunnel_enabled'],
                                'bg-gray-700' => !$project['tunnel_enabled'],
                            ])
                        >
                            <span @class([
                                'pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow ring-0 transition-transform duration-200',
                                'translate-x-5' => $project['tunnel_enabled'],
                                'translate-x-0' => !$project['tunnel_enabled'],
                            ])></span>
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Traffic Stats --}}
    @if (count($trafficStats) > 0)
        <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-6">
            <h3 class="text-sm font-medium text-gray-400 mb-4">Traffic Stats (24h)</h3>
            <div class="space-y-2">
                @foreach ($trafficStats as $stat)
                    <div class="flex items-center justify-between bg-white/[0.03] rounded-lg px-4 py-3">
                        <span class="text-sm text-white">{{ $stat['project'] }}</span>
                        <div class="flex items-center gap-4 text-xs text-gray-400">
                            <span>{{ number_format($stat['requests']) }} requests</span>
                            <span>{{ $stat['avg_response_time_ms'] }} ms avg</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
