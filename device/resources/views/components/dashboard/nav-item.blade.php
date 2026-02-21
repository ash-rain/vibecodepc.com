@props(['route', 'icon', 'label'])

@php
    $active = request()->routeIs($route) || request()->routeIs($route . '.*');
@endphp

<a
    href="{{ route($route) }}"
    @class([
        'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors',
        'bg-amber-500/10 text-amber-400' => $active,
        'text-gray-400 hover:text-white hover:bg-gray-800/50' => !$active,
    ])
>
    <span @class(['w-5 h-5 shrink-0', 'text-amber-400' => $active, 'text-gray-500' => !$active])>
        {!! $icon !!}
    </span>
    <span>{{ $label }}</span>
</a>
