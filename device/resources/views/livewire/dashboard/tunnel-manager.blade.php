<div class="space-y-6">
    {{-- Header --}}
    <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <h2 class="text-lg font-semibold text-white">Tunnel</h2>
                @if (!$tunnelConfigured)
                    <span class="text-xs bg-gray-500/20 text-gray-400 px-2.5 py-0.5 rounded-full">Not Configured</span>
                @elseif ($tunnelRunning)
                    <span class="text-xs bg-green-500/20 text-green-400 px-2.5 py-0.5 rounded-full">Running</span>
                @else
                    <span class="text-xs bg-red-500/20 text-red-400 px-2.5 py-0.5 rounded-full">Stopped</span>
                @endif
            </div>
            @if ($tunnelConfigured)
                <div class="flex items-center gap-2">
                    <button wire:click="reprovisionTunnel" wire:loading.attr="disabled"
                        wire:target="reprovisionTunnel, restartTunnel"
                        class="px-3 py-1.5 bg-amber-500/20 hover:bg-amber-500/30 disabled:opacity-50 text-amber-400 text-xs rounded-lg transition-colors">
                        <span wire:loading.remove wire:target="reprovisionTunnel">Re-provision</span>
                        <span wire:loading wire:target="reprovisionTunnel">Re-provisioning...</span>
                    </button>
                    <button wire:click="restartTunnel" wire:loading.attr="disabled"
                        wire:target="reprovisionTunnel, restartTunnel"
                        class="px-3 py-1.5 bg-white/[0.06] hover:bg-white/10 disabled:opacity-50 text-white text-xs rounded-lg transition-colors">
                        <span wire:loading.remove wire:target="restartTunnel">Restart</span>
                        <span wire:loading wire:target="restartTunnel">Restarting...</span>
                    </button>
                </div>
            @endif
        </div>

        @if ($error)
            <div class="bg-red-500/10 border border-red-500/30 rounded-lg p-3 mt-4">
                <p class="text-red-400 text-sm">{{ $error }}</p>
            </div>
        @endif

        @if ($isProvisioning)
            <div class="bg-amber-500/10 border border-amber-500/30 rounded-lg p-3 mt-4 flex items-center gap-2">
                <svg class="animate-spin h-4 w-4 text-amber-400 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="text-amber-400 text-sm">{{ $provisionStatus }}</p>
            </div>
        @endif

        {{-- Setup form (only when not configured) --}}
        @if (!$subdomain)
            <div class="mt-4 space-y-3">
                <p class="text-gray-400 text-sm">Set up a subdomain to expose your device to the internet.</p>

                <div class="flex items-center gap-2">
                    <div @class([
                        'flex-1 flex items-center bg-white/[0.04] border rounded-lg overflow-hidden focus-within:ring-1 transition-colors',
                        'border-white/[0.08] focus-within:border-emerald-500/50 focus-within:ring-emerald-500/30' => !$errors->has('newSubdomain'),
                        'border-red-500/50 focus-within:border-red-500/50 focus-within:ring-red-500/30' => $errors->has('newSubdomain'),
                    ])>
                        <input type="text" wire:model="newSubdomain" placeholder="my-device"
                            wire:keydown.enter="{{ $subdomainAvailable ? 'provisionTunnel' : 'checkAvailability' }}"
                            class="flex-1 bg-transparent px-3 py-2 text-sm text-white placeholder-gray-600 focus:outline-none">
                        <span class="text-gray-500 pr-3 text-xs whitespace-nowrap">.{{ config('vibecodepc.cloud_domain') }}</span>
                    </div>

                    @if ($subdomainAvailable)
                        <button wire:click="provisionTunnel" wire:loading.attr="disabled"
                            wire:target="provisionTunnel, checkAvailability"
                            class="px-4 py-2 bg-emerald-500/20 hover:bg-emerald-500/30 disabled:opacity-50 text-emerald-400 text-sm font-medium rounded-lg transition-colors whitespace-nowrap">
                            <span wire:loading.remove wire:target="provisionTunnel">Setup Tunnel</span>
                            <span wire:loading wire:target="provisionTunnel">Setting up...</span>
                        </button>
                    @else
                        <button wire:click="checkAvailability" wire:loading.attr="disabled"
                            wire:target="checkAvailability"
                            class="px-4 py-2 bg-white/[0.06] hover:bg-white/10 disabled:opacity-50 text-white text-sm rounded-lg transition-colors whitespace-nowrap">
                            <span wire:loading.remove wire:target="checkAvailability">Check</span>
                            <span wire:loading wire:target="checkAvailability">Checking...</span>
                        </button>
                    @endif
                </div>

                @if ($newSubdomain)
                    <a href="https://{{ $newSubdomain }}.{{ config('vibecodepc.cloud_domain') }}" target="_blank"
                        class="text-xs text-gray-300 font-mono hover:underline block">https://{{ $newSubdomain }}.{{ config('vibecodepc.cloud_domain') }}</a>
                @endif

                @error('newSubdomain')
                    <p class="text-red-400 text-xs">{{ $message }}</p>
                @enderror

                @if ($provisionStatus)
                    <p @class([
                        'text-xs',
                        'text-emerald-400' => $subdomainAvailable,
                        'text-amber-400' => !$subdomainAvailable,
                    ])>{{ $provisionStatus }}</p>
                @endif
            </div>
        @endif
    </div>

    {{-- Published Apps --}}
    @if ($subdomain)
        <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-6">
            <h3 class="text-sm font-medium text-gray-400 mb-4">Published Apps</h3>

            <div class="space-y-2">
                {{-- Device Dashboard (always first, always on) --}}
                <div class="flex items-center justify-between bg-white/[0.03] rounded-lg p-4">
                    <div class="min-w-0">
                        <div class="text-sm text-white font-medium">Dashboard</div>
                        <div class="text-xs font-mono mt-0.5 space-y-0.5">
                            <div class="text-gray-500">/ &rarr; localhost:{{ config('vibecodepc.tunnel.device_app_port') }}</div>
                            <a href="https://{{ $subdomain }}.{{ config('vibecodepc.cloud_domain') }}" target="_blank"
                                class="text-emerald-400 hover:underline block truncate">{{ $subdomain }}.{{ config('vibecodepc.cloud_domain') }}</a>
                        </div>
                    </div>
                    <span class="text-xs text-emerald-400/60 px-2 py-0.5 bg-emerald-500/10 rounded-full whitespace-nowrap">Always on</span>
                </div>

                {{-- Projects --}}
                @foreach ($projects as $project)
                    <div class="flex items-center justify-between bg-white/[0.03] rounded-lg p-4">
                        <div class="min-w-0">
                            <div class="text-sm text-white font-medium">{{ $project['name'] }}</div>
                            @if ($project['tunnel_enabled'])
                                <div class="text-xs font-mono mt-0.5 space-y-0.5">
                                    <div class="text-gray-500">/{{ $project['slug'] }} &rarr; localhost:{{ $project['port'] }}</div>
                                    <a href="https://{{ $project['slug'] }}--{{ $subdomain }}.{{ config('vibecodepc.cloud_domain') }}" target="_blank"
                                        class="text-emerald-400/70 hover:underline block truncate">{{ $project['slug'] }}--{{ $subdomain }}.{{ config('vibecodepc.cloud_domain') }}</a>
                                </div>
                            @else
                                <div class="text-xs text-gray-600 mt-0.5">Not published</div>
                            @endif
                        </div>
                        <button wire:click="toggleProjectTunnel({{ $project['id'] }})" @class([
                            'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200',
                            'bg-emerald-500' => $project['tunnel_enabled'],
                            'bg-gray-700' => !$project['tunnel_enabled'],
                        ])>
                            <span @class([
                                'pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow ring-0 transition-transform duration-200',
                                'translate-x-5' => $project['tunnel_enabled'],
                                'translate-x-0' => !$project['tunnel_enabled'],
                            ])></span>
                        </button>
                    </div>
                @endforeach

                @if (count($projects) === 0)
                    <p class="text-gray-600 text-xs px-4 py-2">No projects yet. Create a project to publish it here.</p>
                @endif
            </div>
        </div>

        {{-- Traffic Stats --}}
        @if (count($trafficStats) > 0)
            <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-6">
                <h3 class="text-sm font-medium text-gray-400 mb-4">Traffic (24h)</h3>
                <div class="space-y-2">
                    @foreach ($trafficStats as $stat)
                        <div class="flex items-center justify-between bg-white/[0.03] rounded-lg px-4 py-3">
                            <span class="text-sm text-white">{{ $stat['project'] }}</span>
                            <div class="flex items-center gap-4 text-xs text-gray-400">
                                <span>{{ number_format($stat['requests']) }} req</span>
                                <span>{{ $stat['avg_response_time_ms'] }}ms avg</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</div>
