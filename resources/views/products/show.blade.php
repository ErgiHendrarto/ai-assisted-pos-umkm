@extends('layouts.app', [
    'pageTitle' => $product->product_name,
    'breadcrumb' => 'Products / ' . $product->product_code,
])

@section('title', $product->product_name)

@section('content')
<div class="space-y-6">
    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 flex items-center gap-2.5">
            <span class="material-symbols-outlined text-[18px] text-emerald-600">check_circle</span>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc list-inside space-y-0.5 text-xs">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Product Info -->
        <div class="lg:col-span-2 rounded-xl bg-surface-container-lowest border border-divider/80 shadow-[0_1px_3px_0_rgba(37,43,54,0.04)] p-6 space-y-5">
            <div class="flex flex-wrap items-start justify-between gap-4 pb-5 border-b border-divider">
                <div class="flex items-start gap-4">
                    @if ($product->image_path)
                        <img src="{{ asset('storage/' . $product->image_path) }}" alt="{{ $product->product_name }}" class="h-16 w-16 rounded-xl object-cover border border-divider">
                    @else
                        <div class="h-16 w-16 rounded-xl bg-surface-container-low text-primary flex items-center justify-center">
                            <span class="material-symbols-outlined text-[28px]">inventory_2</span>
                        </div>
                    @endif
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-mono text-xs font-semibold text-outline bg-surface-container-low px-2 py-0.5 rounded">{{ $product->product_code }}</span>
                            <x-status-badge :status="$product->is_active ? 'active' : 'inactive'" />
                            <x-status-badge :status="$product->stockStatus()" />
                        </div>
                        <h2 class="mt-2 text-xl font-bold text-primary">{{ $product->product_name }}</h2>
                        <p class="text-xs text-on-surface-variant mt-0.5">{{ $product->category?->name ?? 'Uncategorized' }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <x-button size="sm" variant="secondary" :href="route('products.edit', $product)">Edit</x-button>
                    <form method="POST" action="{{ route('products.destroy', $product) }}" onsubmit="return confirm('Arsipkan produk ini?');">
                        @csrf
                        @method('DELETE')
                        <x-button type="submit" size="sm" variant="danger">Archive</x-button>
                    </form>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="rounded-lg bg-surface-container-low/60 p-3.5">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-outline">Cost / Sell Price</span>
                    <p class="mt-1 text-base font-bold text-primary tabular-nums">{{ \App\Support\Format::rupiah($product->price) }}</p>
                    <p class="text-[11px] text-outline tabular-nums">Cost: {{ \App\Support\Format::rupiah($product->cost_price) }}</p>
                </div>
                <div class="rounded-lg bg-surface-container-low/60 p-3.5">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-outline">Profit / {{ $product->unit }}</span>
                    <p class="mt-1 text-base font-bold {{ $product->baseProfit() >= 0 ? 'text-emerald-700' : 'text-red-600' }} tabular-nums">{{ \App\Support\Format::rupiah($product->baseProfit()) }}</p>
                    <p class="text-[11px] text-outline">{{ $product->profitMarginPercent() }}% margin</p>
                </div>
                <div class="rounded-lg bg-surface-container-low/60 p-3.5">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-outline">Current Stock</span>
                    <p class="mt-1 text-base font-bold text-on-surface tabular-nums">{{ \App\Support\Format::quantity($product->stock) }} {{ $product->unit }}</p>
                    <p class="text-[11px] text-outline tabular-nums">Min: {{ \App\Support\Format::quantity($product->minimum_stock) }} {{ $product->unit }}</p>
                </div>
                <div class="rounded-lg bg-surface-container-low/60 p-3.5">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-outline">Base Unit</span>
                    <p class="mt-1 text-base font-bold text-on-surface">{{ $product->unit }}</p>
                </div>
            </div>

            @if ($product->units->isNotEmpty())
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-outline mb-2">Additional Selling Units &amp; Profit</h3>
                    <x-table :headers="['Unit', 'Conversion', 'Unit Cost', 'Selling Price', 'Profit', 'Margin']">
                        @foreach ($product->units as $u)
                            <tr>
                                <td class="py-2 px-3 font-semibold text-primary">{{ $u->unit_name }}</td>
                                <td class="py-2 px-3 tabular-nums text-xs">1 {{ $u->unit_name }} = {{ \App\Support\Format::quantity($u->conversion_ratio) }} {{ $product->unit }}</td>
                                <td class="py-2 px-3 tabular-nums text-xs">{{ \App\Support\Format::rupiah($u->unitCost()) }}</td>
                                <td class="py-2 px-3 tabular-nums font-semibold">{{ \App\Support\Format::rupiah($u->selling_price) }}</td>
                                <td class="py-2 px-3 tabular-nums font-semibold {{ $u->unitProfit() >= 0 ? 'text-emerald-700' : 'text-red-600' }}">{{ \App\Support\Format::rupiah($u->unitProfit()) }}</td>
                                <td class="py-2 px-3 tabular-nums text-xs">{{ $u->profitMarginPercent() }}%</td>
                            </tr>
                        @endforeach
                    </x-table>
                </div>
            @endif

            @if ($product->description)
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-outline">Description</h3>
                    <p class="mt-1 text-sm text-on-surface-variant">{{ $product->description }}</p>
                </div>
            @endif
        </div>

        <!-- Stock Adjustment Form -->
        <div class="rounded-xl bg-surface-container-lowest border border-divider/80 shadow-[0_1px_3px_0_rgba(37,43,54,0.04)] p-6">
            <h3 class="text-base font-semibold text-primary">Stock Adjustment</h3>
            <p class="text-xs text-on-surface-variant mt-0.5">Every change creates an auditable stock movement record.</p>

            <form method="POST" action="{{ route('products.adjust-stock', $product) }}" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1.5">Movement Type</label>
                    <select name="movement_type" class="w-full rounded-lg border border-divider bg-white px-3 py-2 text-sm focus:border-primary focus:outline-none">
                        <option value="stock_in">Stock In (+ Tambah Stok)</option>
                        <option value="stock_out">Stock Out (- Kurangi Stok)</option>
                        <option value="adjustment">Stock Opname (Set Stok Aktual)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1.5">Quantity / Actual Stock ({{ $product->unit }})</label>
                    <input type="number" name="quantity" step="0.001" min="0" required class="w-full rounded-lg border border-divider px-3 py-2 text-sm tabular-nums focus:border-primary focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1.5">Reason / Note</label>
                    <textarea name="note" rows="2" required placeholder="e.g. Restock supplier pagi, barang rusak..." class="w-full rounded-lg border border-divider px-3 py-2 text-sm focus:border-primary focus:outline-none"></textarea>
                </div>
                <x-button type="submit" variant="primary" class="w-full">Apply Stock Update</x-button>
            </form>
        </div>
    </div>

    <!-- Stock Movement History -->
    <div class="rounded-xl bg-surface-container-lowest border border-divider/80 shadow-[0_1px_3px_0_rgba(37,43,54,0.04)] p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-primary">Stock Movement History</h3>
            <x-button size="sm" variant="secondary" :href="route('products.index')">Back to Products</x-button>
        </div>

        @if ($movements->isEmpty())
            <x-empty-state title="No Stock Movements Yet" description="Stock changes for this product will appear here." />
        @else
            <x-table :headers="['Date', 'Type', 'Qty', 'Before', 'After', 'User', 'Transaction', 'Note']">
                @foreach ($movements as $movement)
                    <tr class="hover:bg-surface-container-low/40">
                        <td class="py-2.5 px-3 text-xs text-on-surface-variant whitespace-nowrap">{{ $movement->created_at?->format('d M Y H:i') }}</td>
                        <td class="py-2.5 px-3"><x-status-badge :status="$movement->movement_type->value" /></td>
                        <td class="py-2.5 px-3 tabular-nums font-semibold">{{ \App\Support\Format::quantity($movement->quantity) }}</td>
                        <td class="py-2.5 px-3 tabular-nums text-on-surface-variant">{{ \App\Support\Format::quantity($movement->stock_before) }}</td>
                        <td class="py-2.5 px-3 tabular-nums font-semibold text-primary">{{ \App\Support\Format::quantity($movement->stock_after) }}</td>
                        <td class="py-2.5 px-3 text-xs">{{ $movement->user?->name ?? 'System' }}</td>
                        <td class="py-2.5 px-3 font-mono text-xs">{{ $movement->transaction?->transaction_code ?? '—' }}</td>
                        <td class="py-2.5 px-3 text-xs text-on-surface-variant">{{ $movement->note ?: '—' }}</td>
                    </tr>
                @endforeach
            </x-table>

            <div class="mt-4">
                {{ $movements->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
