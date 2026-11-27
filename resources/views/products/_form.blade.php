@php
    $isEdit = isset($product);
    $existingUnits = old('product_units', isset($product)
        ? $product->units->map(fn ($u) => [
            'unit_name' => $u->unit_name,
            'conversion_ratio' => (float) $u->conversion_ratio,
            'selling_price' => (float) $u->selling_price,
        ])->values()->all()
        : []);
@endphp

<div
    x-data="{
        unitsList: @js($units),
        baseUnit: @js(old('unit', $product->unit ?? 'pcs')),
        costPrice: {{ (float) old('cost_price', isset($product) ? $product->cost_price : 0) }},
        sellingPrice: {{ (float) old('price', isset($product) ? $product->price : 0) }},
        extraUnits: @js($existingUnits),
        newUnitName: '',
        unitError: '',
        get baseProfit() {
            return (Number(this.sellingPrice) || 0) - (Number(this.costPrice) || 0);
        },
        get baseMargin() {
            const p = Number(this.sellingPrice) || 0;
            return p > 0 ? ((this.baseProfit / p) * 100).toFixed(1) : '0.0';
        },
        addExtraUnit() {
            this.extraUnits.push({ unit_name: this.unitsList[0] || 'pack', conversion_ratio: 1, selling_price: 0 });
        },
        removeExtraUnit(index) {
            this.extraUnits.splice(index, 1);
        },
        unitCost(ratio) {
            return (Number(this.costPrice) || 0) * (Number(ratio) || 0);
        },
        unitProfit(row) {
            return (Number(row.selling_price) || 0) - this.unitCost(row.conversion_ratio);
        },
        formatRp(val) {
            const sign = val < 0 ? '-' : '';
            return sign + 'Rp ' + Math.round(Math.abs(val)).toLocaleString('id-ID');
        },
        async createCustomUnit() {
            this.unitError = '';
            const name = this.newUnitName.trim().toLowerCase();
            if (!name) return;
            try {
                const res = await fetch(@js(route('units.store')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ name }),
                });
                const data = await res.json();
                if (!res.ok) {
                    this.unitError = data.message || 'Gagal menambah satuan.';
                    return;
                }
                if (!this.unitsList.includes(data.unit.name)) {
                    this.unitsList.push(data.unit.name);
                }
                this.baseUnit = data.unit.name;
                this.newUnitName = '';
            } catch (e) {
                this.unitError = 'Terjadi kesalahan jaringan.';
            }
        }
    }"
    class="space-y-5"
