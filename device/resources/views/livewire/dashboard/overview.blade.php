<div class="space-y-6">
    {{-- Not Paired Banner --}}
    @if (!$isPaired)
        <div class="bg-amber-500/10 border border-amber-500/30 rounded-2xl p-5">
            <div class="flex items-start gap-4">
                <div class="shrink-0 w-10 h-10 bg-amber-500/20 rounded-full flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 class="text-amber-400 font-semibold">Device not paired — limited to local network</h3>
                    <p class="text-amber-200/70 text-sm mt-1">
                        Your device is running in local-only mode. Pair it with VibeCodePC Cloud to enable remote access, public URLs, and access your projects from anywhere.
                    </p>
                    <a href="{{ route('dashboard.tunnels') }}" class="inline-flex items-center gap-2 mt-3 px-4 py-2 bg-amber-500 hover:bg-amber-400 text-gray-950 font-medium text-sm rounded-xl transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                        </svg>
                        Set up remote access
                    </a>
                </div>
            </div>
        </div>
    @endif

    {{-- Welcome --}}
    <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-6">
        <h2 class="text-xl font-semibold text-white">Welcome back, {{ $username }}</h2>
        <p class="text-gray-400 text-sm mt-1">Here's what's happening on your VibeCodePC.</p>
    </div>

    {{-- Quick Stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        {{-- Projects --}}
        <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-5">
            <div class="text-gray-500 text-sm">Projects</div>
            <div class="text-2xl font-bold text-white mt-1">{{ $projectCount }}</div>
        </div>

        {{-- Running --}}
        <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-5">
            <div class="text-gray-500 text-sm">Running</div>
            <div class="text-2xl font-bold text-green-400 mt-1">{{ $runningCount }}</div>
        </div>

        {{-- Tunnel --}}
        <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-5">
            <div class="text-gray-500 text-sm">Tunnel</div>
            <div class="mt-1">
                @if ($tunnelRunning)
                    <span class="text-xs bg-green-500/20 text-green-400 px-2 py-0.5 rounded-full">Online</span>
                @else
                    <span class="text-xs bg-gray-500/20 text-gray-400 px-2 py-0.5 rounded-full">Offline</span>
                @endif
            </div>
        </div>

        {{-- AI Providers --}}
        <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-5">
            <div class="text-gray-500 text-sm">AI Providers</div>
            <div class="text-2xl font-bold text-emerald-400 mt-1">{{ $aiProviderCount }}</div>
        </div>

        {{-- Copilot --}}
        <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-5">
            <div class="text-gray-500 text-sm">Copilot</div>
            <div class="mt-1">
                @if ($hasCopilot)
                    <span class="text-xs bg-blue-500/20 text-blue-400 px-2 py-0.5 rounded-full">Active</span>
                @else
                    <span class="text-xs bg-gray-500/20 text-gray-400 px-2 py-0.5 rounded-full">Not configured</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-6">
        <h3 class="text-sm font-medium text-gray-400 mb-4">Quick Actions</h3>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('dashboard.projects.create') }}" class="px-4 py-2 bg-emerald-500 hover:bg-emerald-400 text-gray-950 font-medium text-sm rounded-xl transition-colors">
                New Project
            </a>
            <a href="{{ route('dashboard.code-editor') }}" class="px-4 py-2 bg-white/[0.06] hover:bg-white/10 text-white text-sm rounded-lg transition-colors">
                Open Editor
            </a>
            <a href="{{ route('dashboard.ai-services') }}" class="px-4 py-2 bg-white/[0.06] hover:bg-white/10 text-white text-sm rounded-lg transition-colors">
                Manage AI Keys
            </a>
            <a href="{{ route('dashboard.tunnels') }}" class="px-4 py-2 bg-white/[0.06] hover:bg-white/10 text-white text-sm rounded-lg transition-colors">
                Tunnel Settings
            </a>
        </div>
    </div>

    {{-- Recent Activity --}}
    @if (count($recentActivity) > 0)
        <div class="bg-white/[0.02] rounded-2xl border border-white/[0.06] p-6">
            <h3 class="text-sm font-medium text-gray-400 mb-4">Recent Activity</h3>
            <div class="space-y-3">
                @foreach ($recentActivity as $activity)
                    <div class="flex items-start gap-3">
                        <span @class([
                            'w-2 h-2 rounded-full mt-1.5 shrink-0',
                            'bg-green-500' => $activity['type'] === 'info' || $activity['type'] === 'scaffold',
                            'bg-red-500' => $activity['type'] === 'error',
                            'bg-amber-500' => $activity['type'] === 'warning',
                            'bg-blue-500' => $activity['type'] === 'docker',
                        ])></span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-white truncate">{{ $activity['message'] }}</p>
                            <p class="text-xs text-gray-500">{{ $activity['created_at'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
