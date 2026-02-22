<div class="space-y-6">
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-8">
        <h2 class="text-xl font-semibold text-white mb-2">Welcome to VibeCodePC</h2>
        <p class="text-gray-400 text-sm mb-6">Let's get your workstation set up. First, confirm your account and configure basic settings.</p>

        @if (!$isPaired)
            {{-- No Cloud Account — Pairing CTA --}}
            <div class="text-center py-8">
                <div class="bg-amber-500/10 border border-amber-500/20 rounded-xl p-8 mb-6">
                    <svg class="w-16 h-16 mx-auto text-amber-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m9.86-2.07a4.5 4.5 0 00-6.364-6.364L4.757 8.243a4.5 4.5 0 003.182 7.682" />
                    </svg>
                    <h3 class="text-lg font-semibold text-white mb-2">Connect Your Cloud Account</h3>
                    <p class="text-gray-400 text-sm mb-6 max-w-sm mx-auto">
                        Your device needs to be linked to a VibeCodePC cloud account before setup can continue. Scan the QR code or visit the pairing page to register and claim this device.
                    </p>
                    <a
                        href="{{ route('pairing') }}"
                        class="inline-flex items-center gap-3 px-8 py-4 bg-amber-500 hover:bg-amber-600 text-gray-950 font-semibold text-lg rounded-xl transition-colors"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6.75 6.75h.75v.75h-.75v-.75zM6.75 16.5h.75v.75h-.75v-.75zM16.5 6.75h.75v.75H16.5v-.75zM13.5 13.5h.75v.75h-.75v-.75zM13.5 19.5h.75v.75h-.75v-.75zM19.5 13.5h.75v.75h-.75v-.75zM19.5 19.5h.75v.75h-.75v-.75zM16.5 16.5h.75v.75H16.5v-.75z" />
                        </svg>
                        Pair This Device
                    </a>
                </div>
            </div>
        @else
            {{-- Account Info --}}
            <div class="bg-gray-800/50 rounded-lg p-4 mb-6">
                <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-3">Cloud Account</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-400">Username</dt>
                        <dd class="text-amber-400 font-mono">{{ $cloudUsername }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-400">Email</dt>
                        <dd class="text-gray-200">{{ $cloudEmail }}</dd>
                    </div>
                </dl>
            </div>

            <form wire:submit="complete" class="space-y-5">
                {{-- Admin Password --}}
                <div>
                    <label for="adminPassword" class="block text-sm font-medium text-gray-300 mb-1">
                        Device Admin Password
                    </label>
                    <input
                        wire:model="adminPassword"
                        type="password"
                        id="adminPassword"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-white placeholder-gray-500 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none"
                        placeholder="Minimum 8 characters"
                    >
                    @error('adminPassword')
                        <p class="text-red-400 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="adminPasswordConfirmation" class="block text-sm font-medium text-gray-300 mb-1">
                        Confirm Password
                    </label>
                    <input
                        wire:model="adminPasswordConfirmation"
                        type="password"
                        id="adminPasswordConfirmation"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-white placeholder-gray-500 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none"
                        placeholder="Re-enter your password"
                    >
                </div>

                {{-- Timezone --}}
                <div>
                    <label for="timezone" class="block text-sm font-medium text-gray-300 mb-1">
                        Timezone
                    </label>
                    <select
                        wire:model="timezone"
                        id="timezone"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-white focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none"
                    >
                        @foreach ($timezones as $tz)
                            <option value="{{ $tz }}">{{ $tz }}</option>
                        @endforeach
                    </select>
                    @error('timezone')
                        <p class="text-red-400 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Terms of Service --}}
                <div class="flex items-start gap-3">
                    <input
                        wire:model="acceptedTos"
                        type="checkbox"
                        id="acceptedTos"
                        class="mt-1 h-4 w-4 rounded border-gray-600 bg-gray-800 text-amber-500 focus:ring-amber-500"
                    >
                    <label for="acceptedTos" class="text-sm text-gray-400">
                        I agree to the <a href="https://vibecodepc.com/terms" target="_blank" class="text-amber-400 hover:underline">Terms of Service</a> and <a href="https://vibecodepc.com/privacy" target="_blank" class="text-amber-400 hover:underline">Privacy Policy</a>.
                    </label>
                </div>
                @error('acceptedTos')
                    <p class="text-red-400 text-sm mt-1">{{ $message }}</p>
                @enderror

                {{-- Submit --}}
                <button
                    type="submit"
                    class="w-full bg-amber-500 hover:bg-amber-600 text-gray-950 font-semibold py-3 rounded-lg transition-colors"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-50 cursor-wait"
                >
                    <span wire:loading.remove>Continue</span>
                    <span wire:loading class="inline-flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Saving...
                    </span>
                </button>
            </form>
        @endif
    </div>
</div>
