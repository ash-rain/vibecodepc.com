@props(['steps', 'currentStep'])

<div class="w-full max-w-3xl mx-auto">
    <div class="flex items-center justify-between">
        @foreach ($steps as $index => $step)
            @php
                $isCurrent = $step['step'] === $currentStep;
                $isCompleted = $step['status'] === 'completed';
                $isSkipped = $step['status'] === 'skipped';
                $isDone = $isCompleted || $isSkipped;
            @endphp

            {{-- Step Circle + Label --}}
            <div class="flex flex-col items-center relative" style="min-width: 60px;">
                <button
                    wire:click="navigateToStep('{{ $step['step'] }}')"
                    @class([
                        'w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all',
                        'bg-emerald-500 text-gray-950' => $isCurrent,
                        'bg-green-500 text-white' => $isCompleted,
                        'bg-gray-600 text-gray-300' => $isSkipped,
                        'bg-gray-800 text-gray-500 border border-gray-700' => !$isCurrent && !$isDone,
                        'cursor-pointer hover:ring-2 hover:ring-emerald-400/50' => $isDone,
                        'cursor-default' => !$isDone && !$isCurrent,
                    ])
                    @if(!$isDone && !$isCurrent) disabled @endif
                >
                    @if ($isCompleted)
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                        </svg>
                    @elseif ($isSkipped)
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                        </svg>
                    @else
                        {{ $index + 1 }}
                    @endif
                </button>
                <span @class([
                    'text-xs mt-1.5 whitespace-nowrap',
                    'text-emerald-400 font-semibold' => $isCurrent,
                    'text-green-400' => $isCompleted,
                    'text-gray-500' => $isSkipped,
                    'text-gray-600' => !$isCurrent && !$isDone,
                ])>
                    {{ $step['label'] }}
                </span>
            </div>

            {{-- Connector Line --}}
            @if (!$loop->last)
                <div @class([
                    'flex-1 h-0.5 mx-1 mb-5',
                    'bg-green-500' => $isDone,
                    'bg-emerald-500/30' => $isCurrent,
                    'bg-gray-800' => !$isCurrent && !$isDone,
                ])></div>
            @endif
        @endforeach
    </div>
</div>
