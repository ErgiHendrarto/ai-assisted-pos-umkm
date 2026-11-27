@extends('layouts.app', [
    'pageTitle' => 'Inventory & Stock Movements',
    'breadcrumb' => 'Inventory',
])

@section('title', 'Inventory & Stock Movements')

@section('content')
<div class="space-y-6">
    <!-- Stock Overview KPIs -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <x-kpi-card title="Active Products" :value="$totalProducts" subtitle="Total active SKUs in catalog" tone="primary">
            <x-slot:icon><span class="material-symbols-outlined text-[20px]">inventory_2</span></x-slot:icon>
        </x-kpi-card>
        <x-kpi-card title="Low Stock Alerts" :value="$lowStockCount" subtitle="Above 0 but at/below minimum" tone="amber">
            <x-slot:icon><span class="material-symbols-outlined text-[20px]">warning</span></x-slot:icon>
        </x-kpi-card>
        <x-kpi-card title="Out of Stock" :value="$outOfStockCount" subtitle="Unavailable for sale in POS" tone="error">
            <x-slot:icon><span class="material-symbols-outlined text-[20px]">error</span></x-slot:icon>
        </x-kpi-card>
    </div>

    @if ($lowStockProducts->isNotEmpty())
        <div class="rounded-xl bg-surface-container-lowest border border-divider/80 shadow-[0_1px_3px_0_rgba(37,43,54,0.04)] p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-primary">Products Needing Restock</h3>
                <x-button size="sm" variant="secondary" :href="route('products.index', ['stock_status' => 'low'])">View All</x-button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                @foreach ($lowStockProducts as $item)
                    <a href="{{ route('products.show', $item) }}" class="rounded-lg border border-divider p-3 hover:border-primary/40 hover:bg-surface-container-low/40 transition-colors">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-semibold text-primary truncate">{{ $item->product_name }}</span>
                            <x-status-badge :status="$item->stockStatus()" />
                        </div>
                        <p class="mt-1.5 text-xs text-on-surface-variant tabular-nums">
                            Stock: <strong class="text-on-surface">{{ \App\Support\Format::quantity($item->stock) }}</strong> / Min {{ \App\Support\Format::quantity($item->minimum_stock) }} {{ $item->unit }}
                        </p>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Movement History Table -->
    <div class="rounded-xl bg-surface-container-lowest border border-divider/80 shadow-[0_1px_3px_0_rgba(37,43,54,0.04)]">
        <div class="p-5 border-b border-divider flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="text-base font-semibold text-primary">Stock Movement Audit Log</h2>
            <form method="GET" action="{{ route('inventory.index') }}" class="flex flex-wrap items-center gap-2.5">
                <input
                    type="text"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Product name or SKU..."
                    class="rounded-lg border border-divider bg-white px-3 py-2 text-sm focus:border-primary focus:outline-none"
                >
                <select name="movement_type" onchange="this.form.submit()" class="rounded-lg border border-divider bg-white px-3 py-2 text-sm">
                    <option value="all" @selected($movementType === 'all')>All Movements</option>
                    @foreach ($movementTypes as $type)
                        <option value="{{ $type->value }}" @selected($movementType === $type->value)>
                            {{ ucfirst(str_replace('_', ' ', $type->value)) }}
                        </option>
                    @endforeach
                </select>
                <x-button type="submit" variant="secondary">Filter</x-button>
            </form>
        </div>

        <div class="p-5">
            @if ($movements->isEmpty())
                <x-empty-state title="No Stock Movements" description="Inventory adjustments and sales deductions will be logged here." />
            @else
                <x-table :headers="['Date', 'Product', 'Type', 'Qty', 'Before', 'After', 'User', 'Transaction', 'Note']">
                    @foreach ($movements as $movement)
                        <tr class="hover:bg-surface-container-low/40">
                            <td class="py-2.5 px-3 text-xs text-on-surface-variant whitespace-nowrap">{{ $movement->created_at?->format('d M Y H:i') }}</td>
                            <td class="py-2.5 px-3 font-medium text-primary">
                                @if ($movement->product)
                                    <a href="{{ route('products.show', $movement->product) }}" class="hover:underline">{{ $movement->product->product_name }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="py-2.5 px-3"><x-status-badge :status="$movement->movement_type->value" /></td>
                            <td class="py-2.5 px-3 tabular-nums font-semibold">{{ \App\Support\Format::quantity($movement->quantity) }}</td>
                            <td class="py-2.5 px-3 tabular-nums text-on-surface-variant">{{ \App\Support\Format::quantity($movement->stock_before) }}</td>
                            <td class="py-2.5 px-3 tabular-nums font-semibold text-primary">{{ \App\Support\Format::quantity($movement->stock_after) }}</td>
                            <td class="py-2.5 px-3 text-xs">{{ $movement->user?->name ?? 'System' }}</td>
                            <td class="py-2.5 px-3 font-mono text-xs">
                                @if ($movement->transaction)
                                    <a href="{{ route('transactions.show', $movement->transaction) }}" class="text-secondary hover:underline">{{ $movement->transaction->transaction_code }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="py-2.5 px-3 text-xs text-on-surface-variant">{{ $movement->note ?: '—' }}</td>
                        </tr>
                    @endforeach
                </x-table>
                <div class="mt-4">{{ $movements->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
