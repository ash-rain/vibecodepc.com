@props(['route' => null, 'href' => null, 'icon', 'label', 'exact' => false, 'external' => false])

@php
    $url = $href ?? ($route ? route($route) : '#');
    $active = $route && ($exact
        ? request()->routeIs($route)
        : request()->routeIs($route) || request()->routeIs($route . '.*'));
@endphp

<a
    href="{{ $url }}"
    @if($external) target="_blank" rel="noopener noreferrer" @endif
    @class([
        'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors',
        'bg-emerald-500/10 text-emerald-400' => $active,
        'text-gray-400 hover:text-white hover:bg-white/[0.04]' => !$active,
    ])
>
    <span @class(['w-5 h-5 shrink-0', 'text-emerald-400' => $active, 'text-gray-500' => !$active])>
        {!! $icon !!}
    </span>
    <span>{{ $label }}</span>
    @if($external)
        <svg class="w-3.5 h-3.5 ml-auto text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
    @endif
</a>
