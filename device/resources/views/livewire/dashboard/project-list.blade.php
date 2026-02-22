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
                        ])>{{ $project->status->label() }}</span>
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
                        @if ($project->isRunning())
                            <button
                                wire:click="stopProject({{ $project->id }})"
                                wire:loading.attr="disabled"
                                class="px-3 py-1.5 bg-red-500/10 hover:bg-red-500/20 text-red-400 text-xs rounded-lg transition-colors"
                            >Stop</button>
                            @if ($project->port)
                                <a
                                    href="http://localhost:{{ $project->port }}"
                                    target="_blank"
                                    class="px-3 py-1.5 bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 text-xs rounded-lg transition-colors"
                                >Preview</a>
                            @endif
                        @else
                            <button
                                wire:click="startProject({{ $project->id }})"
                                wire:loading.attr="disabled"
                                class="px-3 py-1.5 bg-green-500/10 hover:bg-green-500/20 text-green-400 text-xs rounded-lg transition-colors"
                            >Start</button>
                        @endif

                        <a href="{{ route('dashboard.projects.show', $project) }}" class="px-3 py-1.5 bg-gray-800 hover:bg-gray-700 text-white text-xs rounded-lg transition-colors">
                            Details
                        </a>

                        <button
                            wire:click="openInVsCode({{ $project->id }})"
                            class="px-3 py-1.5 bg-gray-800 hover:bg-gray-700 text-white text-xs rounded-lg transition-colors"
                        >Editor</button>

                        <button
                            wire:click="deleteProject({{ $project->id }})"
                            wire:confirm="Are you sure you want to delete this project? This cannot be undone."
                            class="ml-auto px-3 py-1.5 bg-red-500/10 hover:bg-red-500/20 text-red-400 text-xs rounded-lg transition-colors"
                        >Delete</button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
