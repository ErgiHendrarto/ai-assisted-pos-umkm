@extends('layouts.app', [
    'pageTitle' => 'Checkout Review & Confirmation',
    'breadcrumb' => 'POS / Checkout Review',
])

@section('title', 'Checkout Confirmation')

@section('content')
<div
    x-data="{
        subtotal: {{ (float) $cart->subtotal }},
        discount: {{ (float) $discount }},
        paymentMethod: @js($paymentMethod),
        customerId: @js((string) ($customerId ?? '')),
        customersList: @js($customers),
        staffList: @js($staffMembers),
        takenByStaffId: @js((string) ($takenByStaffId ?? '')),
        staffOpen: false,
        staffSearch: '',
        amountPaid: {{ (float) $amountPaid }},
        includePreviousDebt: @js((bool) $includePreviousDebt),
        get selectedStaff() {
            return this.staffList.find(s => String(s.id) === String(this.takenByStaffId)) || null;
        },
        get filteredStaff() {
            const q = this.staffSearch.trim().toLowerCase();
            if (!q) return this.staffList;
            return this.staffList.filter(s =>
                s.name.toLowerCase().includes(q) || s.staff_code.toLowerCase().includes(q)
            );
        },
        get netTotal() {
            return Math.max(0, this.subtotal - (Number(this.discount) || 0));
        },
        get selectedCustomer() {
            return this.customersList.find(c => String(c.id) === String(this.customerId)) || null;
        },
        get previousDebt() {
            return this.selectedCustomer ? (Number(this.selectedCustomer.outstanding_debt) || 0) : 0;
        },
        get includedDebt() {
            return (this.includePreviousDebt && this.previousDebt > 0) ? this.previousDebt : 0;
        },
        get grandTotal() {
            return this.netTotal + this.includedDebt;
        },
        get debtSettled() {
            return Math.min(Math.max(0, Number(this.amountPaid) || 0), this.includedDebt);
        },
        get change() {
            return Math.max(0, (Number(this.amountPaid) || 0) - this.grandTotal);
        },
        get outstandingDebt() {
            return Math.max(0, this.grandTotal - (Number(this.amountPaid) || 0));
        },
        formatRp(val) {
            return 'Rp ' + Math.round(val).toLocaleString('id-ID');
        }
    }"
    class="max-w-5xl space-y-6"
