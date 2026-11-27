@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
])

@php
    $variants = [
        'primary' => 'bg-primary-container text-on-primary hover:bg-primary focus-visible:ring-primary-fixed border border-transparent',
        'secondary' => 'bg-surface-container-lowest text-on-surface hover:bg-surface-container-low focus-visible:ring-primary-fixed border border-divider',
        'soft' => 'bg-surface-container-low text-on-surface hover:bg-surface-container focus-visible:ring-primary-fixed border border-transparent',
        'ghost' => 'bg-transparent text-secondary hover:bg-surface-container-low hover:text-primary focus-visible:ring-primary-fixed border border-transparent',
        'danger' => 'bg-surface-container-lowest text-[#C53030] hover:bg-[#FDECEB] focus-visible:ring-error-container border border-[#FDECEB]',
    ];

    $sizes = [
        'sm' => 'px-3 h-8 text-xs gap-1.5',
        'md' => 'px-3.5 h-9 text-[13px] gap-2',
        'lg' => 'px-5 h-11 text-sm gap-2',
    ];

    $classes = 'inline-flex items-center justify-center rounded-lg font-medium transition-colors duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-1 disabled:opacity-50 disabled:pointer-events-none cursor-pointer '
        . ($variants[$variant] ?? $variants['primary']) . ' '
        . ($sizes[$size] ?? $sizes['md']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
