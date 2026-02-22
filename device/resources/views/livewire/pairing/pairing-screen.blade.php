<div x-data="{ polling: true }" x-init="setInterval(() => { if (polling) $wire.checkPairingStatus() }, 5000)"
    class="min-h-screen flex flex-col items-center justify-center px-4 py-12">
    <div class="mb-8 text-center">
        <h1 class="text-3xl font-bold text-emerald-400 mb-2">VibeCodePC</h1>
        <p class="text-gray-400 text-sm">Personal AI Coding Workstation</p>
    </div>

    <div class="w-full max-w-lg space-y-6">
        @if ($isPaired)
            {{-- Tunnel Provisioning Transition --}}
            <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-8 text-center"
                @if ($tunnelStatus === 'provisioning') wire:init="setupTunnel" @endif>
                <div class="mb-6">
                    @if ($tunnelStatus === 'ready')
                        <div
                            class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-emerald-500/20 mb-4">
                            <svg class="w-8 h-8 text-emerald-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                    @elseif ($tunnelStatus === 'failed')
                        <div
                            class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-amber-500/20 mb-4">
                            <svg class="w-8 h-8 text-amber-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                            </svg>
                        </div>
                    @else
                        <svg class="w-12 h-12 mx-auto text-emerald-400 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                    @endif
                </div>

                <h2 class="text-xl font-semibold text-white mb-2">
                    @if ($tunnelStatus === 'ready')
                        Device Connected!
                    @elseif ($tunnelStatus === 'failed')
                        Continuing Setup
                    @else
                        Connecting Your Device
                    @endif
                </h2>
                <p class="text-gray-400 text-sm mb-4">{{ $tunnelMessage }}</p>

                @if ($tunnelUrl)
                    <p class="font-mono text-emerald-400 text-sm">{{ $tunnelUrl }}</p>
                @endif

                {{-- Step indicators --}}
                <div class="flex items-center justify-center gap-3 mt-6 text-xs text-gray-500">
                    <div
                        class="flex items-center gap-1.5 {{ in_array($tunnelStatus, ['provisioning', 'starting', 'ready']) ? 'text-emerald-400' : '' }}">
                        @if (in_array($tunnelStatus, ['starting', 'ready']))
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z"
                                    clip-rule="evenodd" />
                            </svg>
                        @else
                            <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        @endif
                        Provisioning
                    </div>
                    <span class="text-gray-700">&rarr;</span>
                    <div
                        class="flex items-center gap-1.5 {{ in_array($tunnelStatus, ['starting', 'ready']) ? 'text-emerald-400' : '' }}">
                        @if ($tunnelStatus === 'ready')
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z"
                                    clip-rule="evenodd" />
                            </svg>
                        @elseif ($tunnelStatus === 'starting')
                            <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        @else
                            <div class="w-3.5 h-3.5 rounded-full border border-current"></div>
                        @endif
                        Connecting
                    </div>
                    <span class="text-gray-700">&rarr;</span>
                    <div class="flex items-center gap-1.5 {{ $tunnelStatus === 'ready' ? 'text-emerald-400' : '' }}">
                        @if ($tunnelStatus === 'ready')
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z"
                                    clip-rule="evenodd" />
                            </svg>
                        @else
                            <div class="w-3.5 h-3.5 rounded-full border border-current"></div>
                        @endif
                        Ready
                    </div>
                </div>
            </div>
        @else
            {{-- QR Code & Pairing Info --}}
            <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-8 text-center">
                <h2 class="text-xl font-semibold mb-4">Scan to Pair</h2>

                @if ($pairingUrl)
                    <div
                        class="bg-white rounded-lg p-4 inline-block mb-4 w-56 h-56 overflow-hidden [&_svg]:w-full [&_svg]:h-full">
                        <img src="{!! $qrCodeSvg !!}" alt="QR Code" class="w-full h-full object-contain" />
                    </div>

                    <p class="text-sm text-gray-400 mb-2">Or visit this URL:</p>
                    <a href="{{ $pairingUrl }}" target="_blank"
                        class="font-mono text-emerald-400 text-sm break-all underline hover:text-emerald-300">{{ $pairingUrl }}</a>
                @else
                    <div class="text-red-400 py-8">
                        <p class="font-semibold">No device identity found</p>
                        <p class="text-sm text-gray-500 mt-1">Run: php artisan device:generate-id</p>
                    </div>
                @endif
            </div>

            {{-- Device Info --}}
            <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-6">
                <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-3">Device Info</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-400">Device ID</dt>
                        <dd class="font-mono text-gray-200">{{ Str::limit($deviceId, 16) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-400">Local IP</dt>
                        <dd class="font-mono text-gray-200">{{ $localIp }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-400">Internet</dt>
                        <dd>
                            @if ($hasInternet)
                                <span class="text-green-400">Connected</span>
                            @else
                                <span class="text-red-400">No connection</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- Network Setup (if no internet) --}}
            @if (!$hasInternet)
                <livewire:pairing.network-setup />
            @endif

            {{-- Polling Status --}}
            <div class="text-center">
                <div class="inline-flex items-center gap-2 text-sm text-gray-500">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                            stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                    Waiting for pairing...
                </div>
            </div>
        @endif
    </div>
</div>