>
    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 flex items-center gap-2.5">
            <span class="material-symbols-outlined text-[18px] text-emerald-600">check_circle</span>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc list-inside text-xs space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <!-- Left: Editable Order Summary (7 cols) -->
        <div class="lg:col-span-7 rounded-xl bg-surface-container-lowest border border-divider/80 shadow-[0_1px_3px_0_rgba(37,43,54,0.04)] p-6 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-divider">
                <div>
                    <h2 class="text-base font-semibold text-primary">Review Customer Order</h2>
                    <p class="text-xs text-on-surface-variant mt-0.5">
                        Edit quantities, remove items, or return to POS to add more products before finalizing.
                    </p>
                </div>
                <x-button size="sm" variant="secondary" :href="route('pos.index')">
                    <span class="material-symbols-outlined text-[16px]">add_shopping_cart</span>
                    <span>Add / Edit Products</span>
                </x-button>
            </div>

            <div class="divide-y divide-divider/70">
                @foreach ($cart->items as $item)
                    @php
                        $unitLabel = $item->unit_name ?: ($item->product?->unit ?? 'pcs');
                        $ratio = max(0.001, (float) ($item->conversion_ratio ?: 1));
                        $maxQtyInUnit = floor(((float) ($item->product?->stock ?? 0)) / $ratio * 1000) / 1000;
                    @endphp
                    <div class="py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-on-surface">{{ $item->product?->product_name }}</p>
                            <p class="text-xs text-on-surface-variant tabular-nums">
                                {{ \App\Support\Format::rupiah($item->price_snapshot) }} / {{ $unitLabel }}
                                @if ($ratio > 1)
                                    (1 {{ $unitLabel }} = {{ \App\Support\Format::quantity($ratio) }} {{ $item->product?->unit }})
                                @endif
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            <form method="POST" action="{{ route('pos.cart.update', $item) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="redirect_to" value="checkout">
                                <input type="hidden" name="quantity" value="{{ max(0, (float) $item->quantity - 1) }}">
                                <button type="submit" class="h-8 w-8 rounded-md border border-divider bg-white text-xs font-bold hover:bg-surface-container-low cursor-pointer">-</button>
                            </form>

                            <form method="POST" action="{{ route('pos.cart.update', $item) }}" class="flex items-center gap-1">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="redirect_to" value="checkout">
                                <input
                                    type="number"
                                    name="quantity"
                                    step="0.001"
                                    min="0.001"
                                    max="{{ $maxQtyInUnit }}"
                                    value="{{ (float) $item->quantity }}"
                                    class="w-16 rounded-md border border-divider px-2 py-1.5 text-center text-xs tabular-nums"
                                >
                                <button type="submit" class="rounded-md bg-surface-container-low px-2.5 py-1.5 text-xs font-semibold text-primary hover:bg-surface-container cursor-pointer">
                                    Update
                                </button>
                            </form>

                            <form method="POST" action="{{ route('pos.cart.update', $item) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="redirect_to" value="checkout">
                                <input type="hidden" name="quantity" value="{{ (float) $item->quantity + 1 }}">
                                <button type="submit" class="h-8 w-8 rounded-md border border-divider bg-white text-xs font-bold hover:bg-surface-container-low cursor-pointer">+</button>
                            </form>

                            <span class="w-24 text-right text-sm font-bold text-primary tabular-nums">
                                {{ \App\Support\Format::rupiah($item->subtotal) }}
                            </span>

                            <form method="POST" action="{{ route('pos.cart.remove', $item) }}">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="redirect_to" value="checkout">
                                <button type="submit" class="text-red-600 hover:text-red-800 p-1 cursor-pointer" title="Remove item">
                                    <span class="material-symbols-outlined text-[18px]">delete</span>
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Right: Payment & Final Confirmation (5 cols) -->
        <div class="lg:col-span-5 rounded-xl bg-surface-container-lowest border border-divider/80 shadow-[0_1px_3px_0_rgba(37,43,54,0.04)] p-6 space-y-5">
            <h2 class="text-base font-semibold text-primary border-b border-divider pb-3">Final Payment Confirmation</h2>

            <form method="POST" action="{{ route('pos.checkout') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="include_previous_debt" :value="includePreviousDebt ? 1 : 0">

                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1">Customer <span class="font-normal text-outline">(Required if remaining debt &gt; Rp 0)</span></label>
                    <select name="customer_id" x-model="customerId" @change="includePreviousDebt = false" class="w-full rounded-lg border border-divider bg-white px-3 py-2 text-xs">
                        <option value="">Walk-in / General Customer</option>
                        <template x-for="c in customersList" :key="c.id">
                            <option :value="String(c.id)" x-text="c.customer_code + ' – ' + c.name + (c.outstanding_debt > 0 ? ' [Debt: ' + formatRp(c.outstanding_debt) + ']' : '')" :selected="String(customerId) === String(c.id)"></option>
                        </template>
                    </select>
                    <template x-if="selectedCustomer">
                        <div class="mt-2 rounded-lg px-3 py-2 text-xs border space-y-2" :class="previousDebt > 0 ? 'bg-amber-50 border-amber-200 text-amber-900' : 'bg-surface-container-low/50 border-divider text-on-surface-variant'">
                            <div class="flex justify-between">
                                <span><strong x-text="selectedCustomer.customer_code"></strong> &ndash; <span x-text="selectedCustomer.name"></span></span>
                                <span class="font-semibold tabular-nums" x-text="'Outstanding Debt: ' + formatRp(previousDebt)"></span>
                            </div>
                            <template x-if="previousDebt > 0">
                                <div class="flex flex-wrap items-center gap-2 pt-1 border-t border-amber-200/70">
                                    <template x-if="!includePreviousDebt">
                                        <button type="button" @click="includePreviousDebt = true" class="rounded-md bg-primary-container text-on-primary px-2.5 py-1 text-[11px] font-semibold hover:bg-primary cursor-pointer">
                                            Pay Now / Bayar Sekarang
                                        </button>
                                    </template>
                                    <template x-if="includePreviousDebt">
                                        <button type="button" @click="includePreviousDebt = false" class="rounded-md border border-divider bg-white text-red-700 px-2.5 py-1 text-[11px] font-semibold hover:bg-red-50 cursor-pointer">
                                            Remove Debt Payment
                                        </button>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <!-- Optional Taken By Staff Searchable Dropdown -->
                <div class="relative" @click.away="staffOpen = false">
                    <label class="block text-xs font-semibold text-on-surface mb-1">Taken By (Optional)</label>
                    <input type="hidden" name="taken_by_staff_id" :value="takenByStaffId">
                    <div class="flex items-center gap-1.5">
                        <button
                            type="button"
                            @click="staffOpen = !staffOpen"
                            class="flex-1 flex items-center justify-between rounded-lg border border-divider bg-white px-3 py-2 text-xs text-left hover:border-primary/50 cursor-pointer"
                        >
                            <span x-text="selectedStaff ? (selectedStaff.name + ' — ' + selectedStaff.staff_code) : 'Not Assigned (Leave Blank)'" :class="selectedStaff ? 'font-semibold text-on-surface' : 'text-outline'"></span>
                            <span class="material-symbols-outlined text-[18px] text-outline">expand_more</span>
                        </button>
                        <button
                            type="button"
                            x-show="takenByStaffId !== ''"
                            @click="takenByStaffId = ''; staffSearch = ''; staffOpen = false"
                            class="rounded-lg border border-divider bg-white px-2.5 py-2 text-xs font-semibold text-red-600 hover:bg-red-50 cursor-pointer"
                            title="Clear staff selection"
                        >
                            Clear
                        </button>
                    </div>
                    <p class="mt-1 text-[11px] text-outline">Select the staff member who picked or prepared the customer's products. This field is optional.</p>

                    <div x-show="staffOpen" x-cloak class="absolute z-30 mt-1 w-full rounded-lg border border-divider bg-white shadow-lg overflow-hidden">
                        <div class="p-2 border-b border-divider">
                            <input
                                type="text"
                                x-model="staffSearch"
                                placeholder="Search by name or Staff ID..."
                                class="w-full rounded-md border border-divider px-2.5 py-1.5 text-xs focus:border-primary focus:outline-none"
                            >
                        </div>
                        <div class="max-h-44 overflow-y-auto divide-y divide-divider/50">
                            <button
                                type="button"
                                @click="takenByStaffId = ''; staffOpen = false"
                                class="w-full px-3 py-2 text-left text-xs text-outline hover:bg-surface-container-low cursor-pointer"
                            >
                                None / Not Assigned
                            </button>
                            <template x-if="filteredStaff.length === 0">
                                <p class="px-3 py-2 text-xs text-outline">No matching active staff.</p>
                            </template>
                            <template x-for="s in filteredStaff" :key="s.id">
                                <button
                                    type="button"
                                    @click="takenByStaffId = String(s.id); staffOpen = false"
                                    class="w-full px-3 py-2 text-left text-xs hover:bg-surface-container-low flex items-center justify-between cursor-pointer"
                                    :class="String(takenByStaffId) === String(s.id) ? 'bg-primary-container/10 font-semibold text-primary' : 'text-on-surface'"
                                >
                                    <span x-text="s.name"></span>
                                    <span class="font-mono text-[11px] text-outline" x-text="s.staff_code"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="space-y-2 text-sm">
                    <div class="flex justify-between text-on-surface-variant">
                        <span>Current Purchase Subtotal</span>
                        <span class="tabular-nums font-medium">{{ \App\Support\Format::rupiah($cart->subtotal) }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-2 text-on-surface-variant">
                        <label for="discount_amount" class="text-xs">Discount (Rp)</label>
                        <input
                            id="discount_amount"
                            type="number"
                            name="discount_amount"
                            min="0"
                            step="1"
                            x-model.number="discount"
                            class="w-32 rounded-md border border-divider px-2.5 py-1 text-right text-xs tabular-nums"
                        >
                    </div>
                    <div class="flex justify-between text-sm font-semibold text-on-surface pt-1 border-t border-divider/60">
                        <span>Current Purchase Total</span>
                        <span class="tabular-nums" x-text="formatRp(netTotal)"></span>
                    </div>
                    <div class="flex justify-between text-xs font-semibold" :class="includedDebt > 0 ? 'text-amber-800' : 'text-on-surface-variant'">
                        <span>Previous Debt Included</span>
                        <span class="tabular-nums" x-text="formatRp(includedDebt)"></span>
                    </div>
                    <div class="flex justify-between text-lg font-bold text-primary pt-2 border-t border-divider">
                        <span>Grand Total</span>
                        <span class="tabular-nums" x-text="formatRp(grandTotal)"></span>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1.5">Payment Method</label>
                    <div class="grid grid-cols-3 gap-2">
                        <label class="cursor-pointer">
                            <input type="radio" name="payment_method" value="cash" x-model="paymentMethod" class="sr-only peer">
                            <div class="rounded-lg border border-divider py-2 text-center text-xs font-semibold peer-checked:border-primary peer-checked:bg-primary-container peer-checked:text-on-primary">Cash</div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="payment_method" value="qris" x-model="paymentMethod" class="sr-only peer">
                            <div class="rounded-lg border border-divider py-2 text-center text-xs font-semibold peer-checked:border-primary peer-checked:bg-primary-container peer-checked:text-on-primary">QRIS</div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="payment_method" value="other" x-model="paymentMethod" class="sr-only peer">
                            <div class="rounded-lg border border-divider py-2 text-center text-xs font-semibold peer-checked:border-primary peer-checked:bg-primary-container peer-checked:text-on-primary">Other</div>
                        </label>
                    </div>
                </div>

                <div class="space-y-2">
                    <label for="amount_paid" class="block text-xs font-semibold text-on-surface">Amount Paid (Cash Received / Rp)</label>
                    <input
                        id="amount_paid"
                        type="number"
                        name="amount_paid"
                        min="0"
                        step="1"
                        x-model.number="amountPaid"
                        class="w-full rounded-lg border border-divider px-3 py-2 text-sm font-semibold tabular-nums focus:border-primary focus:outline-none"
                    >
                </div>

                <div
                    class="rounded-lg px-3 py-2.5 text-xs font-semibold border space-y-1"
                    :class="outstandingDebt > 0 ? 'bg-amber-50 border-amber-200 text-amber-900' : 'bg-emerald-50 border-emerald-200 text-emerald-800'"
                >
                    <template x-if="includedDebt > 0">
                        <div class="flex justify-between items-center text-[11px]">
                            <span>Debt Settled from Previous Transactions:</span>
                            <span class="tabular-nums font-bold" x-text="formatRp(debtSettled)"></span>
                        </div>
                    </template>
                    <template x-if="outstandingDebt > 0">
                        <div>
                            <div class="flex justify-between items-center">
                                <span>Remaining Debt:</span>
                                <span class="tabular-nums font-bold text-red-700" x-text="formatRp(outstandingDebt)"></span>
                            </div>
                            <p x-show="!customerId" class="mt-1 text-[11px] text-red-700">
                                Please select a customer above to record this debt.
                            </p>
                        </div>
                    </template>
                    <template x-if="outstandingDebt === 0">
                        <div class="flex justify-between items-center">
                            <span>Change:</span>
                            <span class="tabular-nums font-bold" x-text="formatRp(change)"></span>
                        </div>
                    </template>
                </div>

                <div>
                    <label for="notes" class="block text-xs font-semibold text-on-surface mb-1">Notes</label>
                    <input
                        id="notes"
                        type="text"
                        name="notes"
                        value="{{ old('notes', $notes) }}"
                        placeholder="Optional transaction note..."
                        class="w-full rounded-lg border border-divider px-3 py-2 text-xs focus:border-primary focus:outline-none"
                    >
                </div>

                <div class="pt-2 space-y-2.5">
                    <x-button type="submit" variant="primary" size="lg" class="w-full">
                        <span class="material-symbols-outlined text-[18px]">check_circle</span>
                        <span>Confirm &amp; Finalize Transaction</span>
                    </x-button>

                    <x-button variant="secondary" :href="route('pos.index')" class="w-full">
                        <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                        <span>Back to Edit Order</span>
                    </x-button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
