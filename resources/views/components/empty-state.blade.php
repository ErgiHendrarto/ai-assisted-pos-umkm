@props([
    'title',
    'description' => null,
    'tone' => 'slate',
])

@php
    $iconBg = match ($tone) {
        'emerald' => 'bg-[#EBF7EE] text-[#1B7A43]',
        'blue' => 'bg-surface-container-low text-secondary',
        default => 'bg-surface-container-low text-outline',
    };
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center text-center px-6 py-10']) }}>
    <div class="flex h-11 w-11 items-center justify-center rounded-xl {{ $iconBg }}">
        @if (isset($icon))
            {{ $icon }}
        @else
            <span class="material-symbols-outlined text-[22px]">info</span>
        @endif
    </div>
    <h4 class="mt-3 text-sm font-semibold text-on-surface">{{ $title }}</h4>
    @if ($description)
        <p class="mt-1 max-w-sm text-xs leading-relaxed text-on-surface-variant">{{ $description }}</p>
    @endif
    @if (isset($action))
        <div class="mt-4">
            {{ $action }}
        </div>
    @endif
</div>
