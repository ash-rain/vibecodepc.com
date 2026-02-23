<div x-data="{}" x-init="setInterval(() => $wire.checkPairingStatus(), 5000)"
    class="flex flex-col items-center justify-center min-h-screen px-4 py-12">
    <div class="mb-8 text-center">
        <h1 class="mb-2 text-3xl font-bold text-emerald-400">VibeCodePC</h1>
        <p class="text-sm text-gray-400">Personal AI Coding Workstation</p>
    </div>

    <div class="w-full max-w-lg space-y-6">
        {{-- QR Code & Pairing Info --}}
        <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-8 text-center">
            <h2 class="mb-4 text-xl font-semibold">Scan to Pair</h2>

            @if ($pairingUrl)
                <div
                    class="bg-white rounded-lg p-4 inline-block mb-4 w-56 h-56 overflow-hidden [&_svg]:w-full [&_svg]:h-full">
                    <img src="{!! $qrCodeSvg !!}" alt="QR Code" class="object-contain w-full h-full" />
                </div>

                <p class="mb-2 text-sm text-gray-400">Or visit this URL:</p>
                <a href="{{ $pairingUrl }}"
                    class="font-mono text-sm underline break-all text-emerald-400 hover:text-emerald-300">{{ $pairingUrl }}</a>
            @else
                <div class="py-8 text-red-400">
                    <p class="font-semibold">No device identity found</p>
                    <p class="mt-1 text-sm text-gray-500">Run: php artisan device:generate-id</p>
                </div>
            @endif
        </div>

        {{-- Device Info --}}
        <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-6">
            <h3 class="mb-3 text-sm font-semibold tracking-wider text-gray-400 uppercase">Device Info</h3>
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
    </div>
</div>
