<div class="space-y-6">
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-8">
        <h2 class="text-xl font-semibold text-white mb-2">Welcome to VibeCodePC</h2>
        <p class="text-gray-400 text-sm mb-6">Let's get your workstation set up. First, confirm your account and configure basic settings.</p>

        {{-- Account Info --}}
        <div class="bg-gray-800/50 rounded-lg p-4 mb-6">
            <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-3">Cloud Account</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-400">Username</dt>
                    <dd class="text-amber-400 font-mono">{{ $cloudUsername ?: 'Not set' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-400">Email</dt>
                    <dd class="text-gray-200">{{ $cloudEmail ?: 'Not set' }}</dd>
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
    </div>
</div>
