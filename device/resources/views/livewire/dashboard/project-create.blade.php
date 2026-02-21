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

    {{-- Step 1: Name + Framework --}}
    @if ($step === 1)
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-6 space-y-5">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-300 mb-1.5">Project Name</label>
                <input
                    wire:model="name"
                    id="name"
                    type="text"
                    class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-white placeholder-gray-500 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none"
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
                            @class([
                                'p-4 rounded-lg border text-left transition-colors',
                                'border-amber-500 bg-amber-500/10' => $framework === $fw['value'],
                                'border-gray-700 bg-gray-800/50 hover:border-gray-600' => $framework !== $fw['value'],
                            ])
                        >
                            <div class="text-sm font-medium text-white">{{ $fw['label'] }}</div>
                            <div class="text-xs text-gray-500 mt-0.5">Port {{ $fw['port'] }}</div>
                        </button>
                    @endforeach
                </div>
                @error('framework') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end">
                <button
                    wire:click="nextStep"
                    class="px-6 py-2.5 bg-amber-500 hover:bg-amber-600 text-gray-950 font-semibold rounded-lg transition-colors"
                >
                    Next
                </button>
            </div>
        </div>
    @endif

    {{-- Step 2: Confirm + Scaffold --}}
    @if ($step === 2)
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-6 space-y-5">
            <h3 class="text-white font-medium">Confirm Project</h3>

            <div class="bg-gray-800/50 rounded-lg p-4 space-y-2">
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
                    class="px-6 py-2.5 bg-amber-500 hover:bg-amber-600 text-gray-950 font-semibold rounded-lg transition-colors disabled:opacity-50 disabled:cursor-wait"
                >
                    <span wire:loading.remove wire:target="scaffold">Create Project</span>
                    <span wire:loading wire:target="scaffold" class="flex items-center gap-2">
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
</div>
