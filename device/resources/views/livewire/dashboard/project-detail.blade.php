<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('dashboard.projects') }}" class="text-gray-400 hover:text-white text-sm transition-colors">&larr; Back to Projects</a>
            <h2 class="text-lg font-semibold text-white mt-2">{{ $project->name }}</h2>
            <div class="flex items-center gap-2 mt-1">
                <span class="text-xs text-gray-500">{{ $project->framework->label() }}</span>
                <span @class([
                    'text-xs px-2 py-0.5 rounded-full',
                    'bg-green-500/20 text-green-400' => $project->status->color() === 'green',
                    'bg-amber-500/20 text-amber-400' => $project->status->color() === 'amber',
                    'bg-gray-500/20 text-gray-400' => $project->status->color() === 'gray',
                    'bg-red-500/20 text-red-400' => $project->status->color() === 'red',
                ])>{{ $project->status->label() }}</span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            @if ($project->isRunning())
                <button wire:click="stop" wire:loading.attr="disabled" class="px-4 py-2 bg-red-500/10 hover:bg-red-500/20 text-red-400 text-sm rounded-lg transition-colors">Stop</button>
                <button wire:click="restart" wire:loading.attr="disabled" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-white text-sm rounded-lg transition-colors">Restart</button>
            @else
                <button wire:click="start" wire:loading.attr="disabled" class="px-4 py-2 bg-green-500/10 hover:bg-green-500/20 text-green-400 text-sm rounded-lg transition-colors">Start</button>
            @endif
        </div>
    </div>

    {{-- Overview --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
            <div class="text-gray-500 text-xs mb-1">Port</div>
            <div class="text-white font-medium">{{ $project->port ?? '—' }}</div>
        </div>
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
            <div class="text-gray-500 text-xs mb-1">Path</div>
            <div class="text-white font-medium text-sm truncate">{{ $project->path }}</div>
        </div>
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
            <div class="text-gray-500 text-xs mb-1">Public URL</div>
            <div class="text-sm truncate">
                @if ($project->getPublicUrl())
                    <a href="{{ $project->getPublicUrl() }}" target="_blank" class="text-amber-400 hover:underline">{{ $project->getPublicUrl() }}</a>
                @else
                    <span class="text-gray-500">Not published</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Resource Usage --}}
    @if ($resourceUsage)
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
            <h3 class="text-sm font-medium text-gray-400 mb-3">Resource Usage</h3>
            <div class="flex gap-6 text-sm">
                <div><span class="text-gray-500">CPU:</span> <span class="text-white">{{ $resourceUsage['cpu'] }}</span></div>
                <div><span class="text-gray-500">Memory:</span> <span class="text-white">{{ $resourceUsage['memory'] }}</span></div>
            </div>
        </div>
    @endif

    {{-- Tunnel --}}
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-medium text-white">Tunnel Access</h3>
                <p class="text-xs text-gray-500 mt-0.5">Expose this project to the internet via your VibeCodePC tunnel.</p>
            </div>
            <button
                wire:click="toggleTunnel"
                @class([
                    'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200',
                    'bg-amber-500' => $project->tunnel_enabled,
                    'bg-gray-700' => !$project->tunnel_enabled,
                ])
            >
                <span @class([
                    'pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow ring-0 transition-transform duration-200',
                    'translate-x-5' => $project->tunnel_enabled,
                    'translate-x-0' => !$project->tunnel_enabled,
                ])></span>
            </button>
        </div>
    </div>

    {{-- Environment Variables --}}
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
        <h3 class="text-sm font-medium text-gray-400 mb-3">Environment Variables</h3>

        @if (count($envVars) > 0)
            <div class="space-y-2 mb-4">
                @foreach ($envVars as $key => $value)
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-gray-300 font-mono flex-1 truncate">{{ $key }}</span>
                        <span class="text-sm text-gray-500 font-mono flex-1 truncate">{{ str_repeat('*', min(20, strlen($value))) }}</span>
                        <button wire:click="removeEnvVar('{{ $key }}')" class="text-red-400 hover:text-red-300 text-xs shrink-0">Remove</button>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="flex gap-2">
            <input wire:model="newEnvKey" type="text" placeholder="KEY" class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white font-mono placeholder-gray-500 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none">
            <input wire:model="newEnvValue" type="text" placeholder="value" class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white font-mono placeholder-gray-500 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none">
            <button wire:click="addEnvVar" class="px-3 py-2 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded-lg transition-colors">Add</button>
        </div>
    </div>

    {{-- Container Logs --}}
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-medium text-gray-400">Logs</h3>
            <button wire:click="refreshLogs" class="text-xs text-gray-500 hover:text-white transition-colors">Refresh</button>
        </div>

        @if (count($containerLogs) > 0)
            <div class="bg-gray-950 rounded-lg p-3 max-h-64 overflow-y-auto font-mono text-xs text-gray-400 space-y-0.5">
                @foreach ($containerLogs as $line)
                    <div>{{ $line }}</div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-500">No logs available. Start the project to see container output.</p>
        @endif
    </div>

    {{-- Danger Zone --}}
    <div class="bg-gray-900 rounded-xl border border-red-500/20 p-5">
        <h3 class="text-sm font-medium text-red-400 mb-3">Danger Zone</h3>
        <button
            wire:click="deleteProject"
            wire:confirm="Are you sure? This will delete the project, its container, and all files. This cannot be undone."
            class="px-4 py-2 bg-red-500/10 hover:bg-red-500/20 text-red-400 text-sm rounded-lg transition-colors"
        >Delete Project</button>
    </div>
</div>
