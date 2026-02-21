<div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
    <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-3">Network Setup</h3>

    @if ($hasEthernet)
        <p class="text-sm text-gray-400 mb-4">
            Ethernet is available. Connect a cable for the easiest setup.
        </p>
    @endif

    @if ($hasWifi)
        <form wire:submit="connect" class="space-y-4">
            <div>
                <label for="ssid" class="block text-sm font-medium text-gray-300 mb-1">WiFi Network</label>
                <input
                    type="text"
                    id="ssid"
                    wire:model="ssid"
                    placeholder="Network name (SSID)"
                    class="w-full rounded-lg bg-gray-800 border-gray-700 text-white px-4 py-2 text-sm focus:border-amber-500 focus:ring-amber-500"
                >
                @error('ssid')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-300 mb-1">Password</label>
                <input
                    type="password"
                    id="password"
                    wire:model="password"
                    placeholder="WiFi password"
                    class="w-full rounded-lg bg-gray-800 border-gray-700 text-white px-4 py-2 text-sm focus:border-amber-500 focus:ring-amber-500"
                >
                @error('password')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <button
                type="submit"
                wire:loading.attr="disabled"
                class="w-full rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-amber-400 transition-colors disabled:opacity-50"
            >
                <span wire:loading.remove>Connect</span>
                <span wire:loading>Connecting...</span>
            </button>
        </form>

        @if ($error)
            <div class="mt-3 rounded-lg bg-red-900/50 border border-red-700 px-4 py-2 text-sm text-red-200">
                {{ $error }}
            </div>
        @endif

        @if ($success)
            <div class="mt-3 rounded-lg bg-green-900/50 border border-green-700 px-4 py-2 text-sm text-green-200">
                {{ $success }}
            </div>
        @endif
    @else
        <p class="text-sm text-gray-400">
            No WiFi adapter detected. Please connect via Ethernet.
        </p>
    @endif
</div>
