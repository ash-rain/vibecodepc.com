<div class="space-y-6" x-data="{ tab: 'network' }">
    <div>
        <h2 class="text-lg font-semibold text-white">System Settings</h2>
        <p class="text-gray-400 text-sm mt-0.5">Manage your VibeCodePC device configuration.</p>
    </div>

    @if ($statusMessage)
        <div class="bg-emerald-500/10 border border-emerald-500/20 rounded-lg p-4 text-emerald-400 text-sm">
            {{ $statusMessage }}
        </div>
    @endif

    {{-- Tabs --}}
    <div class="border-b border-white/[0.06] flex gap-1 overflow-x-auto">
        @foreach (['network' => 'Network', 'storage' => 'Storage', 'updates' => 'Updates', 'ssh' => 'SSH', 'backup' => 'Backup', 'power' => 'Power'] as $key => $label)
            <button
                @click="tab = '{{ $key }}'"
                :class="tab === '{{ $key }}' ? 'text-emerald-400 border-emerald-400' : 'text-gray-500 border-transparent hover:text-gray-300'"
                class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap"
            >{{ $label }}</button>
        @endforeach
    </div>

    {{-- Network Tab --}}
    <div x-show="tab === 'network'" class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-6 space-y-4">
        <h3 class="text-sm font-medium text-gray-400">Network Configuration</h3>
        <div class="space-y-3">
            <div class="flex justify-between text-sm">
                <span class="text-gray-400">Local IP</span>
                <span class="text-white font-mono">{{ $localIp ?? 'Unknown' }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-400">Ethernet</span>
                <span @class(['text-green-400' => $hasEthernet, 'text-gray-500' => !$hasEthernet])>
                    {{ $hasEthernet ? 'Connected' : 'Not connected' }}
                </span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-400">Wi-Fi</span>
                <span @class(['text-green-400' => $hasWifi, 'text-gray-500' => !$hasWifi])>
                    {{ $hasWifi ? 'Available' : 'Not available' }}
                </span>
            </div>
        </div>
    </div>

    {{-- Storage Tab --}}
    <div x-show="tab === 'storage'" class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-6 space-y-4">
        <h3 class="text-sm font-medium text-gray-400">Disk Usage</h3>
        @php $diskPercent = $diskTotalGb > 0 ? ($diskUsedGb / $diskTotalGb) * 100 : 0; @endphp
        <div>
            <div class="flex justify-between text-sm mb-2">
                <span class="text-gray-400">{{ $diskUsedGb }} GB used of {{ $diskTotalGb }} GB</span>
                <span class="text-gray-400">{{ number_format($diskPercent, 1) }}%</span>
            </div>
            <div class="w-full h-2 bg-gray-800 rounded-full overflow-hidden">
                <div
                    @class([
                        'h-full rounded-full transition-all',
                        'bg-green-500' => $diskPercent < 70,
                        'bg-amber-500' => $diskPercent >= 70 && $diskPercent < 90,
                        'bg-red-500' => $diskPercent >= 90,
                    ])
                    style="width: {{ min(100, $diskPercent) }}%"
                ></div>
            </div>
        </div>
    </div>

    {{-- Updates Tab --}}
    <div x-show="tab === 'updates'" class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-6 space-y-4">
        <h3 class="text-sm font-medium text-gray-400">System Updates</h3>
        <p class="text-sm text-gray-500">Check for available system and package updates.</p>
        <button
            wire:click="checkForUpdates"
            wire:loading.attr="disabled"
            class="px-4 py-2 bg-white/[0.06] hover:bg-white/10 text-white text-sm rounded-lg transition-colors"
        >
            <span wire:loading.remove wire:target="checkForUpdates">Check for Updates</span>
            <span wire:loading wire:target="checkForUpdates">Checking...</span>
        </button>
    </div>

    {{-- SSH Tab --}}
    <div x-show="tab === 'ssh'" class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-6 space-y-4">
        <h3 class="text-sm font-medium text-gray-400">SSH Access</h3>
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-white">SSH Server</p>
                <p class="text-xs text-gray-500 mt-0.5">Allow remote terminal access to this device.</p>
            </div>
            <button
                wire:click="toggleSsh"
                @class([
                    'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200',
                    'bg-emerald-500' => $sshEnabled,
                    'bg-gray-700' => !$sshEnabled,
                ])
            >
                <span @class([
                    'pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow ring-0 transition-transform duration-200',
                    'translate-x-5' => $sshEnabled,
                    'translate-x-0' => !$sshEnabled,
                ])></span>
            </button>
        </div>
        @if ($sshEnabled && $localIp)
            <div class="bg-white/[0.03] rounded-lg p-3 text-sm font-mono text-gray-400">
                ssh vibecodepc@{{ $localIp }}
            </div>
        @endif
    </div>

    {{-- Backup Tab --}}
    <div x-show="tab === 'backup'" class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-6 space-y-6">
        <h3 class="text-sm font-medium text-gray-400">Backup & Restore</h3>

        <div>
            <p class="text-sm text-gray-500 mb-3">Download an encrypted backup of your device configuration (AI keys, tunnel settings, credentials).</p>
            <button
                wire:click="createBackup"
                wire:loading.attr="disabled"
                class="px-4 py-2 bg-emerald-500 hover:bg-emerald-400 disabled:opacity-50 text-gray-950 text-sm font-medium rounded-xl transition-colors"
            >
                <span wire:loading.remove wire:target="createBackup">Download Backup</span>
                <span wire:loading wire:target="createBackup">Creating backup...</span>
            </button>
        </div>

        <div class="pt-4 border-t border-white/[0.06]">
            <p class="text-sm text-gray-500 mb-3">Restore a previously downloaded backup file. This will overwrite current settings.</p>
            <div class="space-y-3">
                <input
                    wire:model="backupFile"
                    type="file"
                    accept=".zip"
                    class="block w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-white/[0.06] file:text-white hover:file:bg-white/10"
                >
                @error('backupFile')
                    <p class="text-xs text-red-400">{{ $message }}</p>
                @enderror
                <button
                    wire:click="restoreBackup"
                    wire:confirm="This will overwrite your current configuration with the backup. Are you sure?"
                    wire:loading.attr="disabled"
                    @disabled(! $backupFile)
                    class="px-4 py-2 bg-white/[0.06] hover:bg-white/10 disabled:opacity-50 text-white text-sm rounded-lg transition-colors"
                >
                    <span wire:loading.remove wire:target="restoreBackup">Restore from Backup</span>
                    <span wire:loading wire:target="restoreBackup">Restoring...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Power Tab --}}
    <div x-show="tab === 'power'" class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-6 space-y-4">
        <h3 class="text-sm font-medium text-gray-400">Power Management</h3>
        <div class="flex flex-wrap gap-3">
            <button
                wire:click="restartDevice"
                wire:confirm="Are you sure you want to restart the device?"
                class="px-4 py-2 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 text-sm rounded-lg transition-colors"
            >Restart Device</button>
            <button
                wire:click="shutdownDevice"
                wire:confirm="Are you sure you want to shut down the device?"
                class="px-4 py-2 bg-red-500/10 hover:bg-red-500/20 text-red-400 text-sm rounded-lg transition-colors"
            >Shutdown</button>
        </div>

        <div class="pt-4 mt-4 border-t border-white/[0.06]">
            <h4 class="text-sm font-medium text-red-400 mb-2">Factory Reset</h4>
            <p class="text-xs text-gray-500 mb-3">This will erase all settings and return the device to its initial state. This cannot be undone.</p>
            <button
                wire:click="factoryReset"
                wire:confirm="FACTORY RESET: This will erase ALL data, projects, and settings. Are you absolutely sure?"
                class="px-4 py-2 bg-red-500/10 hover:bg-red-500/20 text-red-400 text-sm rounded-lg transition-colors"
            >Factory Reset</button>
        </div>
    </div>
</div>
