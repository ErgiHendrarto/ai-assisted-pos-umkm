@props([
    'status' => 'default',
    'label' => null,
])

@php
    $normalized = strtolower((string) $status);

    $styles = match ($normalized) {
        'completed', 'sufficient', 'active' => 'bg-[#EBF7EE] text-[#1B7A43]',
        'pending', 'low', 'cashier' => 'bg-[#FEF7EA] text-[#B57914]',
        'cancelled', 'critical', 'out_of_stock', 'inactive' => 'bg-[#FDECEB] text-[#C53030]',
        'administrator', 'admin' => 'bg-secondary-container/40 text-on-secondary-container',
        'placeholder' => 'bg-secondary-fixed/60 text-on-secondary-fixed-variant',
        default => 'bg-surface-container-low text-outline',
    };

    $displayLabel = $label ?? match ($normalized) {
        'out_of_stock' => 'Out of Stock',
        'critical' => 'Critical',
        'low' => 'Restock Soon',
        'sufficient' => 'Sufficient',
        default => ucfirst(str_replace('_', ' ', $normalized)),
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 rounded-[6px] px-2 py-0.5 text-[11px] font-semibold leading-4 {$styles}"]) }}>
    {{ $displayLabel }}
</span>
