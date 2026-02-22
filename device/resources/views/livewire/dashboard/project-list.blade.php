<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-white">Your Projects</h2>
            <p class="text-gray-400 text-sm mt-0.5">Manage your development projects.</p>
        </div>
        <a href="{{ route('dashboard.projects.create') }}" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-gray-950 font-medium text-sm rounded-lg transition-colors">
            New Project
        </a>
    </div>

    {{-- Project Grid --}}
    @if ($this->projects->isEmpty())
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-12 text-center">
            <svg class="w-12 h-12 text-gray-700 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
            </svg>
            <h3 class="text-white font-medium mb-1">No projects yet</h3>
            <p class="text-gray-500 text-sm mb-4">Create your first project to get started.</p>
            <a href="{{ route('dashboard.projects.create') }}" class="inline-block px-4 py-2 bg-amber-500 hover:bg-amber-600 text-gray-950 font-medium text-sm rounded-lg transition-colors">
                Create Project
            </a>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach ($this->projects as $project)
                <div class="bg-gray-900 rounded-xl border border-gray-800 p-5 flex flex-col">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <h3 class="text-white font-medium">{{ $project->name }}</h3>
                            <span class="text-xs text-gray-500">{{ $project->framework->label() }}</span>
                        </div>
                        <span @class([
                            'text-xs px-2 py-0.5 rounded-full',
                            'bg-green-500/20 text-green-400' => $project->status->color() === 'green',
                            'bg-amber-500/20 text-amber-400' => $project->status->color() === 'amber',
                            'bg-gray-500/20 text-gray-400' => $project->status->color() === 'gray',
                            'bg-red-500/20 text-red-400' => $project->status->color() === 'red',
                            'bg-blue-500/20 text-blue-400' => $project->status->color() === 'blue',
                        ])>
                            @if ($project->isProvisioning())
                                <span class="inline-flex items-center gap-1">
                                    <span class="relative flex h-1.5 w-1.5">
                                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-blue-400 opacity-75"></span>
                                        <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                                    </span>
                                    {{ $project->status->label() }}
                                </span>
                            @else
                                {{ $project->status->label() }}
                            @endif
                        </span>
                    </div>

                    <div class="text-xs text-gray-500 space-y-1 mb-4">
                        @if ($project->port)
                            <div>Port: {{ $project->port }}</div>
                        @endif
                        @if ($project->getPublicUrl())
                            <div class="truncate">
                                URL: <a href="{{ $project->getPublicUrl() }}" target="_blank" class="text-amber-400 hover:underline">{{ $project->getPublicUrl() }}</a>
                            </div>
                        @endif
                    </div>

                    <div class="flex items-center gap-2 mt-auto pt-3 border-t border-gray-800">
                        <a href="{{ route('dashboard.projects.show', $project) }}" class="px-3 py-1.5 bg-gray-800 hover:bg-gray-700 text-white text-xs rounded-lg transition-colors">
                            Details
                        </a>

                        @if (! $project->isProvisioning())
                        <div x-data="{ open: false }" class="relative ml-auto">
                            <button @click="open = !open" class="px-3 py-1.5 bg-gray-800 hover:bg-gray-700 text-white text-xs rounded-lg transition-colors flex items-center gap-1">
                                Actions
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                            </button>

                            <div
                                x-show="open"
                                @click.outside="open = false"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="opacity-100 scale-100"
                                x-transition:leave-end="opacity-0 scale-95"
                                class="absolute right-0 bottom-full mb-1 w-40 bg-gray-800 border border-gray-700 rounded-lg shadow-lg py-1 z-10"
                            >
                                @if ($project->isRunning())
                                    <button wire:click="stopProject({{ $project->id }})" wire:loading.attr="disabled" @click="open = false" class="w-full text-left px-3 py-1.5 text-xs text-red-400 hover:bg-gray-700 transition-colors">Stop</button>
                                    @if ($project->port)
                                        <a href="http://localhost:{{ $project->port }}" target="_blank" class="block px-3 py-1.5 text-xs text-amber-400 hover:bg-gray-700 transition-colors">Preview</a>
                                    @endif
                                @else
                                    <button wire:click="startProject({{ $project->id }})" wire:loading.attr="disabled" @click="open = false" class="w-full text-left px-3 py-1.5 text-xs text-green-400 hover:bg-gray-700 transition-colors">Start</button>
                                @endif
                                <button wire:click="openInVsCode({{ $project->id }})" @click="open = false" class="w-full text-left px-3 py-1.5 text-xs text-white hover:bg-gray-700 transition-colors">Open in Editor</button>
                                <div class="border-t border-gray-700 my-1"></div>
                                <button wire:click="deleteProject({{ $project->id }})" wire:confirm="Are you sure you want to delete this project? This cannot be undone." @click="open = false" class="w-full text-left px-3 py-1.5 text-xs text-red-400 hover:bg-gray-700 transition-colors">Delete</button>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
