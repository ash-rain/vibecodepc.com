<div
    x-data="{ polling: true }"
    x-init="setInterval(() => { if (polling) $wire.checkPairingStatus() }, 5000)"
    class="min-h-screen flex flex-col items-center justify-center px-4 py-12"
>
    <div class="mb-8 text-center">
        <h1 class="text-3xl font-bold text-amber-400 mb-2">VibeCodePC</h1>
        <p class="text-gray-400 text-sm">Personal AI Coding Workstation</p>
    </div>

    <div class="w-full max-w-lg space-y-6">
        {{-- QR Code & Pairing Info --}}
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-8 text-center">
            <h2 class="text-xl font-semibold mb-4">Scan to Pair</h2>

            @if ($pairingUrl)
                <div class="bg-white rounded-lg p-4 inline-block mb-4 [&>svg]:w-48 [&>svg]:h-48">
                    {!! $qrCodeSvg !!}
                </div>

                <p class="text-sm text-gray-400 mb-2">Or visit this URL:</p>
                <p class="font-mono text-amber-400 text-sm break-all">{{ $pairingUrl }}</p>
            @else
                <div class="text-red-400 py-8">
                    <p class="font-semibold">No device identity found</p>
                    <p class="text-sm text-gray-500 mt-1">Run: php artisan device:generate-id</p>
                </div>
            @endif
        </div>

        {{-- Device Info --}}
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
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
        @if (! $hasInternet)
            <livewire:pairing.network-setup />
        @endif

        {{-- Polling Status --}}
        <div class="text-center">
            <div class="inline-flex items-center gap-2 text-sm text-gray-500">
                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Waiting for pairing...
            </div>
        </div>
    </div>
</div>
