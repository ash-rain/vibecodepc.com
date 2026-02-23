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
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-medium text-gray-400">Published Apps</h3>
                <button wire:click="openConfig"
                    class="px-2.5 py-1 bg-white/[0.04] hover:bg-white/[0.08] text-gray-400 hover:text-white text-xs rounded-lg transition-colors flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75 22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 16.5" />
                    </svg>
                    Routes Config
                </button>
            </div>

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
                    <div class="bg-white/[0.03] rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-white font-medium">{{ $project['name'] }}</div>
                            <div class="flex items-center gap-2">
                                @if ($project['tunnel_enabled'] && $editingProjectId !== $project['id'])
                                    <button wire:click="editProject({{ $project['id'] }})"
                                        class="p-1 text-gray-500 hover:text-white transition-colors rounded" title="Edit route">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                        </svg>
                                    </button>
                                @endif
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
                        </div>

                        @if ($editingProjectId === $project['id'])
                            {{-- Edit mode --}}
                            <div class="mt-3 space-y-2">
                                <div class="flex items-center gap-2">
                                    <div class="flex items-center bg-white/[0.04] border border-white/[0.08] rounded-lg overflow-hidden flex-1">
                                        <span class="text-gray-500 pl-3 text-xs">/</span>
                                        <input type="text" wire:model="editPath"
                                            class="flex-1 bg-transparent px-1.5 py-1.5 text-xs text-white font-mono placeholder-gray-600 focus:outline-none"
                                            placeholder="path">
                                    </div>
                                    <span class="text-gray-600 text-xs">&rarr;</span>
                                    <div class="flex items-center bg-white/[0.04] border border-white/[0.08] rounded-lg overflow-hidden w-36">
                                        <span class="text-gray-500 pl-3 text-xs">localhost:</span>
                                        <input type="number" wire:model="editPort" wire:keydown.enter="saveProject"
                                            class="w-16 bg-transparent px-1.5 py-1.5 text-xs text-white font-mono placeholder-gray-600 focus:outline-none"
                                            placeholder="port">
                                    </div>
                                </div>
                                @error('editPath') <p class="text-red-400 text-xs">{{ $message }}</p> @enderror
                                @error('editPort') <p class="text-red-400 text-xs">{{ $message }}</p> @enderror
                                <div class="flex items-center gap-2">
                                    <button wire:click="saveProject"
                                        class="px-3 py-1 bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-400 text-xs rounded-lg transition-colors">
                                        Save
                                    </button>
                                    <button wire:click="cancelEdit"
                                        class="px-3 py-1 bg-white/[0.04] hover:bg-white/[0.08] text-gray-400 text-xs rounded-lg transition-colors">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        @elseif ($project['tunnel_enabled'])
                            <div class="text-xs font-mono mt-1.5 space-y-0.5">
                                <div class="text-gray-500">/{{ $project['tunnel_subdomain_path'] ?? $project['slug'] }} &rarr; localhost:{{ $project['port'] }}</div>
                                <a href="https://{{ $project['tunnel_subdomain_path'] ?? $project['slug'] }}--{{ $subdomain }}.{{ config('vibecodepc.cloud_domain') }}" target="_blank"
                                    class="text-emerald-400/70 hover:underline block truncate">{{ $project['tunnel_subdomain_path'] ?? $project['slug'] }}--{{ $subdomain }}.{{ config('vibecodepc.cloud_domain') }}</a>
                            </div>
                        @else
                            <div class="text-xs text-gray-600 mt-1.5">Not published</div>
                        @endif
                    </div>
                @endforeach

                @if (count($projects) === 0)
                    <p class="text-gray-600 text-xs px-4 py-2">No projects yet. Create a project to publish it here.</p>
                @endif
            </div>
        </div>

        {{-- Cloudflared Config Modal --}}
        @if ($showConfig)
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data x-init="$el.querySelector('pre').scrollTop = 0">
                <div class="absolute inset-0 bg-black/60" wire:click="closeConfig"></div>
                <div class="relative bg-gray-900 border border-white/[0.08] rounded-2xl w-full max-w-lg max-h-[80vh] flex flex-col">
                    <div class="flex items-center justify-between p-5 border-b border-white/[0.06]">
                        <h3 class="text-sm font-medium text-white">Cloudflared Ingress Routes</h3>
                        <button wire:click="closeConfig" class="text-gray-500 hover:text-white transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="p-5 overflow-y-auto">
                        <pre class="text-xs text-gray-300 font-mono bg-black/30 rounded-lg p-4 overflow-x-auto whitespace-pre">{{ $ingressConfig }}</pre>
                    </div>
                    <div class="p-5 border-t border-white/[0.06] flex justify-end">
                        <button wire:click="closeConfig"
                            class="px-4 py-1.5 bg-white/[0.06] hover:bg-white/10 text-white text-xs rounded-lg transition-colors">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        @endif

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
