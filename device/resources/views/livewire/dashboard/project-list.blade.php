<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-white">Your Projects</h2>
            <p class="text-gray-400 text-sm mt-0.5">Manage your development projects.</p>
        </div>
        <a href="{{ route('dashboard.projects.create') }}" class="px-4 py-2 bg-emerald-500 hover:bg-emerald-400 text-gray-950 font-medium text-sm rounded-xl transition-colors">
            New Project
        </a>
    </div>

    {{-- Project List --}}
    @if ($this->projects->isEmpty())
        <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-12 text-center">
            <svg class="w-12 h-12 text-gray-700 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
            </svg>
            <h3 class="text-white font-medium mb-1">No projects yet</h3>
            <p class="text-gray-500 text-sm mb-4">Create your first project to get started.</p>
            <a href="{{ route('dashboard.projects.create') }}" class="inline-block px-4 py-2 bg-emerald-500 hover:bg-emerald-400 text-gray-950 font-medium text-sm rounded-xl transition-colors">
                Create Project
            </a>
        </div>
    @else
        <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] divide-y divide-white/[0.06]">
            @foreach ($this->projects as $project)
                {{-- Error banner --}}
                @if (! empty($actionErrors[$project->id]))
                    <div class="flex items-start gap-3 px-5 py-3 bg-red-500/5 border-b border-red-500/10">
                        <svg class="w-4 h-4 text-red-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-red-400">Failed to update {{ $project->name }}</p>
                            <pre class="mt-1 text-xs text-red-300/80 whitespace-pre-wrap font-mono break-words">{{ $actionErrors[$project->id] }}</pre>
                        </div>
                        <button wire:click="dismissError({{ $project->id }})" class="text-red-400/60 hover:text-red-400 shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                @endif

                <div class="flex items-center gap-4 px-5 py-4 transition-colors hover:bg-white/[0.02]">
                    {{-- Status indicator --}}
                    <span @class([
                        'w-2 h-2 rounded-full shrink-0',
                        'bg-green-400' => $project->status->color() === 'green',
                        'bg-amber-400' => $project->status->color() === 'amber',
                        'bg-gray-400' => $project->status->color() === 'gray',
                        'bg-red-400' => $project->status->color() === 'red',
                        'bg-blue-400' => $project->status->color() === 'blue',
                    ])></span>

                    {{-- Project info --}}
                    <a href="{{ route('dashboard.projects.show', $project) }}" class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-white font-medium truncate">{{ $project->name }}</span>
                            @if ($project->isProvisioning())
                                <span class="inline-flex items-center gap-1 text-xs text-blue-400">
                                    <span class="relative flex h-1.5 w-1.5">
                                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-blue-400 opacity-75"></span>
                                        <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                                    </span>
                                    Provisioning
                                </span>
                            @endif
                        </div>
                        <div class="flex items-center gap-3 mt-0.5 text-xs text-gray-500">
                            <span>{{ $project->framework->label() }}</span>
                            @if ($project->port)
                                <span>Port {{ $project->port }}</span>
                            @endif
                            @if ($project->getPublicUrl())
                                <span class="truncate">{{ $project->getPublicUrl() }}</span>
                            @endif
                        </div>
                    </a>

                    {{-- Status badge --}}
                    <span @class([
                        'text-xs px-2 py-0.5 rounded-full shrink-0',
                        'bg-green-500/20 text-green-400' => $project->status->color() === 'green',
                        'bg-amber-500/20 text-amber-400' => $project->status->color() === 'amber',
                        'bg-gray-500/20 text-gray-400' => $project->status->color() === 'gray',
                        'bg-red-500/20 text-red-400' => $project->status->color() === 'red',
                        'bg-blue-500/20 text-blue-400' => $project->status->color() === 'blue',
                    ])>{{ $project->status->label() }}</span>

                    {{-- Actions --}}
                    @if (! $project->isProvisioning())
                        <div x-data="{ open: false }" class="relative shrink-0">
                            <button @click="open = !open" class="p-1.5 text-gray-500 hover:text-white rounded-lg hover:bg-white/[0.06] transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                                </svg>
                            </button>

                            <div
                                x-show="open"
                                x-cloak
                                @click.outside="open = false"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="opacity-100 scale-100"
                                x-transition:leave-end="opacity-0 scale-95"
                                class="absolute right-0 top-full mt-1 w-40 bg-gray-900 border border-white/10 rounded-xl shadow-lg py-1 z-10"
                            >
                                @if ($project->isRunning())
                                    <button wire:click="stopProject({{ $project->id }})" wire:loading.attr="disabled" @click="open = false" class="w-full text-left px-3 py-1.5 text-xs text-red-400 hover:bg-white/[0.04] transition-colors">Stop</button>
                                    @if ($project->port)
                                        <a href="http://localhost:{{ $project->port }}" target="_blank" class="block px-3 py-1.5 text-xs text-emerald-400 hover:bg-white/[0.04] transition-colors">Preview</a>
                                    @endif
                                @else
                                    <button wire:click="startProject({{ $project->id }})" wire:loading.attr="disabled" @click="open = false" class="w-full text-left px-3 py-1.5 text-xs text-green-400 hover:bg-white/[0.04] transition-colors">Start</button>
                                @endif
                                <button wire:click="openInVsCode({{ $project->id }})" @click="open = false" class="w-full text-left px-3 py-1.5 text-xs text-white hover:bg-white/[0.04] transition-colors">Open in Editor</button>
                                <div class="border-t border-white/10 my-1"></div>
                                <button wire:click="deleteProject({{ $project->id }})" wire:confirm="Are you sure you want to delete this project? This cannot be undone." @click="open = false" class="w-full text-left px-3 py-1.5 text-xs text-red-400 hover:bg-white/[0.04] transition-colors">Delete</button>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