>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <label for="product_name" class="block text-xs font-semibold text-on-surface mb-1.5">Product Name</label>
            <input
                id="product_name"
                type="text"
                name="product_name"
                value="{{ old('product_name', $product->product_name ?? '') }}"
                required
                class="w-full rounded-lg border border-divider bg-white px-3.5 py-2.5 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/15"
            >
        </div>

        <div>
            <label for="product_code" class="block text-xs font-semibold text-on-surface mb-1.5">
                Product Code / SKU
                @if (! $isEdit)
                    <span class="font-normal text-outline">(Auto-generated)</span>
                @endif
            </label>
            <input
                id="product_code"
                type="text"
                name="product_code"
                value="{{ old('product_code', $product->product_code ?? ($suggestedSku ?? '')) }}"
                placeholder="Auto-generated if left blank"
                @if ($isEdit) required @endif
                class="w-full rounded-lg border border-divider bg-white px-3.5 py-2.5 text-sm font-mono uppercase focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/15"
            >
        </div>

        <div>
            <label for="category_id" class="block text-xs font-semibold text-on-surface mb-1.5">Category</label>
            <select
                id="category_id"
                name="category_id"
                required
                class="w-full rounded-lg border border-divider bg-white px-3.5 py-2.5 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/15"
            >
                <option value="">Select Category</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) old('category_id', $product->category_id ?? '') === (string) $category->id)>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="space-y-2">
            <label for="unit" class="block text-xs font-semibold text-on-surface">Base Unit (Stock Unit)</label>
            <select
                id="unit"
                name="unit"
                x-model="baseUnit"
                required
                class="w-full rounded-lg border border-divider bg-white px-3.5 py-2.5 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/15"
            >
                <template x-for="u in unitsList" :key="u">
                    <option :value="u" x-text="u" :selected="baseUnit === u"></option>
                </template>
            </select>
            <div class="flex items-center gap-1.5">
                <input
                    type="text"
                    x-model="newUnitName"
                    placeholder="Custom unit (e.g. slop, ball, stick)..."
                    class="flex-1 rounded-md border border-divider px-2.5 py-1.5 text-xs focus:border-primary focus:outline-none"
                >
                <button type="button" @click="createCustomUnit()" class="rounded-md bg-surface-container-low px-2.5 py-1.5 text-xs font-semibold text-primary hover:bg-surface-container cursor-pointer">
                    + Add Unit
                </button>
            </div>
            <p x-show="unitError" x-text="unitError" class="text-[11px] text-red-600"></p>
        </div>

        <div>
            <label for="cost_price" class="block text-xs font-semibold text-on-surface mb-1.5">Cost / Purchase Price (Rp per <span x-text="baseUnit"></span>)</label>
            <input
                id="cost_price"
                type="number"
                name="cost_price"
                step="1"
                min="0"
                x-model.number="costPrice"
                class="w-full rounded-lg border border-divider bg-white px-3.5 py-2.5 text-sm tabular-nums focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/15"
            >
        </div>

        <div>
            <label for="price" class="block text-xs font-semibold text-on-surface mb-1.5">Selling Price (Rp per <span x-text="baseUnit"></span>)</label>
            <input
                id="price"
                type="number"
                name="price"
                step="1"
                min="0"
                x-model.number="sellingPrice"
                required
                class="w-full rounded-lg border border-divider bg-white px-3.5 py-2.5 text-sm tabular-nums focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/15"
            >
            <p class="mt-1 text-[11px] font-medium" :class="baseProfit >= 0 ? 'text-emerald-700' : 'text-red-600'">
                Estimated Profit: <strong x-text="formatRp(baseProfit)"></strong> / <span x-text="baseUnit"></span>
                (<span x-text="baseMargin"></span>% margin)
            </p>
        </div>

        @if (! $isEdit)
            <div>
                <label for="stock" class="block text-xs font-semibold text-on-surface mb-1.5">Initial Stock (<span x-text="baseUnit"></span>)</label>
                <input
                    id="stock"
                    type="number"
                    name="stock"
                    step="0.001"
                    min="0"
                    value="{{ old('stock', 0) }}"
                    required
                    class="w-full rounded-lg border border-divider bg-white px-3.5 py-2.5 text-sm tabular-nums focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/15"
                >
            </div>
        @endif

        <div>
            <label for="minimum_stock" class="block text-xs font-semibold text-on-surface mb-1.5">Minimum Stock Alert (<span x-text="baseUnit"></span>)</label>
            <input
                id="minimum_stock"
                type="number"
                name="minimum_stock"
                step="0.001"
                min="0"
                value="{{ old('minimum_stock', isset($product) ? (float) $product->minimum_stock : 5) }}"
                required
                class="w-full rounded-lg border border-divider bg-white px-3.5 py-2.5 text-sm tabular-nums focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/15"
            >
        </div>

        <div>
            <label for="lead_time_days" class="block text-xs font-semibold text-on-surface mb-1.5">Supplier Lead Time (Days)</label>
            <input
                id="lead_time_days"
                type="number"
                name="lead_time_days"
                min="0"
                max="90"
                value="{{ old('lead_time_days', isset($product) ? (int) $product->lead_time_days : 2) }}"
                class="w-full rounded-lg border border-divider bg-white px-3.5 py-2.5 text-sm tabular-nums focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/15"
            >
        </div>

        <div>
            <label for="safety_stock" class="block text-xs font-semibold text-on-surface mb-1.5">Safety Stock (<span x-text="baseUnit"></span>)</label>
            <input
                id="safety_stock"
                type="number"
                name="safety_stock"
                step="0.001"
                min="0"
                value="{{ old('safety_stock', isset($product) ? (float) $product->safety_stock : 5) }}"
                class="w-full rounded-lg border border-divider bg-white px-3.5 py-2.5 text-sm tabular-nums focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/15"
            >
        </div>

        <div>
            <label for="image" class="block text-xs font-semibold text-on-surface mb-1.5">Product Image <span class="font-normal text-outline">(Optional, max 2MB)</span></label>
            <input
                id="image"
                type="file"
                name="image"
                accept="image/jpeg,image/png,image/webp"
                class="w-full rounded-lg border border-divider bg-white px-3 py-2 text-xs text-on-surface file:mr-3 file:rounded-md file:border-0 file:bg-surface-container-low file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary"
            >
            @if ($isEdit && $product->image_path)
                <label class="mt-2 inline-flex items-center gap-2 text-xs text-red-600 cursor-pointer">
                    <input type="checkbox" name="remove_image" value="1" class="rounded border-divider">
                    <span>Remove existing image</span>
                </label>
            @endif
        </div>
    </div>

    <!-- Additional Multi-Unit Conversions -->
    <div class="rounded-xl border border-divider/80 bg-surface-container-low/40 p-4 space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wider text-primary">Additional Selling Units &amp; Conversion Ratios</h3>
                <p class="text-[11px] text-on-surface-variant">
                    Example: If base unit is <strong>stick</strong> (16 sticks/pack), add <strong>pack</strong> with ratio <code>16</code>, <strong>slop</strong> with ratio <code>160</code>, or <strong>carton</strong> with ratio <code>1600</code>.
                </p>
            </div>
            <button type="button" @click="addExtraUnit()" class="rounded-lg bg-primary-container text-on-primary px-3 py-1.5 text-xs font-semibold hover:bg-primary cursor-pointer">
                + Add Selling Unit
            </button>
        </div>

        <template x-if="extraUnits.length === 0">
            <p class="text-xs text-outline italic">No additional selling units configured. Product will be sold using its base unit (<span x-text="baseUnit"></span>).</p>
        </template>

        <div class="space-y-2.5">
            <template x-for="(row, idx) in extraUnits" :key="idx">
                <div class="grid grid-cols-1 sm:grid-cols-12 gap-2.5 items-end rounded-lg border border-divider bg-white p-3">
                    <div class="sm:col-span-3">
                        <label class="block text-[11px] font-semibold text-on-surface mb-1">Selling Unit</label>
                        <select :name="'product_units[' + idx + '][unit_name]'" x-model="row.unit_name" class="w-full rounded-md border border-divider px-2.5 py-1.5 text-xs">
                            <template x-for="u in unitsList" :key="u">
                                <option :value="u" x-text="u" :selected="row.unit_name === u"></option>
                            </template>
                        </select>
                    </div>
                    <div class="sm:col-span-3">
                        <label class="block text-[11px] font-semibold text-on-surface mb-1">Contains (<span x-text="baseUnit"></span>)</label>
                        <input type="number" step="0.001" min="0.001" :name="'product_units[' + idx + '][conversion_ratio]'" x-model.number="row.conversion_ratio" required class="w-full rounded-md border border-divider px-2.5 py-1.5 text-xs tabular-nums">
                    </div>
                    <div class="sm:col-span-3">
                        <label class="block text-[11px] font-semibold text-on-surface mb-1">Selling Price (Rp)</label>
                        <input type="number" step="1" min="0" :name="'product_units[' + idx + '][selling_price]'" x-model.number="row.selling_price" required class="w-full rounded-md border border-divider px-2.5 py-1.5 text-xs tabular-nums">
                    </div>
                    <div class="sm:col-span-2 text-[11px] leading-tight">
                        <span class="text-outline block">Cost: <strong x-text="formatRp(unitCost(row.conversion_ratio))"></strong></span>
                        <span class="block" :class="unitProfit(row) >= 0 ? 'text-emerald-700' : 'text-red-600'">
                            Profit: <strong x-text="formatRp(unitProfit(row))"></strong>
                        </span>
                    </div>
                    <div class="sm:col-span-1 text-right">
                        <button type="button" @click="removeExtraUnit(idx)" class="text-red-600 hover:text-red-800 p-1 cursor-pointer" title="Remove unit">
                            <span class="material-symbols-outlined text-[18px]">delete</span>
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    @if (isset($allProducts) && $allProducts->isNotEmpty())
        @php
            $selectedAlts = old('alternative_product_ids', isset($product) ? $product->alternatives->pluck('id')->all() : []);
        @endphp
        <div>
            <label for="alternative_product_ids" class="block text-xs font-semibold text-on-surface mb-1.5">
                Manual Alternative Products When Out of Stock <span class="font-normal text-outline">(Optional, hold Ctrl/Cmd to multi-select)</span>
            </label>
            <select
                id="alternative_product_ids"
                name="alternative_product_ids[]"
                multiple
                size="4"
                class="w-full rounded-lg border border-divider bg-white px-3 py-2 text-xs focus:border-primary focus:outline-none"
            >
                @foreach ($allProducts as $alt)
                    <option value="{{ $alt->id }}" @selected(in_array($alt->id, $selectedAlts))>
                        {{ $alt->product_code }} &ndash; {{ $alt->product_name }}
                    </option>
                @endforeach
            </select>
        </div>
    @endif

    <div>
        <label for="description" class="block text-xs font-semibold text-on-surface mb-1.5">Description <span class="font-normal text-outline">(Optional)</span></label>
        <textarea
            id="description"
            name="description"
            rows="3"
            class="w-full rounded-lg border border-divider bg-white px-3.5 py-2.5 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/15"
        >{{ old('description', $product->description ?? '') }}</textarea>
    </div>

    <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active ?? true)) class="rounded border-divider text-primary">
        <span class="font-medium text-on-surface">Active in POS catalog</span>
    </label>
</div>
