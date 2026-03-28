<div class="space-y-6">
    <div>
        <h2 class="text-lg font-semibold text-white">Environment Variables</h2>
        <p class="text-gray-400 text-sm mt-0.5">Manage environment variables and PATH modifications in your ~/.bashrc file.</p>
    </div>

    {{-- Read-Only Notice --}}
    @if ($isReadOnly)
    <div class="bg-amber-500/10 border border-amber-500/20 rounded-lg p-4">
        <div class="flex items-start gap-3">
            <div class="flex-shrink-0">
                <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
            </div>
            <div class="flex-1">
                <h3 class="text-sm font-medium text-amber-400">Read-Only Mode</h3>
                <p class="text-xs text-amber-400/80 mt-1">
                    {{ $readOnlyReason }}
                    <a href="{{ route('dashboard.tunnels') }}" class="underline hover:text-amber-300">Go to Tunnels</a> to configure remote access.
                </p>
            </div>
        </div>
    </div>
    @endif

    {{-- Unpaired Warning --}}
    @if (!$isPairingRequired && !$isPaired)
    <div class="bg-blue-500/10 border border-blue-500/20 rounded-lg p-4">
        <div class="flex items-start gap-3">
            <div class="flex-shrink-0">
                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div class="flex-1">
                <h3 class="text-sm font-medium text-blue-400">Running Without Pairing</h3>
                <p class="text-xs text-blue-400/80 mt-1">
                    This device is running without cloud pairing. Local editing is enabled, but some features require pairing.
                    <a href="{{ route('pairing') }}" class="underline hover:text-blue-300">Pair your device</a> for full functionality.
                </p>
            </div>
        </div>
    </div>
    @endif

    {{-- Status Message --}}
    @if ($statusMessage)
    <div @class([
        'rounded-lg p-4 text-sm border',
        'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' => $statusType === 'success',
        'bg-red-500/10 border-red-500/20 text-red-400' => $statusType === 'error',
    ])>
        {{ $statusMessage }}
    </div>
    @endif

    {{-- PATH Configuration --}}
    <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-6 space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-medium text-white">PATH Extra Directories</h3>
                <p class="text-xs text-gray-500 mt-1">Additional directories to prepend to PATH variable.</p>
            </div>
            @if ($isDirty)
            <span class="text-xs text-amber-400 bg-amber-500/10 px-2 py-1 rounded">Unsaved changes</span>
            @endif
        </div>

        <div class="space-y-2">
            <label class="block text-xs text-gray-400">Extra PATH (directories separated by colons)</label>
            <input
                type="text"
                wire:model.live="extraPath"
                @disabled($isReadOnly)
                placeholder="/usr/local/bin:/opt/bin"
                class="w-full bg-gray-900 border border-white/10 rounded-lg px-3 py-2 text-sm text-gray-300 focus:outline-none focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/20 disabled:opacity-50 disabled:cursor-not-allowed"
            />
            <p class="text-xs text-gray-600">
                These directories will be prepended to PATH in the format: <code class="bg-gray-800 px-1 rounded">export PATH="/your/paths:$PATH"</code>
            </p>
        </div>
    </div>

    {{-- Environment Variables --}}
    <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-6 space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-medium text-white">Environment Variables</h3>
                <p class="text-xs text-gray-500 mt-1">Variables will be added to ~/.bashrc with export statements.</p>
            </div>
            @if ($isDirty)
            <span class="text-xs text-amber-400 bg-amber-500/10 px-2 py-1 rounded">Unsaved changes</span>
            @endif
        </div>

        <div class="space-y-3">
            {{-- Header --}}
            <div class="grid grid-cols-12 gap-3 text-xs text-gray-500">
                <div class="col-span-4">Variable Name</div>
                <div class="col-span-7">Value</div>
                <div class="col-span-1"></div>
            </div>

            {{-- Environment Variable Rows --}}
            @foreach ($envVarList as $index => $var)
            <div class="grid grid-cols-12 gap-3 items-start" wire:key="env-var-{{ $index }}">
                <div class="col-span-4">
                    <input
                        type="text"
                        wire:model.live="envVarList.{{ $index }}.key"
                        @disabled($isReadOnly)
                        placeholder="VAR_NAME"
                        class="w-full bg-gray-900 border border-white/10 rounded-lg px-3 py-2 text-sm text-gray-300 focus:outline-none focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/20 disabled:opacity-50 disabled:cursor-not-allowed uppercase"
                        oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9_]/g, '')"
                    />
                </div>
                <div class="col-span-7">
                    <input
                        type="text"
                        wire:model.live="envVarList.{{ $index }}.value"
                        @disabled($isReadOnly)
                        placeholder="value"
                        class="w-full bg-gray-900 border border-white/10 rounded-lg px-3 py-2 text-sm text-gray-300 focus:outline-none focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/20 disabled:opacity-50 disabled:cursor-not-allowed"
                    />
                </div>
                <div class="col-span-1 flex justify-end">
                    @if (count($envVarList) > 1 || $index < count($envVarList) - 1)
                    <button
                        wire:click="removeEnvVar({{ $index }})"
                        @disabled($isReadOnly)
                        type="button"
                        class="text-gray-600 hover:text-red-400 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        title="Remove"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                    @endif
                </div>
            </div>
            @endforeach
        </div>

        {{-- Add Button --}}
        <button
            wire:click="addEnvVar"
            @disabled($isReadOnly)
            type="button"
            class="inline-flex items-center gap-2 px-3 py-2 bg-white/[0.06] hover:bg-white/10 disabled:opacity-50 disabled:cursor-not-allowed text-gray-400 hover:text-white text-sm rounded-lg transition-colors"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Variable
        </button>
    </div>

    {{-- Sensitive Keys Notice --}}
    <div class="bg-gray-800/50 border border-white/[0.06] rounded-lg p-4">
        <div class="flex items-start gap-3">
            <div class="flex-shrink-0">
                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
            </div>
            <div class="flex-1">
                <h3 class="text-sm font-medium text-gray-400">Security Note</h3>
                <p class="text-xs text-gray-500 mt-1">
                    Values for sensitive keys (containing API_KEY, TOKEN, SECRET, PASSWORD, AUTH) are automatically encrypted when stored.
                    Variable names must be UPPERCASE and contain only letters, numbers, and underscores.
                </p>
            </div>
        </div>
    </div>

    {{-- Actions --}}
    <div class="flex items-center justify-between pt-2">
        <button
            wire:click="resetToDefaults"
            wire:confirm="Reset all environment variables? This will remove the VibeCodePC section from ~/.bashrc."
            type="button"
            @disabled($isReadOnly)
            class="px-4 py-2 bg-red-500/10 hover:bg-red-500/20 disabled:opacity-50 disabled:cursor-not-allowed text-red-400 text-sm rounded-lg transition-colors"
        >
            Reset to Defaults
        </button>

        <button
            wire:click="save"
            wire:loading.attr="disabled"
            wire:target="save"
            @disabled(!$isDirty || $isReadOnly)
            class="px-5 py-2 bg-emerald-500 hover:bg-emerald-400 disabled:opacity-50 disabled:cursor-not-allowed text-gray-950 text-sm font-medium rounded-xl transition-colors"
        >
            <span wire:loading.remove wire:target="save">Save Changes</span>
            <span wire:loading wire:target="save">Saving...</span>
        </button>
    </div>

    {{-- File Info --}}
    <div class="text-xs text-gray-600 px-2">
        @php
            $home = $_SERVER['HOME'] ?? '/home/vibecodepc';
            $bashrcPath = $home . '/.bashrc';
        @endphp
        File: <code class="text-gray-500">{{ $bashrcPath }}</code>
        @if (file_exists($bashrcPath))
            @php
                $stat = stat($bashrcPath);
                if ($stat) {
                    echo ' - Last modified: ' . \Carbon\Carbon::createFromTimestamp($stat['mtime'])->diffForHumans();
                }
            @endphp
        @endif
    </div>
</div>
