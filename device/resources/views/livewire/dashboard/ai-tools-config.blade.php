<div class="space-y-6" x-data="{ tab: @entangle('activeTab') }">
    <div>
        <h2 class="text-lg font-semibold text-white">AI Tools Config</h2>
        <p class="text-gray-400 text-sm mt-0.5">Configure AI coding tools — environment variables, opencode settings, and auth tokens.</p>
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
        @foreach (['environment' => 'Environment', 'opencode' => 'Opencode Config', 'auth' => 'Opencode Auth'] as $key => $label)
            <button
                @click="tab = '{{ $key }}'"
                :class="tab === '{{ $key }}' ? 'text-emerald-400 border-emerald-400' : 'text-gray-500 border-transparent hover:text-gray-300'"
                class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap"
            >{{ $label }}</button>
        @endforeach
    </div>

    {{-- Environment Tab --}}
    <div x-show="tab === 'environment'" class="space-y-6">
        {{-- API Keys --}}
        <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-6 space-y-5">
            <h3 class="text-sm font-medium text-gray-400">API Keys</h3>
            <p class="text-xs text-gray-500">These are written to <span class="font-mono text-gray-400">~/.bashrc</span> in a managed section. Enter a new value to update; leave <span class="font-mono">••••••••</span> to keep the existing key.</p>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs text-gray-400 mb-1.5">Gemini API Key <span class="text-gray-600">GEMINI_API_KEY</span></label>
                    <input
                        wire:model="geminiApiKey"
                        type="password"
                        class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/20 focus:outline-none"
                        placeholder="AIza..."
                    >
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1.5">Claude API Key <span class="text-gray-600">CLAUDE_API_KEY</span></label>
                    <input
                        wire:model="claudeApiKey"
                        type="password"
                        class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/20 focus:outline-none"
                        placeholder="sk-ant-api..."
                    >
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1.5">Ollama API Key <span class="text-gray-600">OLLAMA_API_KEY</span></label>
                    <input
                        wire:model="ollamaApiKey"
                        type="password"
                        class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/20 focus:outline-none"
                        placeholder="ollama key..."
                    >
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1.5">Ollama Cloud API Key <span class="text-gray-600">OLLAMA_CLOUD_API_KEY</span></label>
                    <input
                        wire:model="ollamaCloudApiKey"
                        type="password"
                        class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/20 focus:outline-none"
                        placeholder="cloud key..."
                    >
                </div>
            </div>
        </div>

        {{-- PATH --}}
        <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-6 space-y-4">
            <h3 class="text-sm font-medium text-gray-400">PATH Additions</h3>
            <div>
                <label class="block text-xs text-gray-400 mb-1.5">Extra PATH entries <span class="text-gray-600">prepended to $PATH</span></label>
                <input
                    wire:model="extraPath"
                    type="text"
                    class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white font-mono placeholder-gray-500 focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/20 focus:outline-none"
                    placeholder="/autodev/bin:/root/.opencode/bin"
                >
                <p class="text-xs text-gray-600 mt-1.5">Written as <span class="font-mono text-gray-500">export PATH="&lt;value&gt;:$PATH"</span></p>
            </div>
        </div>

        {{-- Opencode Settings --}}
        <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-6 space-y-5">
            <h3 class="text-sm font-medium text-gray-400">Opencode Settings</h3>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs text-gray-400 mb-1.5">Bash Timeout (ms) <span class="text-gray-600">OPENCODE_EXPERIMENTAL_BASH_DEFAULT_TIMEOUT_MS</span></label>
                    <input
                        wire:model="opencodeExperimentalBashTimeoutMs"
                        type="text"
                        class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white font-mono placeholder-gray-500 focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/20 focus:outline-none"
                        placeholder="94748364"
                    >
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1.5">Max Output Length <span class="text-gray-600">OPENCODE_EXPERIMENTAL_BASH_MAX_OUTPUT_LENGTH</span></label>
                    <input
                        wire:model="opencodeExperimentalBashMaxOutput"
                        type="text"
                        class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white font-mono placeholder-gray-500 focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/20 focus:outline-none"
                        placeholder="94748364"
                    >
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1.5">Composer Process Timeout <span class="text-gray-600">COMPOSER_PROCESS_TIMEOUT</span></label>
                    <input
                        wire:model="composerProcessTimeout"
                        type="text"
                        class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white font-mono placeholder-gray-500 focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/20 focus:outline-none"
                        placeholder="0"
                    >
                </div>
            </div>

            <div class="space-y-3">
                <label class="flex items-center gap-3 cursor-pointer">
                    <div class="relative inline-flex items-center">
                        <input wire:model="opencodeExperimental" type="checkbox" class="sr-only peer">
                        <div class="w-10 h-6 bg-gray-700 rounded-full peer peer-checked:bg-emerald-500 transition-colors"></div>
                        <div class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition-transform peer-checked:translate-x-4"></div>
                    </div>
                    <div>
                        <span class="text-sm text-white">Opencode Experimental</span>
                        <span class="text-xs text-gray-500 ml-2 font-mono">OPENCODE_EXPERIMENTAL=1</span>
                    </div>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                    <div class="relative inline-flex items-center">
                        <input wire:model="opencodeEnableExperimentalModels" type="checkbox" class="sr-only peer">
                        <div class="w-10 h-6 bg-gray-700 rounded-full peer peer-checked:bg-emerald-500 transition-colors"></div>
                        <div class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition-transform peer-checked:translate-x-4"></div>
                    </div>
                    <div>
                        <span class="text-sm text-white">Enable Experimental Models</span>
                        <span class="text-xs text-gray-500 ml-2 font-mono">OPENCODE_ENABLE_EXPERIMENTAL_MODELS=1</span>
                    </div>
                </label>
            </div>
        </div>

        <div class="flex justify-end">
            <button
                wire:click="saveEnvironment"
                wire:loading.attr="disabled"
                wire:target="saveEnvironment"
                class="px-5 py-2 bg-emerald-500 hover:bg-emerald-400 text-gray-950 text-sm font-medium rounded-xl transition-colors"
            >
                <span wire:loading.remove wire:target="saveEnvironment">Save Environment</span>
                <span wire:loading wire:target="saveEnvironment">Saving...</span>
            </button>
        </div>
    </div>

    {{-- Opencode Config Tab --}}
    <div x-show="tab === 'opencode'" class="space-y-4">
        <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-6 space-y-4">
            <div>
                <h3 class="text-sm font-medium text-gray-400">~/.config/opencode/opencode.json</h3>
                <p class="text-xs text-gray-500 mt-1">Configure opencode providers, models, and permissions. See <a href="https://opencode.ai/docs" target="_blank" class="text-emerald-400 hover:underline">opencode.ai/docs</a> for schema details.</p>
            </div>
            <textarea
                wire:model="opencodeConfigJson"
                rows="24"
                class="w-full bg-gray-900 border border-white/10 rounded-xl px-4 py-3 text-sm text-white font-mono placeholder-gray-500 focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/20 focus:outline-none resize-y"
                placeholder='{
  "$schema": "https://opencode.ai/config.json",
  "permission": { "*": "allow" }
}'
            ></textarea>
        </div>
        <div class="flex justify-end">
            <button
                wire:click="saveOpencodeConfig"
                wire:loading.attr="disabled"
                wire:target="saveOpencodeConfig"
                class="px-5 py-2 bg-emerald-500 hover:bg-emerald-400 text-gray-950 text-sm font-medium rounded-xl transition-colors"
            >
                <span wire:loading.remove wire:target="saveOpencodeConfig">Save Config</span>
                <span wire:loading wire:target="saveOpencodeConfig">Saving...</span>
            </button>
        </div>
    </div>

    {{-- Opencode Auth Tab --}}
    <div x-show="tab === 'auth'" class="space-y-4">
        <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-6 space-y-4">
            <div>
                <h3 class="text-sm font-medium text-gray-400">~/.local/share/opencode/auth.json</h3>
                <p class="text-xs text-gray-500 mt-1">Provider API keys used by opencode. Each entry maps a provider name to its auth type and key.</p>
            </div>
            <textarea
                wire:model="opencodeAuthJson"
                rows="18"
                class="w-full bg-gray-900 border border-white/10 rounded-xl px-4 py-3 text-sm text-white font-mono placeholder-gray-500 focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/20 focus:outline-none resize-y"
                placeholder='{
  "anthropic": { "type": "api", "key": "sk-ant-api-..." },
  "opencode": { "type": "api", "key": "..." }
}'
            ></textarea>
            <p class="text-xs text-gray-600">Stored in plaintext on this device — treat this file like any other credential file.</p>
        </div>
        <div class="flex justify-end">
            <button
                wire:click="saveOpencodeAuth"
                wire:loading.attr="disabled"
                wire:target="saveOpencodeAuth"
                class="px-5 py-2 bg-emerald-500 hover:bg-emerald-400 text-gray-950 text-sm font-medium rounded-xl transition-colors"
            >
                <span wire:loading.remove wire:target="saveOpencodeAuth">Save Auth</span>
                <span wire:loading wire:target="saveOpencodeAuth">Saving...</span>
            </button>
        </div>
    </div>
</div>
