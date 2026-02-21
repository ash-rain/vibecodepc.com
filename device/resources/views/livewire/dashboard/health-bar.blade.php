<div wire:poll.10s="poll" class="hidden sm:flex items-center gap-4 text-xs">
    {{-- CPU --}}
    <div class="flex items-center gap-1.5">
        <span class="text-gray-500">CPU</span>
        <div class="w-16 h-1.5 bg-gray-800 rounded-full overflow-hidden">
            <div
                @class([
                    'h-full rounded-full transition-all duration-500',
                    'bg-green-500' => $cpuPercent < 60,
                    'bg-amber-500' => $cpuPercent >= 60 && $cpuPercent < 85,
                    'bg-red-500' => $cpuPercent >= 85,
                ])
                style="width: {{ min(100, $cpuPercent) }}%"
            ></div>
        </div>
        <span class="text-gray-400 w-8 text-right">{{ $cpuPercent }}%</span>
    </div>

    {{-- RAM --}}
    <div class="flex items-center gap-1.5">
        <span class="text-gray-500">RAM</span>
        <div class="w-16 h-1.5 bg-gray-800 rounded-full overflow-hidden">
            @php $ramPercent = $ramTotalMb > 0 ? ($ramUsedMb / $ramTotalMb) * 100 : 0; @endphp
            <div
                @class([
                    'h-full rounded-full transition-all duration-500',
                    'bg-green-500' => $ramPercent < 60,
                    'bg-amber-500' => $ramPercent >= 60 && $ramPercent < 85,
                    'bg-red-500' => $ramPercent >= 85,
                ])
                style="width: {{ min(100, $ramPercent) }}%"
            ></div>
        </div>
        <span class="text-gray-400 w-20 text-right">{{ $ramUsedMb }}/{{ $ramTotalMb }}M</span>
    </div>

    {{-- Disk --}}
    <div class="flex items-center gap-1.5">
        <span class="text-gray-500">Disk</span>
        @php $diskPercent = $diskTotalGb > 0 ? ($diskUsedGb / $diskTotalGb) * 100 : 0; @endphp
        <div class="w-16 h-1.5 bg-gray-800 rounded-full overflow-hidden">
            <div
                @class([
                    'h-full rounded-full transition-all duration-500',
                    'bg-green-500' => $diskPercent < 70,
                    'bg-amber-500' => $diskPercent >= 70 && $diskPercent < 90,
                    'bg-red-500' => $diskPercent >= 90,
                ])
                style="width: {{ min(100, $diskPercent) }}%"
            ></div>
        </div>
        <span class="text-gray-400 w-16 text-right">{{ $diskUsedGb }}/{{ $diskTotalGb }}G</span>
    </div>

    {{-- Temperature --}}
    @if ($temperatureC !== null)
        <div class="flex items-center gap-1.5">
            <span class="text-gray-500">Temp</span>
            <span @class([
                'text-green-400' => $temperatureC < 60,
                'text-amber-400' => $temperatureC >= 60 && $temperatureC < 75,
                'text-red-400' => $temperatureC >= 75,
            ])>{{ $temperatureC }}&deg;C</span>
        </div>
    @endif
</div>
