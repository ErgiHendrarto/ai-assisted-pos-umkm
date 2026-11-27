@extends('layouts.app', [
    'pageTitle' => 'Products',
    'breadcrumb' => 'Products',
])

@section('title', 'Products')

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

    <div class="rounded-xl bg-surface-container-lowest border border-divider/80 shadow-[0_1px_3px_0_rgba(37,43,54,0.04)]">
        <div class="p-5 border-b border-divider flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <form method="GET" action="{{ route('products.index') }}" class="flex flex-wrap items-center gap-2.5 flex-1">
                <div class="relative flex-1 min-w-[200px] max-w-xs">
                    <span class="material-symbols-outlined text-[18px] text-outline absolute left-3 top-1/2 -translate-y-1/2">search</span>
                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Search name or SKU..."
                        class="w-full rounded-lg border border-divider bg-white pl-9 pr-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/15"
                    >
                </div>

                <select name="category_id" onchange="this.form.submit()" class="rounded-lg border border-divider bg-white px-3 py-2 text-sm">
                    <option value="">All Categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) $categoryId === (string) $category->id)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>

                <select name="stock_status" onchange="this.form.submit()" class="rounded-lg border border-divider bg-white px-3 py-2 text-sm">
                    <option value="all" @selected($stockStatus === 'all')>All Stock Levels</option>
                    <option value="in_stock" @selected($stockStatus === 'in_stock')>In Stock</option>
                    <option value="low" @selected($stockStatus === 'low')>Low Stock</option>
                    <option value="out_of_stock" @selected($stockStatus === 'out_of_stock')>Out of Stock</option>
                </select>

                <select name="active" onchange="this.form.submit()" class="rounded-lg border border-divider bg-white px-3 py-2 text-sm">
                    <option value="all" @selected($activeFilter === 'all')>All Status</option>
                    <option value="active" @selected($activeFilter === 'active')>Active</option>
                    <option value="inactive" @selected($activeFilter === 'inactive')>Inactive</option>
                </select>

                <x-button type="submit" variant="secondary">Filter</x-button>
            </form>

            <div class="flex items-center gap-2.5">
                <x-button variant="secondary" :href="route('inventory.index')">
                    <span class="material-symbols-outlined text-[18px]">swap_vert</span>
                    <span>Stock Movements</span>
                </x-button>
                <x-button variant="primary" :href="route('products.create')">
                    <span class="material-symbols-outlined text-[18px]">add</span>
                    <span>Add Product</span>
                </x-button>
            </div>
        </div>

        <div class="p-5">
            @if ($products->isEmpty())
                <x-empty-state
                    title="No Products Found"
                    description="Try adjusting your search or filter, or add a new retail product."
                />
            @else
                <x-table :headers="['Product', 'SKU', 'Category', 'Cost / Sell Price', 'Profit', 'Stock', 'Status', 'Actions']">
                    @foreach ($products as $product)
                        <tr class="hover:bg-surface-container-low/40 transition-colors">
                            <td class="py-3 px-3">
                                <div class="flex items-center gap-3">
                                    @if ($product->image_path)
                                        <img src="{{ asset('storage/' . $product->image_path) }}" alt="{{ $product->product_name }}" class="h-9 w-9 rounded-lg object-cover border border-divider shrink-0">
                                    @else
                                        <div class="h-9 w-9 rounded-lg bg-surface-container-low text-primary flex items-center justify-center shrink-0">
                                            <span class="material-symbols-outlined text-[18px]">inventory_2</span>
                                        </div>
                                    @endif
                                    <div>
                                        <a href="{{ route('products.show', $product) }}" class="font-semibold text-primary hover:underline">
                                            {{ $product->product_name }}
                                        </a>
                                        @if ($product->units_count ?? $product->units->count())
                                            <span class="block text-[10px] text-secondary font-medium">+{{ $product->units->count() }} extra unit(s)</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-3 font-mono text-xs text-on-surface-variant">{{ $product->product_code }}</td>
                            <td class="py-3 px-3 text-on-surface-variant">{{ $product->category?->name ?? '—' }}</td>
                            <td class="py-3 px-3 tabular-nums">
                                <span class="block font-semibold text-on-surface">{{ \App\Support\Format::rupiah($product->price) }}</span>
                                <span class="block text-[11px] text-outline">Cost: {{ \App\Support\Format::rupiah($product->cost_price) }}</span>
                            </td>
                            <td class="py-3 px-3 tabular-nums">
                                <span class="font-semibold {{ $product->baseProfit() >= 0 ? 'text-emerald-700' : 'text-red-600' }}">
                                    {{ \App\Support\Format::rupiah($product->baseProfit()) }}
                                </span>
                                <span class="block text-[10px] text-outline">{{ $product->profitMarginPercent() }}%</span>
                            </td>
                            <td class="py-3 px-3 tabular-nums">
                                <span class="font-semibold {{ (float) $product->stock <= 0 ? 'text-[#C53030]' : ($product->isLowStock() ? 'text-[#B57914]' : 'text-on-surface') }}">
                                    {{ \App\Support\Format::quantity($product->stock) }}
                                </span>
                                <span class="text-xs text-outline">{{ $product->unit }}</span>
                            </td>
                            <td class="py-3 px-3">
                                <div class="flex flex-col items-start gap-1">
                                    <x-status-badge :status="$product->is_active ? 'active' : 'inactive'" />
                                    @if ($product->is_active && $product->isLowStock())
                                        <x-status-badge :status="$product->stockStatus()" />
                                    @endif
                                </div>
                            </td>
                            <td class="py-3 px-3">
                                <div class="flex items-center gap-1.5">
                                    <x-button size="sm" variant="secondary" :href="route('products.show', $product)">Detail</x-button>
                                    <x-button size="sm" variant="secondary" :href="route('products.edit', $product)">Edit</x-button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-table>

                <div class="mt-4">
                    {{ $products->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
