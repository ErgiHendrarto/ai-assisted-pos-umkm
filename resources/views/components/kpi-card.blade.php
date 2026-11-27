@props([
    'title',
    'value',
    'subtitle' => null,
    'tone' => 'primary',
    'footerLabel' => null,
    'footerValue' => null,
    'footerTone' => 'default',
])

@php
    $iconWrap = match ($tone) {
        'error', 'rose' => 'bg-error-container/40 text-error',
        'amber' => 'bg-[#FEF7EA] text-[#B57914]',
        'emerald', 'tertiary' => 'bg-[#EBF7EE] text-[#1B7A43]',
        'secondary' => 'bg-surface-container-low text-secondary',
        default => 'bg-surface-container-low text-primary',
    };

    $valueColor = match ($tone) {
        'error', 'rose' => 'text-error',
        default => 'text-primary',
    };

    $footerValueClass = match ($footerTone) {
        'error' => 'text-error font-semibold',
        'success' => 'text-[#1B7A43] font-semibold',
        default => 'text-on-surface font-semibold',
    };
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col justify-between p-4 rounded-xl bg-surface-container-lowest border border-divider/80 shadow-[0_1px_3px_0_rgba(37,43,54,0.04)]']) }}>
    <div class="flex items-start justify-between gap-3">
        <span class="text-xs font-medium text-on-surface-variant">
            {{ $title }}
        </span>
        @if (isset($icon))
            <span class="p-1.5 rounded-lg shrink-0 {{ $iconWrap }}">
                {{ $icon }}
            </span>
        @endif
    </div>

    <div class="mt-3 flex flex-col gap-1">
        <span class="text-[22px] leading-7 font-semibold tracking-[-0.02em] tabular-nums {{ $valueColor }}">
            {{ $value }}
        </span>
        @if ($subtitle)
            <div class="text-[11px] font-medium text-outline leading-4">
                {{ $subtitle }}
            </div>
        @endif
        @if (isset($meta))
            <div class="flex items-center gap-1.5 flex-wrap">
                {{ $meta }}
            </div>
        @endif
    </div>

    @if ($footerLabel || $footerValue)
        <div class="mt-3 pt-2.5 border-t border-divider/60 text-[11px] font-medium text-outline flex items-center justify-between gap-2">
            <span>{{ $footerLabel }}</span>
            <span class="tabular-nums {{ $footerValueClass }}">{{ $footerValue }}</span>
        </div>
    @endif
</div>
