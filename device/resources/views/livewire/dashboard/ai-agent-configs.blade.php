<div class="space-y-6" x-data="{ tab: @entangle('activeTab') }">
    <div>
        <h2 class="text-lg font-semibold text-white">AI Agent Configs</h2>
        <p class="text-gray-400 text-sm mt-0.5">Edit configuration files for AI agents: Boost, OpenCode, Claude Code, and Copilot.</p>
    </div>

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

    {{-- Tabs --}}
    <div class="border-b border-white/[0.06] flex gap-1 overflow-x-auto">
        @foreach ($configFiles as $key => $config)
            <button
                @click="tab = '{{ $key }}'"
                :class="tab === '{{ $key }}' ? 'text-emerald-400 border-emerald-400' : 'text-gray-500 border-transparent hover:text-gray-300'"
                class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap"
            >
                {{ $config['label'] }}
                @if ($isDirty[$key] ?? false)
                    <span class="ml-1.5 w-2 h-2 bg-amber-500 rounded-full inline-block"></span>
                @endif
            </button>
        @endforeach
    </div>

    {{-- Config File Editors --}}
    @foreach ($configFiles as $key => $config)
        <div x-show="tab === '{{ $key }}'" x-cloak class="space-y-4">
            <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-6 space-y-4">
                {{-- Header --}}
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-sm font-medium text-gray-400">{{ $config['label'] }}</h3>
                        <p class="text-xs text-gray-500 mt-1">{{ $config['description'] }}</p>
                        <div class="mt-2 text-xs font-mono text-gray-600">{{ $config['path'] }}</div>
                    </div>
                    <div class="flex items-center gap-2">
                        @if (!($fileExists[$key] ?? false))
                            <span class="text-xs text-amber-400 bg-amber-500/10 px-2 py-1 rounded">File does not exist</span>
                        @endif
                        @if ($isDirty[$key] ?? false)
                            <span class="text-xs text-amber-400 bg-amber-500/10 px-2 py-1 rounded">Unsaved changes</span>
                        @endif
                        @if (!($isValid[$key] ?? true))
                            <span class="text-xs text-red-400 bg-red-500/10 px-2 py-1 rounded">Invalid JSON</span>
                        @endif
                    </div>
                </div>

                {{-- Validation Error --}}
                @if (!($isValid[$key] ?? true) && ($validationErrors[$key] ?? ''))
                    <div class="bg-red-500/10 border border-red-500/20 rounded-lg p-3 text-red-400 text-xs">
                        {{ $validationErrors[$key] }}
                    </div>
                @endif

                {{-- Editor --}}
                <div class="relative">
                    <textarea
                        wire:model.live="fileContent.{{ $key }}"
                        rows="24"
                        @class([
                            'w-full bg-gray-900 border rounded-xl px-4 py-3 text-sm text-white font-mono placeholder-gray-500 focus:outline-none resize-y',
                            'border-red-500/50 focus:border-red-500/70 focus:ring-1 focus:ring-red-500/20' => !($isValid[$key] ?? true),
                            'border-white/10 focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/20' => ($isValid[$key] ?? true),
                        ])
                        placeholder="{{ $key === 'copilot_instructions' ? '# GitHub Copilot Instructions...' : '{\n  // Your configuration here...\n}' }}"
                    ></textarea>
                </div>

                {{-- Actions Bar --}}
                <div class="flex items-center justify-between pt-2">
                    <div class="flex items-center gap-2">
                        {{-- Format JSON button (only for JSON files) --}}
                        @if ($key !== 'copilot_instructions')
                            <button
                                wire:click="formatJson('{{ $key }}')"
                                type="button"
                                class="px-3 py-1.5 bg-white/[0.06] hover:bg-white/10 text-gray-400 hover:text-white text-xs rounded-lg transition-colors"
                            >
                                Format JSON
                            </button>
                        @endif

                        {{-- Backup dropdown --}}
                        @if (!empty($backups[$key] ?? []))
                            <div class="relative" x-data="{ open: false }">
                                <button
                                    @click="open = !open"
                                    type="button"
                                    class="px-3 py-1.5 bg-white/[0.06] hover:bg-white/10 text-gray-400 hover:text-white text-xs rounded-lg transition-colors flex items-center gap-1"
                                >
                                    Restore Backup
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <div
                                    x-show="open"
                                    @click.away="open = false"
                                    class="absolute left-0 top-full mt-1 w-64 bg-gray-900 border border-white/10 rounded-lg shadow-xl z-10"
                                >
                                    @foreach ($backups[$key] as $backup)
                                        <button
                                            wire:click="restore('{{ $key }}')"
                                            wire:confirm="Restore this backup? Current unsaved changes will be lost."
                                            @click="open = false"
                                            class="w-full text-left px-3 py-2 text-xs text-gray-400 hover:bg-white/5 hover:text-white transition-colors"
                                        >
                                            {{ \Carbon\Carbon::createFromTimestamp($backup['created_at'])->format('M j, Y g:i A') }}
                                            ({{ number_format($backup['size'] / 1024, 1) }} KB)
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="flex items-center gap-2">
                        {{-- Reset button --}}
                        <button
                            wire:click="resetToDefaults('{{ $key }}')"
                            wire:confirm="Reset {{ $config['label'] }} to defaults? This cannot be undone."
                            type="button"
                            class="px-4 py-2 bg-red-500/10 hover:bg-red-500/20 text-red-400 text-sm rounded-lg transition-colors"
                        >
                            Reset to Defaults
                        </button>

                        {{-- Save button --}}
                        <button
                            wire:click="save('{{ $key }}')"
                            wire:loading.attr="disabled"
                            wire:target="save"
                            @disabled(!($isDirty[$key] ?? false) || !($isValid[$key] ?? true))
                            class="px-5 py-2 bg-emerald-500 hover:bg-emerald-400 disabled:opacity-50 disabled:cursor-not-allowed text-gray-950 text-sm font-medium rounded-xl transition-colors"
                        >
                            <span wire:loading.remove wire:target="save">Save Changes</span>
                            <span wire:loading wire:target="save">Saving...</span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- File Info --}}
            <div class="text-xs text-gray-600 px-2">
                @if ($fileExists[$key] ?? false)
                    <span>Last modified: {{ \Carbon\Carbon::createFromTimestamp(filemtime(config("vibecodepc.config_files.{$key}.path")))->diffForHumans() }}</span>
                @else
                    <span>File will be created on save</span>
                @endif
            </div>
        </div>
    @endforeach
</div>
