<div class="space-y-6">
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-8 text-center">
        <div class="mb-6">
            <svg class="w-16 h-16 mx-auto text-green-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h2 class="text-2xl font-bold text-white mb-2">Setup Complete!</h2>
            <p class="text-gray-400">Your VibeCodePC is ready to use. Here's a summary of what was configured.</p>
        </div>

        {{-- Summary --}}
        <div class="bg-gray-800/50 rounded-lg p-6 mb-6 text-left">
            <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-4">Configuration Summary</h3>
            <div class="space-y-3">
                @foreach ($summary as $item)
                    <div class="flex items-center justify-between">
                        <span class="text-gray-300">{{ $item['label'] }}</span>
                        @if ($item['status'] === 'completed')
                            <span class="inline-flex items-center gap-1.5 text-sm text-green-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Configured
                            </span>
                        @elseif ($item['status'] === 'skipped')
                            <span class="inline-flex items-center gap-1.5 text-sm text-gray-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                                </svg>
                                Skipped
                            </span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
            <a href="{{ route('dashboard') }}" class="bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg p-4 transition-colors block">
                <svg class="w-8 h-8 mx-auto text-amber-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                </svg>
                <span class="text-sm text-gray-300">View Dashboard</span>
            </a>
            <a href="{{ $codeServerUrl }}" target="_blank" class="bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg p-4 transition-colors block">
                <svg class="w-8 h-8 mx-auto text-amber-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                </svg>
                <span class="text-sm text-gray-300">Open VS Code</span>
            </a>
            <a href="{{ route('dashboard') }}" class="bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg p-4 transition-colors block">
                <svg class="w-8 h-8 mx-auto text-amber-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                <span class="text-sm text-gray-300">Create Project</span>
            </a>
        </div>
    </div>

    {{-- Go to Dashboard --}}
    <button
        wire:click="goToDashboard"
        class="w-full py-3 bg-amber-500 hover:bg-amber-600 text-gray-950 font-semibold rounded-lg transition-colors"
    >
        Go to Dashboard
    </button>
</div>
