<div class="max-w-2xl mx-auto space-y-6">
    {{-- Header --}}
    <div>
        <a href="{{ route('dashboard.projects') }}" class="text-gray-400 hover:text-white text-sm transition-colors">&larr; Back to Projects</a>
        <h2 class="text-lg font-semibold text-white mt-2">Create New Project</h2>
    </div>

    @if ($error)
        <div class="bg-red-500/10 border border-red-500/20 rounded-lg p-4 text-red-400 text-sm">
            {{ $error }}
        </div>
    @endif

    {{-- Step 0: Choose Mode --}}
    @if ($step === 0)
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <button
                wire:click="selectMode('template')"
                class="bg-white/[0.02] rounded-2xl border border-white/[0.06] hover:border-emerald-500/50 p-6 text-left transition-colors group"
            >
                <div class="w-10 h-10 rounded-lg bg-emerald-500/10 flex items-center justify-center mb-4 group-hover:bg-emerald-500/20 transition-colors">
                    <svg class="w-5 h-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                </div>
                <div class="text-sm font-medium text-white">New Project</div>
                <p class="text-xs text-gray-500 mt-1">Start from a framework template</p>
            </button>

            <button
                wire:click="selectMode('github')"
                @disabled(! $hasGitHub)
                @class([
                    'bg-white/[0.02] rounded-2xl border p-6 text-left transition-colors group',
                    'border-white/[0.06] hover:border-emerald-500/50' => $hasGitHub,
                    'border-white/[0.06] opacity-50 cursor-not-allowed' => ! $hasGitHub,
                ])
            >
                <div class="w-10 h-10 rounded-lg bg-white/[0.06] flex items-center justify-center mb-4 group-hover:bg-white/10 transition-colors">
                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                    </svg>
                </div>
                <div class="text-sm font-medium text-white">Clone from GitHub</div>
                <p class="text-xs text-gray-500 mt-1">
                    @if ($hasGitHub)
                        Pick a repo from your account
                    @else
                        Connect GitHub in the wizard first
                    @endif
                </p>
            </button>

            <button
                wire:click="selectMode('git-url')"
                class="bg-white/[0.02] rounded-2xl border border-white/[0.06] hover:border-emerald-500/50 p-6 text-left transition-colors group"
            >
                <div class="w-10 h-10 rounded-lg bg-white/[0.06] flex items-center justify-center mb-4 group-hover:bg-white/10 transition-colors">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m9.86-2.03a4.5 4.5 0 00-1.242-7.244l4.5-4.5a4.5 4.5 0 016.364 6.364l-1.757 1.757" />
                    </svg>
                </div>
                <div class="text-sm font-medium text-white">Clone from URL</div>
                <p class="text-xs text-gray-500 mt-1">Clone any public git repository</p>
            </button>

            <button
                wire:click="selectMode('existing')"
                class="bg-white/[0.02] rounded-2xl border border-white/[0.06] hover:border-emerald-500/50 p-6 text-left transition-colors group"
            >
                <div class="w-10 h-10 rounded-lg bg-white/[0.06] flex items-center justify-center mb-4 group-hover:bg-white/10 transition-colors">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 0 0-1.883 2.542l.857 6a2.25 2.25 0 0 0 2.227 1.932H19.05a2.25 2.25 0 0 0 2.227-1.932l.857-6a2.25 2.25 0 0 0-1.883-2.542m-16.5 0V6A2.25 2.25 0 0 1 6 3.75h3.879a1.5 1.5 0 0 1 1.06.44l2.122 2.12a1.5 1.5 0 0 0 1.06.44H18A2.25 2.25 0 0 1 20.25 9v.776" />
                    </svg>
                </div>
                <div class="text-sm font-medium text-white">Add Existing Folder</div>
                <p class="text-xs text-gray-500 mt-1">Link a folder already on this device</p>
            </button>
        </div>
    @endif

    {{-- Step 1: Template mode --}}
    @if ($step === 1 && $mode === 'template')
        <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-6 space-y-5">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-300 mb-1.5">Project Name</label>
                <input
                    wire:model="name"
                    id="name"
                    type="text"
                    class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2.5 text-white placeholder-gray-500 focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/20 focus:outline-none"
                    placeholder="my-awesome-project"
                >
                @error('name') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-3">Framework</label>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    @foreach ($frameworks as $fw)
                        <button
                            wire:click="$set('framework', '{{ $fw['value'] }}')"
                            wire:key="fw-{{ $fw['value'] }}"
                            @class([
                                'p-4 rounded-lg border text-left transition-colors',
                                'border-emerald-500 bg-emerald-500/10' => $framework === $fw['value'],
                                'border-white/10 bg-white/[0.03] hover:border-white/20' => $framework !== $fw['value'],
                            ])
                        >
                            <div class="text-sm font-medium text-white">{{ $fw['label'] }}</div>
                            <div class="text-xs text-gray-500 mt-0.5">Port {{ $fw['port'] }}</div>
                        </button>
                    @endforeach
                </div>
                @error('framework') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-between">
                <button
                    wire:click="back"
                    class="px-6 py-2.5 text-gray-400 hover:text-white transition-colors"
                >Back</button>
                <button
                    wire:click="nextStep"
                    class="px-6 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-gray-950 font-semibold rounded-xl transition-colors"
                >
                    Next
                </button>
            </div>
        </div>
    @endif

    {{-- Step 1: GitHub mode --}}
    @if ($step === 1 && $mode === 'github')
        <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-6 space-y-5">
            <div>
                <label for="repoSearch" class="block text-sm font-medium text-gray-300 mb-1.5">Search Repositories</label>
                <input
                    wire:model.live.debounce.300ms="repoSearch"
                    wire:change="searchRepos"
                    id="repoSearch"
                    type="text"
                    class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2.5 text-white placeholder-gray-500 focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/20 focus:outline-none"
                    placeholder="Search your repos..."
                >
            </div>

            <div class="max-h-64 overflow-y-auto space-y-2 scrollbar-thin">
                @if ($loadingRepos)
                    <div class="flex items-center justify-center py-8 text-gray-500">
                        <svg class="w-5 h-5 animate-spin mr-2" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Loading repositories...
                    </div>
                @elseif (empty($repos))
                    <p class="text-gray-500 text-sm text-center py-8">No repositories found.</p>
                @else
                    @foreach ($repos as $repo)
                        <button
                            wire:click="selectRepo('{{ $repo['fullName'] }}')"
                            wire:key="repo-{{ $repo['fullName'] }}"
                            @class([
                                'w-full p-3 rounded-lg border text-left transition-colors',
                                'border-emerald-500 bg-emerald-500/10' => $selectedRepo === $repo['fullName'],
                                'border-white/10 bg-white/[0.03] hover:border-white/20' => $selectedRepo !== $repo['fullName'],
                            ])
                        >
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-white truncate">{{ $repo['fullName'] }}</span>
                                @if ($repo['isPrivate'])
                                    <span class="shrink-0 text-[10px] font-medium bg-gray-700 text-gray-300 px-1.5 py-0.5 rounded">Private</span>
                                @endif
                                @if ($repo['language'])
                                    <span class="shrink-0 text-[10px] text-gray-500">{{ $repo['language'] }}</span>
                                @endif
                            </div>
                            @if ($repo['description'])
                                <p class="text-xs text-gray-500 mt-1 truncate">{{ $repo['description'] }}</p>
                            @endif
                        </button>
                    @endforeach
                @endif
            </div>

            @error('selectedRepo') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror

            <div>
                <label for="name" class="block text-sm font-medium text-gray-300 mb-1.5">Project Name</label>
                <input
                    wire:model="name"
                    id="name"
                    type="text"
                    class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2.5 text-white placeholder-gray-500 focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/20 focus:outline-none"
                    placeholder="my-awesome-project"
                >
                @error('name') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-between">
                <button
                    wire:click="back"
                    class="px-6 py-2.5 text-gray-400 hover:text-white transition-colors"
                >Back</button>
                <button
                    wire:click="nextStep"
                    class="px-6 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-gray-950 font-semibold rounded-xl transition-colors"
                >
                    Next
                </button>
            </div>
        </div>
    @endif

    {{-- Step 1: Git URL mode --}}
    @if ($step === 1 && $mode === 'git-url')
        <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-6 space-y-5">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-300 mb-1.5">Project Name</label>
                <input
                    wire:model="name"
                    id="name"
                    type="text"
                    class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2.5 text-white placeholder-gray-500 focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/20 focus:outline-none"
                    placeholder="my-awesome-project"
                >
                @error('name') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="gitUrl" class="block text-sm font-medium text-gray-300 mb-1.5">Git URL</label>
                <input
                    wire:model="gitUrl"
                    id="gitUrl"
                    type="text"
                    class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2.5 text-white placeholder-gray-500 focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/20 focus:outline-none"
                    placeholder="https://github.com/user/repo.git"
                >
                @error('gitUrl') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-between">
                <button
                    wire:click="back"
                    class="px-6 py-2.5 text-gray-400 hover:text-white transition-colors"
                >Back</button>
                <button
                    wire:click="nextStep"
                    class="px-6 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-gray-950 font-semibold rounded-xl transition-colors"
                >
                    Next
                </button>
            </div>
        </div>
    @endif

    {{-- Step 1: Existing folder mode --}}
    @if ($step === 1 && $mode === 'existing')
        <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-6 space-y-5">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-300 mb-1.5">Project Name</label>
                <input
                    wire:model="name"
                    id="name"
                    type="text"
                    class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2.5 text-white placeholder-gray-500 focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/20 focus:outline-none"
                    placeholder="my-awesome-project"
                >
                @error('name') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="folderPath" class="block text-sm font-medium text-gray-300 mb-1.5">Folder Path</label>
                <input
                    wire:model="folderPath"
                    id="folderPath"
                    type="text"
                    class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2.5 text-white placeholder-gray-500 focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/20 focus:outline-none font-mono text-sm"
                    placeholder="/home/vibecodepc/my-project"
                >
                <p class="text-xs text-gray-500 mt-1.5">Absolute path to the project folder on this device.</p>
                @error('folderPath') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-between">
                <button
                    wire:click="back"
                    class="px-6 py-2.5 text-gray-400 hover:text-white transition-colors"
                >Back</button>
                <button
                    wire:click="nextStep"
                    class="px-6 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-gray-950 font-semibold rounded-xl transition-colors"
                >
                    Next
                </button>
            </div>
        </div>
    @endif

    {{-- Step 2: Confirm (template mode) --}}
    @if ($step === 2 && $mode === 'template')
        <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-6 space-y-5">
            <h3 class="text-white font-medium">Confirm Project</h3>

            <div class="bg-white/[0.03] rounded-lg p-4 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-400">Name</span>
                    <span class="text-white">{{ $name }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-400">Framework</span>
                    <span class="text-white">{{ collect($frameworks)->firstWhere('value', $framework)['label'] ?? $framework }}</span>
                </div>
            </div>

            <div class="flex justify-between">
                <button
                    wire:click="back"
                    class="px-6 py-2.5 text-gray-400 hover:text-white transition-colors"
                >Back</button>
                <button
                    wire:click="scaffold"
                    wire:loading.attr="disabled"
                    wire:target="scaffold"
                    class="px-6 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-gray-950 font-semibold rounded-xl transition-colors disabled:opacity-50 disabled:cursor-wait"
                >
                    <span wire:loading.remove wire:target="scaffold">Create Project</span>
                    <span wire:loading.flex wire:target="scaffold" class="items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Scaffolding...
                    </span>
                </button>
            </div>
        </div>
    @endif

    {{-- Step 2: Confirm (clone modes) --}}
    @if ($step === 2 && in_array($mode, ['github', 'git-url']))
        <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-6 space-y-5">
            <h3 class="text-white font-medium">Confirm Clone</h3>

            <div class="bg-white/[0.03] rounded-lg p-4 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-400">Name</span>
                    <span class="text-white">{{ $name }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-400">Source</span>
                    <span class="text-white truncate ml-4">{{ $mode === 'github' ? $selectedRepo : $gitUrl }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-400">Framework</span>
                    <span class="text-white">Auto-detect</span>
                </div>
            </div>

            <div class="flex justify-between">
                <button
                    wire:click="back"
                    class="px-6 py-2.5 text-gray-400 hover:text-white transition-colors"
                >Back</button>
                <button
                    wire:click="cloneProject"
                    wire:loading.attr="disabled"
                    wire:target="cloneProject"
                    class="px-6 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-gray-950 font-semibold rounded-xl transition-colors disabled:opacity-50 disabled:cursor-wait"
                >
                    <span wire:loading.remove wire:target="cloneProject">Clone Project</span>
                    <span wire:loading.flex wire:target="cloneProject" class="items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Cloning...
                    </span>
                </button>
            </div>
        </div>
    @endif

    {{-- Step 2: Confirm (existing folder mode) --}}
    @if ($step === 2 && $mode === 'existing')
        <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-6 space-y-5">
            <h3 class="text-white font-medium">Confirm Link</h3>

            <div class="bg-white/[0.03] rounded-lg p-4 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-400">Name</span>
                    <span class="text-white">{{ $name }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-400">Path</span>
                    <span class="text-white truncate ml-4 font-mono text-xs">{{ $folderPath }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-400">Framework</span>
                    <span class="text-white">{{ $detectedFramework ?? 'Auto-detect' }}</span>
                </div>
            </div>

            <p class="text-xs text-gray-500">A symlink will be created in the projects directory pointing to this folder.</p>

            <div class="flex justify-between">
                <button
                    wire:click="back"
                    class="px-6 py-2.5 text-gray-400 hover:text-white transition-colors"
                >Back</button>
                <button
                    wire:click="linkExisting"
                    wire:loading.attr="disabled"
                    wire:target="linkExisting"
                    class="px-6 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-gray-950 font-semibold rounded-xl transition-colors disabled:opacity-50 disabled:cursor-wait"
                >
                    <span wire:loading.remove wire:target="linkExisting">Link Project</span>
                    <span wire:loading.flex wire:target="linkExisting" class="items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Linking...
                    </span>
                </button>
            </div>
        </div>
    @endif
</div>
