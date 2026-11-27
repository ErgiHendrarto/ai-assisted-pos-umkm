@extends('layouts.app', [
    'pageTitle' => 'POS / Cashier Terminal',
    'breadcrumb' => 'POS / Transactions',
])

@section('title', 'POS Terminal')

@section('content')
<div
    x-data="{
        mobileCartOpen: false,
        pendingModalOpen: false,
        pendingList: @js($pendingTransactions),
        pendingBusy: false,
        pendingError: '',
        conflictModalOpen: false,
        conflictInfo: { active_items_count: 0, active_total: 0 },
        conflictTargetId: null,
        cancelModalOpen: false,
        cancelTarget: null,
        activeItemsCount: {{ $cart->items->count() }},
        formatPendingRp(val) {
            return 'Rp ' + Math.round(Number(val) || 0).toLocaleString('id-ID');
        },
        async openPendingModal() {
            this.pendingModalOpen = true;
            this.pendingError = '';
            try {
                const res = await fetch(@js(route('pos.pending.index')), { headers: { 'Accept': 'application/json' } });
                if (res.ok) {
                    const data = await res.json();
                    this.pendingList = data.pending_transactions || [];
                }
            } catch (e) {}
        },
        readActiveDraftFromDom() {
            const getVal = (sel) => document.querySelector(sel)?.value ?? '';
            const method = document.querySelector('input[name=payment_method]:checked')?.value || 'cash';
            return {
                customer_id: getVal('select[name=customer_id]') || null,
                discount_amount: Number(getVal('input[name=discount_amount]') || 0),
                payment_method: method,
                amount_paid: getVal('input[name=amount_paid]') !== '' ? Number(getVal('input[name=amount_paid]')) : null,
                include_previous_debt: getVal('input[name=include_previous_debt]') === '1',
                notes: getVal('input[name=notes]') || null,
            };
        },
        async resumePending(id, conflictAction = 'abort') {
            if (this.pendingBusy) return;
            this.pendingBusy = true;
            this.pendingError = '';
            try {
                const res = await fetch('/pos/pending/' + id + '/resume', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({
                        conflict_action: conflictAction,
                        current_draft: conflictAction === 'hold_current' ? this.readActiveDraftFromDom() : null,
                    }),
                });
                const data = await res.json();
                if (res.status === 409 && data.conflict) {
                    this.conflictTargetId = id;
                    this.conflictInfo = data;
                    this.conflictModalOpen = true;
                    return;
                }
                if (!res.ok) {
                    this.pendingError = data.message || 'Gagal melanjutkan transaksi pending.';
                    return;
                }
                window.location.href = data.redirect || @js(route('pos.index'));
            } catch (e) {
                this.pendingError = 'Terjadi kesalahan jaringan.';
            } finally {
                this.pendingBusy = false;
            }
        },
        promptCancelPending(item) {
            this.cancelTarget = item;
            this.pendingError = '';
            this.cancelModalOpen = true;
        },
        async confirmCancelPending() {
            if (!this.cancelTarget || this.pendingBusy) return;
            this.pendingBusy = true;
            this.pendingError = '';
            try {
                const res = await fetch('/pos/pending/' + this.cancelTarget.id, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                });
                const data = await res.json();
                if (!res.ok) {
                    this.pendingError = data.message || 'Gagal membatalkan transaksi pending.';
                    return;
                }
                this.pendingList = data.pending_transactions || [];
                this.cancelModalOpen = false;
                this.cancelTarget = null;
            } catch (e) {
                this.pendingError = 'Terjadi kesalahan jaringan.';
            } finally {
                this.pendingBusy = false;
            }
        }
    }"
    @pending-updated.window="pendingList = $event.detail"
    @keydown.escape.window="mobileCartOpen = false; pendingModalOpen = false; conflictModalOpen = false; cancelModalOpen = false"
    class="space-y-4 pb-24 lg:pb-0"
>
    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 flex items-center gap-2.5">
            <span class="material-symbols-outlined text-[18px] text-emerald-600">check_circle</span>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if (session('pos_resume_warnings'))
        <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 space-y-1">
            <div class="flex items-center gap-2 font-semibold">
                <span class="material-symbols-outlined text-[18px] text-amber-700">warning</span>
                <span>Perhatian Stok / Produk saat Melanjutkan Transaksi Pending:</span>
            </div>
            <ul class="list-disc list-inside text-xs space-y-0.5">
                @foreach (session('pos_resume_warnings') as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
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

    <!-- Three Input Modes Bar -->
    <div class="rounded-xl bg-surface-container-lowest border border-divider/80 p-3.5 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2 text-xs text-on-surface-variant">
            <span class="material-symbols-outlined text-[18px] text-primary">bolt</span>
            <span><strong class="text-primary">3 Order Input Modes:</strong> Manual selection always works offline/independently of AI.</span>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                @click="openPendingModal()"
                class="inline-flex items-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 text-amber-900 px-3 py-1.5 text-xs font-semibold hover:bg-amber-100 cursor-pointer"
            >
                <span class="material-symbols-outlined text-[16px]">pause_circle</span>
                <span>Pending Transactions</span>
                <span class="inline-flex items-center justify-center rounded-full bg-amber-600 text-white px-1.5 py-0.5 text-[10px] font-bold leading-none" x-text="pendingList.length"></span>
            </button>
            <span class="inline-flex items-center gap-1.5 rounded-lg bg-primary-container text-on-primary px-3 py-1.5 text-xs font-semibold">
                <span class="material-symbols-outlined text-[16px]">touch_app</span>
                Manual POS
            </span>
            <x-button size="sm" variant="secondary" :href="route('pos.ai.voice')">
                <span class="material-symbols-outlined text-[16px] text-secondary">mic</span>
                <span>Voice Order (AI)</span>
            </x-button>
            <x-button size="sm" variant="secondary" :href="route('pos.ai.image')">
                <span class="material-symbols-outlined text-[16px] text-secondary">photo_camera</span>
                <span>Image / OCR Order (AI)</span>
            </x-button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
        <!-- Left: Product Catalog (7 cols) -->
        <div
            x-data="{
                allProducts: @js($catalogProducts),
                filteredProducts: [],
                useClientFiltering: @js($useClientFiltering),
                searchQuery: @js($search),
                selectedCategory: @js($categoryId),
                searchUrl: @js(route('pos.products.search')),
                debounceTimer: null,
                abortController: null,
                requestSeq: 0,
                altModalOpen: false,
                altProductName: '',
                altItems: [],
                altLoading: false,
                async openAlternatives(product) {
                    this.altProductName = product.product_name;
                    this.altItems = [];
                    this.altLoading = true;
                    this.altModalOpen = true;
                    try {
                        const res = await fetch('/pos/products/' + product.id + '/alternatives', {
                            headers: { 'Accept': 'application/json' },
                        });
                        if (res.ok) {
                            const data = await res.json();
                            this.altItems = data.alternatives || [];
                        }
                    } finally {
                        this.altLoading = false;
                    }
                },
                init() {
                    this.applyLocalFilter();
                },
                onSearchInput() {
                    if (this.useClientFiltering) {
                        this.applyLocalFilter();
                        return;
                    }
                    clearTimeout(this.debounceTimer);
                    this.debounceTimer = setTimeout(() => this.fetchRemoteProducts(), 180);
                },
                clearSearch() {
                    this.searchQuery = '';
                    this.onSearchInput();
                    this.$refs.searchInput.focus();
                },
                applyLocalFilter() {
                    const q = (this.searchQuery || '').trim().toLowerCase();
                    const tokens = q ? q.split(/\s+/).filter(Boolean) : [];
                    const cat = String(this.selectedCategory || '');

                    this.filteredProducts = this.allProducts.filter((p) => {
                        if (cat !== '' && String(p.category_id) !== cat) {
                            return false;
                        }
                        if (tokens.length === 0) {
                            return true;
                        }
                        const haystack = (p.product_name + ' ' + p.product_code).toLowerCase();
                        return tokens.every((t) => haystack.includes(t));
                    });
                },
                async fetchRemoteProducts() {
                    if (this.abortController) {
                        this.abortController.abort();
                    }
                    this.abortController = new AbortController();
                    const currentSeq = ++this.requestSeq;

                    const params = new URLSearchParams();
                    if (this.searchQuery.trim() !== '') {
                        params.set('search', this.searchQuery.trim());
                    }
                    if (this.selectedCategory !== '') {
                        params.set('category_id', this.selectedCategory);
                    }

                    try {
                        const res = await fetch(this.searchUrl + '?' + params.toString(), {
                            headers: { 'Accept': 'application/json' },
                            signal: this.abortController.signal,
                        });
                        if (!res.ok) return;
                        const data = await res.json();
                        if (currentSeq === this.requestSeq) {
                            this.filteredProducts = data.products || [];
                        }
                    } catch (e) {
                        if (e.name !== 'AbortError') {
                            console.error(e);
                        }
                    }
                }
            }"
            class="lg:col-span-7 xl:col-span-8 rounded-xl bg-surface-container-lowest border border-divider/80 shadow-[0_1px_3px_0_rgba(37,43,54,0.04)] p-4 sm:p-5 space-y-4"
        >
            <div class="flex flex-col sm:flex-row gap-2.5">
                <div class="relative flex-1">
                    <span class="material-symbols-outlined text-[18px] text-outline absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none">search</span>
                    <input
                        x-ref="searchInput"
                        type="text"
                        x-model="searchQuery"
                        @input="onSearchInput()"
                        @keydown.enter.prevent
                        placeholder="Type product name or SKU (e.g. indomie gor, PRD-001)..."
                        autofocus
                        autocomplete="off"
                        class="w-full rounded-lg border border-divider bg-white pl-9 pr-9 py-2.5 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/15"
                    >
                    <button
                        type="button"
                        x-show="searchQuery.length > 0"
                        x-cloak
                        @click="clearSearch()"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-outline hover:text-primary cursor-pointer p-0.5 rounded"
                        aria-label="Clear search"
                    >
                        <span class="material-symbols-outlined text-[18px]">close</span>
                    </button>
                </div>

                <select
                    x-model="selectedCategory"
                    @change="onSearchInput()"
                    class="rounded-lg border border-divider bg-white px-3 py-2.5 text-sm focus:border-primary focus:outline-none"
                >
                    <option value="">All Categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center justify-between text-[11px] text-outline">
                <span>
                    Showing <strong class="text-on-surface tabular-nums" x-text="filteredProducts.length"></strong> product(s)
                </span>
                <button
                    type="button"
                    x-show="searchQuery !== '' || selectedCategory !== ''"
                    x-cloak
                    @click="searchQuery = ''; selectedCategory = ''; onSearchInput(); $refs.searchInput.focus();"
                    class="font-medium text-secondary hover:underline cursor-pointer"
                >
                    Reset filter
                </button>
            </div>

            <template x-if="filteredProducts.length === 0">
                <div class="flex flex-col items-center justify-center text-center px-6 py-10">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-surface-container-low text-outline">
                        <span class="material-symbols-outlined text-[22px]">search_off</span>
                    </div>
                    <h4 class="mt-3 text-sm font-semibold text-on-surface">No products found</h4>
                    <p class="mt-1 max-w-sm text-xs leading-relaxed text-on-surface-variant">
                        Try a different product name, SKU code, or clear the category filter.
                    </p>
                </div>
            </template>

            <div x-show="filteredProducts.length > 0" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                <template x-for="product in filteredProducts" :key="product.id">
                    <div
                        class="rounded-xl border border-divider/80 bg-white p-3.5 flex flex-col justify-between gap-3 transition-colors"
                        :class="product.out_of_stock ? 'opacity-65' : 'hover:border-primary/40'"
                    >
                        <div>
                            <div class="flex items-start justify-between gap-2">
                                <span class="font-mono text-[11px] font-semibold text-outline" x-text="product.product_code"></span>
                                <template x-if="product.out_of_stock">
                                    <span class="inline-flex items-center gap-1 rounded-[6px] px-2 py-0.5 text-[11px] font-semibold leading-4 bg-[#FDECEB] text-[#C53030]">
                                        Out of Stock
                                    </span>
                                </template>
                                <template x-if="!product.out_of_stock && product.is_low_stock">
                                    <span class="inline-flex items-center gap-1 rounded-[6px] px-2 py-0.5 text-[11px] font-semibold leading-4 bg-[#FEF7EA] text-[#B57914]" x-text="'Low: ' + product.stock_formatted"></span>
                                </template>
                                <template x-if="!product.out_of_stock && !product.is_low_stock">
                                    <span class="text-[11px] font-medium text-outline tabular-nums" x-text="'Stock: ' + product.stock_formatted + ' ' + product.unit"></span>
                                </template>
                            </div>
                            <h3 class="mt-1.5 text-sm font-semibold text-primary leading-snug" x-text="product.product_name"></h3>
                            <p class="text-[11px] text-on-surface-variant" x-text="product.category_name"></p>
                        </div>

                        <div class="pt-2 border-t border-divider/60 space-y-2">
                            <template x-if="product.units && product.units.length > 1">
                                <select
                                    x-model="product.selected_unit_id"
                                    class="w-full rounded-md border border-divider bg-surface-container-low/40 px-2 py-1 text-[11px] font-medium text-on-surface"
                                >
                                    <template x-for="u in product.units" :key="u.unit_name">
                                        <option
                                            :value="u.id"
                                            x-text="u.unit_name + (u.conversion_ratio > 1 ? ' (' + u.conversion_ratio + ' ' + product.unit + ')' : '') + ' — ' + u.price_formatted"
                                        ></option>
                                    </template>
                                </select>
                            </template>

                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-bold text-on-surface tabular-nums">
                                    <span x-text="(product.units.find(u => String(u.id) === String(product.selected_unit_id)) || product.units[0]).price_formatted"></span>
                                    <span class="text-[11px] font-normal text-outline" x-text="'/' + (product.units.find(u => String(u.id) === String(product.selected_unit_id)) || product.units[0]).unit_name"></span>
                                </span>

                                <div class="flex items-center gap-1.5">
                                    <template x-if="product.out_of_stock">
                                        <button
                                            type="button"
                                            @click="openAlternatives(product)"
                                            class="inline-flex items-center gap-1 rounded-lg bg-amber-50 border border-amber-200 px-2.5 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-100 cursor-pointer"
                                        >
                                            <span class="material-symbols-outlined text-[15px]">swap_horiz</span>
                                            <span>Alternatives</span>
                                        </button>
                                    </template>
                                    <form method="POST" action="{{ route('pos.cart.add') }}">
                                        @csrf
                                        <input type="hidden" name="product_id" :value="product.id">
                                        <input type="hidden" name="product_unit_id" :value="product.selected_unit_id">
                                        <input type="hidden" name="quantity" value="1">
                                        <input type="hidden" name="search" :value="searchQuery">
                                        <input type="hidden" name="category_id" :value="selectedCategory">
                                        <button
                                            type="submit"
                                            :disabled="product.out_of_stock"
                                            class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors"
                                            :class="product.out_of_stock ? 'bg-surface-container-low text-outline cursor-not-allowed' : 'bg-primary-container text-on-primary hover:bg-primary cursor-pointer'"
                                        >
                                            <span class="material-symbols-outlined text-[16px]">add_shopping_cart</span>
                                            <span>Add</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Out-of-Stock Alternatives Modal -->
            <div x-show="altModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-primary/40 p-4">
                <div @click.away="altModalOpen = false" class="w-full max-w-lg rounded-xl bg-white border border-divider shadow-xl overflow-hidden">
                    <div class="px-5 py-4 border-b border-divider flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-primary">Available Alternatives</h3>
                            <p class="text-xs text-on-surface-variant">In-stock substitutes for <strong x-text="altProductName"></strong></p>
                        </div>
                        <button type="button" @click="altModalOpen = false" class="text-outline hover:text-primary cursor-pointer">
                            <span class="material-symbols-outlined text-[20px]">close</span>
                        </button>
                    </div>
                    <div class="p-5 space-y-3 max-h-[65vh] overflow-y-auto">
                        <template x-if="altLoading">
                            <p class="text-xs text-outline text-center py-6">Finding available alternatives...</p>
                        </template>
                        <template x-if="!altLoading && altItems.length === 0">
                            <p class="text-xs text-outline text-center py-6">No similar in-stock alternatives found.</p>
                        </template>
                        <template x-for="alt in altItems" :key="alt.id">
                            <div class="rounded-lg border border-divider p-3 flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-primary truncate" x-text="alt.product_name"></p>
                                    <p class="text-[11px] text-outline" x-text="alt.reason"></p>
                                    <p class="text-xs font-medium text-on-surface mt-0.5 tabular-nums">
                                        <span x-text="alt.price_formatted"></span> / <span x-text="alt.unit"></span>
                                        &bull; Stock: <strong class="text-emerald-700" x-text="alt.stock_formatted + ' ' + alt.unit"></strong>
                                    </p>
                                </div>
                                <form method="POST" action="{{ route('pos.cart.add') }}" class="shrink-0">
                                    @csrf
                                    <input type="hidden" name="product_id" :value="alt.id">
                                    <input type="hidden" name="quantity" value="1">
                                    <button type="submit" class="inline-flex items-center gap-1 rounded-lg bg-primary-container text-on-primary px-3 py-1.5 text-xs font-semibold hover:bg-primary cursor-pointer">
                                        <span class="material-symbols-outlined text-[16px]">add_shopping_cart</span>
                                        <span>Add</span>
                                    </button>
                                </form>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile/Tablet Cart Backdrop -->
        <div
            x-show="mobileCartOpen"
            x-cloak
            x-transition.opacity.duration.200ms
            @click="mobileCartOpen = false"
            class="fixed inset-0 z-40 bg-primary/40 backdrop-blur-[2px] lg:hidden"
            aria-hidden="true"
        ></div>

        <!-- Right: Active Cart & Checkout (Responsive: Bottom Sheet on Mobile/Tablet, Sticky Panel on Desktop) -->
        @php
            $draftMethod = $checkoutDraft['payment_method'] ?? 'cash';
            $draftCustomerId = $checkoutDraft['customer_id'] ?? '';
            $draftDiscount = (float) ($checkoutDraft['discount_amount'] ?? 0);
            $draftNet = max(0, (float) $cart->total - $draftDiscount);
            $draftPaid = array_key_exists('amount_paid', $checkoutDraft) && $checkoutDraft['amount_paid'] !== null
                ? (float) $checkoutDraft['amount_paid']
                : $draftNet;
            $draftNotes = $checkoutDraft['notes'] ?? '';
        @endphp
        <div
            x-data="{
                total: {{ (float) $cart->total }},
                discount: {{ $draftDiscount }},
                paymentMethod: @js($draftMethod),
                customerId: @js((string) $draftCustomerId),
                customersList: @js($customers),
                showQuickCustomer: false,
                newCustName: '',
                newCustPhone: '',
                custError: '',
                amountPaid: {{ $draftPaid }},
                includePreviousDebt: @js((bool) ($checkoutDraft['include_previous_debt'] ?? false)),
                debtBusy: false,
                debtActionMessage: '',
                debtActionError: '',
                removeDebtModalOpen: false,
                removeDebtReason: 'already_paid_outside',
                removeDebtNotes: '',
                removeDebtError: '',
                get netTotal() {
                    return Math.max(0, this.total - (Number(this.discount) || 0));
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
                },
                onCustomerChange() {
                    this.includePreviousDebt = false;
                    this.debtActionMessage = '';
                    this.debtActionError = '';
                },
                async payNowDebt() {
                    if (!this.customerId || this.debtBusy) return;
                    this.debtBusy = true;
                    this.debtActionError = '';
                    this.debtActionMessage = '';
                    try {
                        const res = await fetch('/pos/customers/' + this.customerId + '/debt', {
                            headers: { 'Accept': 'application/json' },
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            this.debtActionError = data.message || 'Gagal mengambil data piutang.';
                            return;
                        }
                        if (this.selectedCustomer) {
                            this.selectedCustomer.outstanding_debt = Number(data.outstanding_debt) || 0;
                        }
                        if ((Number(data.outstanding_debt) || 0) > 0) {
                            this.includePreviousDebt = true;
                        } else {
                            this.includePreviousDebt = false;
                            this.debtActionMessage = 'Pelanggan tidak memiliki piutang tersisa.';
                        }
                    } catch (e) {
                        this.debtActionError = 'Terjadi kesalahan jaringan saat memeriksa piutang.';
                    } finally {
                        this.debtBusy = false;
                    }
                },
                removeDebtPayment() {
                    this.includePreviousDebt = false;
                },
                openRemoveDebtModal() {
                    if (!this.selectedCustomer || this.previousDebt <= 0) return;
                    this.removeDebtError = '';
                    this.removeDebtReason = 'already_paid_outside';
                    this.removeDebtNotes = '';
                    this.removeDebtModalOpen = true;
                },
                async confirmRemoveDebt() {
                    if (!this.customerId || this.debtBusy) return;
                    this.debtBusy = true;
                    this.removeDebtError = '';
                    try {
                        const res = await fetch('/pos/customers/' + this.customerId + '/remove-debt', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            },
                            body: JSON.stringify({
                                reason: this.removeDebtReason,
                                notes: this.removeDebtNotes,
                            }),
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            this.removeDebtError = data.message || (data.errors?.debt?.[0]) || 'Gagal menghapus piutang.';
                            return;
                        }
                        if (this.selectedCustomer) {
                            this.selectedCustomer.outstanding_debt = 0;
                        }
                        this.includePreviousDebt = false;
                        this.removeDebtModalOpen = false;
                        this.debtActionMessage = data.message || 'Piutang berhasil dihapus.';
                    } catch (e) {
                        this.removeDebtError = 'Terjadi kesalahan jaringan.';
                    } finally {
                        this.debtBusy = false;
                    }
                },
                async quickAddCustomer() {
                    this.custError = '';
                    if (!this.newCustName.trim()) {
                        this.custError = 'Nama pelanggan wajib diisi.';
                        return;
                    }
                    try {
                        const res = await fetch(@js(route('pos.customers.quick')), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            },
                            body: JSON.stringify({ name: this.newCustName, phone: this.newCustPhone }),
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            this.custError = data.message || 'Gagal menambah pelanggan.';
                            return;
                        }
                        this.customersList.push(data.customer);
                        this.customerId = String(data.customer.id);
                        this.newCustName = '';
                        this.newCustPhone = '';
                        this.showQuickCustomer = false;
                    } catch (e) {
                        this.custError = 'Terjadi kesalahan jaringan.';
                    }
                }
            }"
            :class="mobileCartOpen ? 'fixed inset-x-0 bottom-0 z-50 flex flex-col max-h-[88dvh] rounded-t-2xl' : 'hidden lg:flex lg:flex-col'"
            class="lg:static lg:col-span-5 xl:col-span-4 lg:sticky lg:top-20 lg:max-h-[calc(100vh-6rem)] lg:rounded-xl bg-surface-container-lowest border border-divider/80 shadow-[0_8px_30px_rgba(10,37,77,0.12)] lg:shadow-[0_1px_3px_0_rgba(37,43,54,0.04)] overflow-hidden"
        >
            <div class="px-4 sm:px-5 py-3.5 sm:py-4 border-b border-divider flex items-center justify-between gap-2 shrink-0">
                <div>
                    <h2 class="text-base font-semibold text-primary">Current Cart</h2>
                    <p class="text-[11px] text-on-surface-variant">{{ $cart->items->count() }} item(s) in active cart</p>
                </div>
                <div class="flex items-center gap-3">
                    @if ($cart->items->isNotEmpty())
                        <form method="POST" action="{{ route('pos.cart.clear') }}" onsubmit="return confirm('Kosongkan keranjang?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs font-medium text-red-600 hover:underline cursor-pointer">Clear Cart</button>
                        </form>
                    @endif
                    <button
                        type="button"
                        @click="mobileCartOpen = false"
                        class="lg:hidden inline-flex h-8 w-8 items-center justify-center rounded-lg text-outline hover:bg-surface-container-low hover:text-primary cursor-pointer"
                        aria-label="Close cart"
                    >
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>
            </div>

            <div class="p-4 sm:p-5 space-y-4 flex-1 min-h-0 overflow-y-auto">
                @if ($cart->items->isEmpty())
                    <x-empty-state
                        title="Cart is Empty"
                        description="Select products manually or use Voice/Image AI input to add items."
                    />
                @else
                    <div class="divide-y divide-divider/70">
                        @foreach ($cart->items as $item)
                            @php
                                $itemProduct = $item->product;
                                $itemStock = (float) ($itemProduct?->stock ?? 0);
                                $itemRequiredBase = $item->baseQuantity();
                                $itemStockShort = ! $itemProduct || ! $itemProduct->is_active || $itemStock < $itemRequiredBase;
                            @endphp
                            <div x-data="{ editPrice: false }" class="py-3 first:pt-0 space-y-2">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <p class="text-sm font-semibold text-on-surface truncate">{{ $item->product_name_snapshot ?: $itemProduct?->product_name }}</p>
                                            @if ($item->is_price_overridden)
                                                <span class="rounded bg-blue-50 border border-blue-200 px-1.5 py-0.5 text-[10px] font-semibold text-blue-700">Price Edited</span>
                                            @endif
                                            @if ($itemStockShort)
                                                <span class="rounded bg-red-50 border border-red-200 px-1.5 py-0.5 text-[10px] font-semibold text-red-700">
                                                    Stock: {{ \App\Support\Format::quantity($itemStock) }} {{ $itemProduct?->unit }}
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-[11px] text-outline tabular-nums">
                                            @if ($item->is_price_overridden && $item->original_price !== null)
                                                <span class="line-through text-outline/70 mr-1">{{ \App\Support\Format::rupiah($item->original_price) }}</span>
                                            @endif
                                            {{ \App\Support\Format::rupiah($item->price_snapshot) }} / {{ $item->unit_name ?: $itemProduct?->unit }}
                                            <button type="button" @click="editPrice = !editPrice" class="ml-1.5 text-secondary font-semibold hover:underline cursor-pointer">Edit Price</button>
                                        </p>
                                    </div>
                                    <p class="text-sm font-bold text-primary tabular-nums shrink-0">
                                        {{ \App\Support\Format::rupiah($item->subtotal) }}
                                    </p>
                                </div>

                                <form x-show="editPrice" x-cloak method="POST" action="{{ route('pos.cart.update-price', $item) }}" class="rounded-lg border border-divider bg-surface-container-low/50 p-2 flex flex-wrap items-center gap-1.5">
                                    @csrf
                                    @method('PATCH')
                                    <input type="number" name="price" min="0" step="1" value="{{ (float) $item->price_snapshot }}" required class="w-24 rounded border border-divider bg-white px-2 py-1 text-xs tabular-nums" placeholder="Price">
                                    <input type="text" name="reason" value="{{ $item->price_override_reason }}" class="flex-1 min-w-[110px] rounded border border-divider bg-white px-2 py-1 text-xs" placeholder="Reason (optional)">
                                    <button type="submit" class="rounded bg-primary-container text-on-primary px-2.5 py-1 text-xs font-semibold cursor-pointer">Save</button>
                                </form>

                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-1">
                                        <form method="POST" action="{{ route('pos.cart.update', $item) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="quantity" value="{{ max(0, (float) $item->quantity - 1) }}">
                                            <button type="submit" class="h-8 w-8 sm:h-7 sm:w-7 rounded-md border border-divider bg-white text-xs font-bold hover:bg-surface-container-low cursor-pointer">-</button>
                                        </form>

                                        <form method="POST" action="{{ route('pos.cart.update', $item) }}" class="flex items-center gap-1">
                                            @csrf
                                            @method('PATCH')
                                            <input
                                                type="number"
                                                name="quantity"
                                                step="0.001"
                                                min="0.001"
                                                max="{{ (float) ($item->product?->stock ?? 9999) }}"
                                                value="{{ (float) $item->quantity }}"
                                                class="w-16 rounded-md border border-divider px-2 py-1.5 sm:py-1 text-center text-xs tabular-nums"
                                            >
                                            <button type="submit" class="rounded-md bg-surface-container-low px-2.5 py-1.5 sm:py-1 text-xs font-semibold text-primary hover:bg-surface-container cursor-pointer">
                                                Set
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('pos.cart.update', $item) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="quantity" value="{{ (float) $item->quantity + 1 }}">
                                            <button type="submit" class="h-8 w-8 sm:h-7 sm:w-7 rounded-md border border-divider bg-white text-xs font-bold hover:bg-surface-container-low cursor-pointer">+</button>
                                        </form>
                                    </div>

                                    <form method="POST" action="{{ route('pos.cart.remove', $item) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-red-600 hover:text-red-800 p-1 cursor-pointer" title="Remove">
                                            <span class="material-symbols-outlined text-[18px]">delete</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Proceed to Checkout Confirmation Form -->
                    <form method="POST" action="{{ route('pos.checkout.prepare') }}" class="pt-4 border-t border-divider space-y-3.5">
                        @csrf
                        <input type="hidden" name="include_previous_debt" :value="includePreviousDebt ? 1 : 0">

                        <!-- Distinct Previous Debt Line Item when Pay Now is active -->
                        <template x-if="includedDebt > 0">
                            <div class="rounded-lg border border-amber-300 bg-amber-50/90 px-3 py-2.5 flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-amber-950">Previous Outstanding Debt</p>
                                    <p class="text-[11px] text-amber-800 tabular-nums" x-text="'Debt Payment: ' + formatRp(includedDebt)"></p>
                                </div>
                                <button
                                    type="button"
                                    @click="removeDebtPayment()"
                                    class="shrink-0 rounded-md border border-amber-300 bg-white px-2 py-1 text-[11px] font-semibold text-red-700 hover:bg-red-50 cursor-pointer"
                                >
                                    Remove Debt Payment
                                </button>
                            </div>
                        </template>

                        <div class="space-y-1.5 text-sm">
                            <div class="flex justify-between text-on-surface-variant">
                                <span>Subtotal</span>
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
                                    class="w-28 rounded-md border border-divider px-2 py-1 text-right text-xs tabular-nums"
                                >
                            </div>
                            <div class="flex justify-between text-sm font-semibold text-on-surface pt-1 border-t border-divider/60">
                                <span>Current Purchase</span>
                                <span class="tabular-nums" x-text="formatRp(netTotal)"></span>
                            </div>
                            <div class="flex justify-between text-xs font-semibold" :class="includedDebt > 0 ? 'text-amber-800' : 'text-on-surface-variant'">
                                <span>Previous Debt Payment</span>
                                <span class="tabular-nums" x-text="formatRp(includedDebt)"></span>
                            </div>
                            <div class="flex justify-between text-base font-bold text-primary pt-1 border-t border-divider/60">
                                <span>Grand Total</span>
                                <span class="tabular-nums" x-text="formatRp(grandTotal)"></span>
                            </div>
                        </div>

                        <!-- Customer Selection (Optional for Cash, Required for Debt/Partial) -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <label class="block text-xs font-semibold text-on-surface">Customer <span class="font-normal text-outline">(Optional if Paid)</span></label>
                                <button type="button" @click="showQuickCustomer = !showQuickCustomer" class="text-[11px] font-semibold text-secondary hover:underline cursor-pointer">
                                    + New Customer
                                </button>
                            </div>
                            <select name="customer_id" x-model="customerId" @change="onCustomerChange()" class="w-full rounded-lg border border-divider bg-white px-3 py-2 text-xs">
                                <option value="">Walk-in / General Customer</option>
                                <template x-for="c in customersList" :key="c.id">
                                    <option :value="String(c.id)" x-text="(c.customer_code ? c.customer_code + ' – ' : '') + c.name + (c.outstanding_debt > 0 ? ' [Debt: ' + formatRp(c.outstanding_debt) + ']' : '')" :selected="String(customerId) === String(c.id)"></option>
                                </template>
                            </select>

                            <template x-if="selectedCustomer">
                                <div
                                    class="rounded-lg px-3 py-2.5 text-[11px] border space-y-2"
                                    :class="previousDebt > 0 ? 'bg-amber-50 border-amber-200 text-amber-900' : 'bg-surface-container-low/60 border-divider text-on-surface-variant'"
                                >
                                    <div class="flex items-center justify-between gap-2">
                                        <span><strong x-text="selectedCustomer.customer_code"></strong> &ndash; <span x-text="selectedCustomer.name"></span></span>
                                        <span class="font-bold tabular-nums" :class="previousDebt > 0 ? 'text-red-700' : ''" x-text="'Outstanding Debt: ' + formatRp(previousDebt)"></span>
                                    </div>
                                    <template x-if="previousDebt > 0">
                                        <div class="flex flex-wrap items-center gap-1.5 pt-1 border-t border-amber-200/70">
                                            <template x-if="!includePreviousDebt">
                                                <button
                                                    type="button"
                                                    @click="payNowDebt()"
                                                    :disabled="debtBusy"
                                                    class="rounded-md bg-primary-container text-on-primary px-2.5 py-1 text-[11px] font-semibold hover:bg-primary cursor-pointer disabled:opacity-50"
                                                >
                                                    Pay Now / Bayar Sekarang
                                                </button>
                                            </template>
                                            <template x-if="includePreviousDebt">
                                                <div class="flex flex-wrap items-center gap-1.5">
                                                    <span class="inline-flex items-center rounded-md bg-emerald-100 text-emerald-800 px-2 py-0.5 text-[10px] font-semibold">
                                                        Included in Transaction
                                                    </span>
                                                    <button
                                                        type="button"
                                                        @click="removeDebtPayment()"
                                                        class="rounded-md border border-divider bg-white px-2 py-0.5 text-[11px] font-semibold text-red-700 hover:bg-red-50 cursor-pointer"
                                                    >
                                                        Undo
                                                    </button>
                                                </div>
                                            </template>
                                            @if (auth()->user()->isAdministrator())
                                                <button
                                                    type="button"
                                                    @click="openRemoveDebtModal()"
                                                    :disabled="debtBusy"
                                                    class="rounded-md border border-red-300 bg-white text-red-700 px-2.5 py-1 text-[11px] font-semibold hover:bg-red-50 cursor-pointer disabled:opacity-50"
                                                >
                                                    Remove Debt / Hapus Piutang
                                                </button>
                                            @endif
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <p x-show="debtActionMessage" x-text="debtActionMessage" class="text-[11px] text-emerald-700 font-medium"></p>
                            <p x-show="debtActionError" x-text="debtActionError" class="text-[11px] text-red-600 font-medium"></p>

                            <div x-show="showQuickCustomer" x-cloak class="rounded-lg border border-divider bg-surface-container-low/50 p-2.5 space-y-2">
                                <input type="text" x-model="newCustName" placeholder="Customer name*" class="w-full rounded border border-divider bg-white px-2.5 py-1.5 text-xs">
                                <input type="text" x-model="newCustPhone" placeholder="Phone (optional)" class="w-full rounded border border-divider bg-white px-2.5 py-1.5 text-xs">
                                <p x-show="custError" x-text="custError" class="text-[11px] text-red-600"></p>
                                <div class="flex justify-end gap-1.5">
                                    <button type="button" @click="showQuickCustomer = false" class="px-2 py-1 text-[11px] text-outline">Cancel</button>
                                    <button type="button" @click="quickAddCustomer()" class="rounded bg-primary-container text-on-primary px-2.5 py-1 text-[11px] font-semibold cursor-pointer">Save</button>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-on-surface mb-1.5">Payment Method</label>
                            <div class="grid grid-cols-3 gap-2">
                                <label class="cursor-pointer">
                                    <input type="radio" name="payment_method" value="cash" x-model="paymentMethod" class="sr-only peer">
                                    <div class="rounded-lg border border-divider py-2 text-center text-xs font-semibold peer-checked:border-primary peer-checked:bg-primary-container peer-checked:text-on-primary">
                                        Cash
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="payment_method" value="qris" x-model="paymentMethod" class="sr-only peer">
                                    <div class="rounded-lg border border-divider py-2 text-center text-xs font-semibold peer-checked:border-primary peer-checked:bg-primary-container peer-checked:text-on-primary">
                                        QRIS
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="payment_method" value="other" x-model="paymentMethod" class="sr-only peer">
                                    <div class="rounded-lg border border-divider py-2 text-center text-xs font-semibold peer-checked:border-primary peer-checked:bg-primary-container peer-checked:text-on-primary">
                                        Other
                                    </div>
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
                            <div class="flex flex-wrap gap-1.5">
                                <button type="button" @click="amountPaid = grandTotal" class="rounded bg-surface-container-low px-2 py-1 text-[11px] font-medium text-primary hover:bg-surface-container cursor-pointer">Exact</button>
                                <button type="button" @click="amountPaid = 0" class="rounded bg-red-50 px-2 py-1 text-[11px] font-medium text-red-700 hover:bg-red-100 cursor-pointer">Rp 0 (Debt)</button>
                                <button type="button" @click="amountPaid = 20000" class="rounded bg-surface-container-low px-2 py-1 text-[11px] font-medium text-primary hover:bg-surface-container cursor-pointer">20k</button>
                                <button type="button" @click="amountPaid = 50000" class="rounded bg-surface-container-low px-2 py-1 text-[11px] font-medium text-primary hover:bg-surface-container cursor-pointer">50k</button>
                                <button type="button" @click="amountPaid = 100000" class="rounded bg-surface-container-low px-2 py-1 text-[11px] font-medium text-primary hover:bg-surface-container cursor-pointer">100k</button>
                            </div>

                            <div
                                class="rounded-lg px-3 py-2 text-xs font-semibold border space-y-1"
                                :class="outstandingDebt > 0 ? 'bg-amber-50 border-amber-200 text-amber-900' : 'bg-emerald-50 border-emerald-200 text-emerald-800'"
                            >
                                <template x-if="includedDebt > 0">
                                    <div class="flex justify-between items-center text-[11px]">
                                        <span>Debt Settled:</span>
                                        <span class="tabular-nums font-bold" x-text="formatRp(debtSettled)"></span>
                                    </div>
                                </template>
                                <template x-if="outstandingDebt > 0">
                                    <div class="flex justify-between items-center">
                                        <span>Remaining Debt:</span>
                                        <span class="tabular-nums font-bold text-red-700" x-text="formatRp(outstandingDebt)"></span>
                                    </div>
                                </template>
                                <template x-if="outstandingDebt === 0">
                                    <div class="flex justify-between items-center">
                                        <span>Change:</span>
                                        <span class="tabular-nums font-bold" x-text="formatRp(change)"></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div>
                            <input
                                type="text"
                                name="notes"
                                value="{{ $draftNotes }}"
                                placeholder="Optional transaction note..."
                                class="w-full rounded-lg border border-divider px-3 py-2 text-xs focus:border-primary focus:outline-none"
                            >
                        </div>

                        <x-button type="submit" variant="primary" size="lg" class="w-full">
                            <span class="material-symbols-outlined text-[18px]">shopping_cart_checkout</span>
                            <span>Proceed to Checkout</span>
                        </x-button>

                        <button
                            type="submit"
                            formaction="{{ route('pos.hold') }}"
                            formmethod="POST"
                            class="w-full inline-flex items-center justify-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-4 py-2.5 text-sm font-semibold text-amber-900 hover:bg-amber-100 cursor-pointer"
                        >
                            <span class="material-symbols-outlined text-[18px]">pause_circle</span>
                            <span>Hold Transaction / Pending</span>
                        </button>
                    </form>
                @endif
            </div>

            <!-- Remove Outstanding Debt Confirmation Modal -->
            <div x-show="removeDebtModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-primary/40 p-4">
                <div @click.away="removeDebtModalOpen = false" class="w-full max-w-md rounded-xl bg-white border border-divider shadow-xl overflow-hidden">
                    <div class="px-5 py-4 border-b border-divider flex items-center justify-between">
                        <h3 class="text-base font-semibold text-red-700">Remove Outstanding Debt?</h3>
                        <button type="button" @click="removeDebtModalOpen = false" class="text-outline hover:text-primary cursor-pointer">
                            <span class="material-symbols-outlined text-[20px]">close</span>
                        </button>
                    </div>
                    <div class="p-5 space-y-4">
                        <div class="rounded-lg bg-red-50 border border-red-200 px-3.5 py-2.5 text-xs text-red-900 space-y-1">
                            <p>Customer: <strong x-text="selectedCustomer ? selectedCustomer.name + ' (' + selectedCustomer.customer_code + ')' : ''"></strong></p>
                            <p>Current Outstanding Debt: <strong class="tabular-nums" x-text="formatRp(previousDebt)"></strong></p>
                        </div>

                        <p class="text-xs text-on-surface-variant leading-relaxed">
                            Are you sure you want to remove this outstanding debt? This action will remove the debt from the customer's outstanding balance and will <strong>NOT</strong> add it to the current transaction.
                        </p>

                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-on-surface">Reason</label>
                            <select x-model="removeDebtReason" class="w-full rounded-lg border border-divider bg-white px-3 py-2 text-xs">
                                <option value="already_paid_outside">Already paid outside the system</option>
                                <option value="incorrect_record">Incorrect debt record</option>
                                <option value="write_off">Debt write-off</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-on-surface">Notes <span class="font-normal text-outline">(Optional)</span></label>
                            <textarea x-model="removeDebtNotes" rows="2" placeholder="Optional explanation or reference..." class="w-full rounded-lg border border-divider px-3 py-2 text-xs focus:border-primary focus:outline-none"></textarea>
                        </div>

                        <p x-show="removeDebtError" x-text="removeDebtError" class="text-xs text-red-600 font-medium"></p>

                        <div class="flex justify-end gap-2 pt-2 border-t border-divider">
                            <button type="button" @click="removeDebtModalOpen = false" class="rounded-lg border border-divider px-3.5 py-2 text-xs font-semibold text-on-surface hover:bg-surface-container-low cursor-pointer">
                                Cancel
                            </button>
                            <button type="button" @click="confirmRemoveDebt()" :disabled="debtBusy" class="rounded-lg bg-red-600 text-white px-3.5 py-2 text-xs font-semibold hover:bg-red-700 cursor-pointer disabled:opacity-50">
                                Confirm Remove Debt
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Out-of-Stock Alternatives Modal -->
            <div x-show="altModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-primary/40 p-4">
                <div @click.away="altModalOpen = false" class="w-full max-w-lg rounded-xl bg-white border border-divider shadow-xl overflow-hidden">
                    <div class="px-5 py-4 border-b border-divider flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-primary">Available Alternatives</h3>
                            <p class="text-xs text-on-surface-variant">In-stock substitutes for <strong x-text="altProductName"></strong></p>
                        </div>
                        <button type="button" @click="altModalOpen = false" class="text-outline hover:text-primary cursor-pointer">
                            <span class="material-symbols-outlined text-[20px]">close</span>
                        </button>
                    </div>
                    <div class="p-5 space-y-3 max-h-[65vh] overflow-y-auto">
                        <template x-if="altLoading">
                            <p class="text-xs text-outline text-center py-6">Finding available alternatives...</p>
                        </template>
                        <template x-if="!altLoading && altItems.length === 0">
                            <p class="text-xs text-outline text-center py-6">No similar in-stock alternatives found.</p>
                        </template>
                        <template x-for="alt in altItems" :key="alt.id">
                            <div class="rounded-lg border border-divider p-3 flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-primary truncate" x-text="alt.product_name"></p>
                                    <p class="text-[11px] text-outline" x-text="alt.reason"></p>
                                    <p class="text-xs font-medium text-on-surface mt-0.5 tabular-nums">
                                        <span x-text="alt.price_formatted"></span> / <span x-text="alt.unit"></span>
                                        &bull; Stock: <strong class="text-emerald-700" x-text="alt.stock_formatted + ' ' + alt.unit"></strong>
                                    </p>
                                </div>
                                <form method="POST" action="{{ route('pos.cart.add') }}" class="shrink-0">
                                    @csrf
                                    <input type="hidden" name="product_id" :value="alt.id">
                                    <input type="hidden" name="quantity" value="1">
                                    <button type="submit" class="inline-flex items-center gap-1 rounded-lg bg-primary-container text-on-primary px-3 py-1.5 text-xs font-semibold hover:bg-primary cursor-pointer">
                                        <span class="material-symbols-outlined text-[16px]">add_shopping_cart</span>
                                        <span>Add</span>
                                    </button>
                                </form>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile/Tablet Sticky Bottom Cart Bar -->
    <div class="lg:hidden fixed bottom-0 inset-x-0 z-30 bg-white/95 backdrop-blur border-t border-divider shadow-[0_-4px_16px_rgba(10,37,77,0.08)] px-4 py-3 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-container text-on-primary relative">
                <span class="material-symbols-outlined text-[20px]">shopping_cart</span>
                @if ($cart->items->isNotEmpty())
                    <span class="absolute -top-1.5 -right-1.5 h-5 min-w-5 px-1 rounded-full bg-emerald-600 text-white text-[10px] font-bold flex items-center justify-center tabular-nums">
                        {{ $cart->items->count() }}
                    </span>
                @endif
            </div>
            <div class="min-w-0">
                <p class="text-[11px] font-medium text-outline">{{ $cart->items->count() }} item(s) in cart</p>
                <p class="text-sm font-bold text-primary tabular-nums truncate">{{ \App\Support\Format::rupiah($cart->total) }}</p>
            </div>

            <!-- Out-of-Stock Alternatives Modal -->
            <div x-show="altModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-primary/40 p-4">
                <div @click.away="altModalOpen = false" class="w-full max-w-lg rounded-xl bg-white border border-divider shadow-xl overflow-hidden">
                    <div class="px-5 py-4 border-b border-divider flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-primary">Available Alternatives</h3>
                            <p class="text-xs text-on-surface-variant">In-stock substitutes for <strong x-text="altProductName"></strong></p>
                        </div>
                        <button type="button" @click="altModalOpen = false" class="text-outline hover:text-primary cursor-pointer">
                            <span class="material-symbols-outlined text-[20px]">close</span>
                        </button>
                    </div>
                    <div class="p-5 space-y-3 max-h-[65vh] overflow-y-auto">
                        <template x-if="altLoading">
                            <p class="text-xs text-outline text-center py-6">Finding available alternatives...</p>
                        </template>
                        <template x-if="!altLoading && altItems.length === 0">
                            <p class="text-xs text-outline text-center py-6">No similar in-stock alternatives found.</p>
                        </template>
                        <template x-for="alt in altItems" :key="alt.id">
                            <div class="rounded-lg border border-divider p-3 flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-primary truncate" x-text="alt.product_name"></p>
                                    <p class="text-[11px] text-outline" x-text="alt.reason"></p>
                                    <p class="text-xs font-medium text-on-surface mt-0.5 tabular-nums">
                                        <span x-text="alt.price_formatted"></span> / <span x-text="alt.unit"></span>
                                        &bull; Stock: <strong class="text-emerald-700" x-text="alt.stock_formatted + ' ' + alt.unit"></strong>
                                    </p>
                                </div>
                                <form method="POST" action="{{ route('pos.cart.add') }}" class="shrink-0">
                                    @csrf
                                    <input type="hidden" name="product_id" :value="alt.id">
                                    <input type="hidden" name="quantity" value="1">
                                    <button type="submit" class="inline-flex items-center gap-1 rounded-lg bg-primary-container text-on-primary px-3 py-1.5 text-xs font-semibold hover:bg-primary cursor-pointer">
                                        <span class="material-symbols-outlined text-[16px]">add_shopping_cart</span>
                                        <span>Add</span>
                                    </button>
                                </form>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <button
            type="button"
            @click="mobileCartOpen = true"
            class="inline-flex items-center gap-1.5 rounded-lg bg-primary-container text-on-primary px-4 py-2.5 text-xs font-semibold hover:bg-primary cursor-pointer shrink-0"
        >
            <span class="material-symbols-outlined text-[18px]">expand_less</span>
            <span>Open Cart</span>
        </button>
    </div>
    <!-- Pending Transactions Modal -->
    <div x-show="pendingModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-primary/40 p-4">
        <div @click.away="pendingModalOpen = false" class="w-full max-w-3xl rounded-xl bg-white border border-divider shadow-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-divider flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-primary">Pending Transactions (<span x-text="pendingList.length"></span>)</h3>
                    <p class="text-xs text-on-surface-variant">Select a held transaction to resume or cancel it.</p>
                </div>
                <button type="button" @click="pendingModalOpen = false" class="text-outline hover:text-primary cursor-pointer">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>
            <div class="p-5 space-y-3 max-h-[70vh] overflow-y-auto">
                <p x-show="pendingError" x-text="pendingError" class="rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-xs text-red-700 font-medium"></p>

                <template x-if="pendingList.length === 0">
                    <p class="text-xs text-outline text-center py-8">No pending transactions on hold.</p>
                </template>

                <template x-if="pendingList.length > 0">
                    <div class="divide-y divide-divider/70">
                        <template x-for="p in pendingList" :key="p.id">
                            <div class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div class="min-w-0 space-y-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-mono text-xs font-bold text-primary" x-text="p.hold_reference"></span>
                                        <span class="rounded-full bg-amber-100 text-amber-900 px-2 py-0.5 text-[10px] font-semibold">Pending</span>
                                        <template x-if="p.include_previous_debt">
                                            <span class="rounded-full bg-blue-50 text-blue-800 px-2 py-0.5 text-[10px] font-semibold">Includes Prev Debt</span>
                                        </template>
                                    </div>
                                    <p class="text-xs font-semibold text-on-surface" x-text="(p.customer_code ? p.customer_code + ' – ' : '') + p.customer_name"></p>
                                    <p class="text-[11px] text-outline tabular-nums" x-text="p.items_count + ' item(s) • Held: ' + p.held_at_formatted + ' • Cashier: ' + p.cashier_name"></p>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="text-sm font-bold text-primary tabular-nums mr-2" x-text="formatPendingRp(p.net_total)"></span>
                                    <button
                                        type="button"
                                        @click="resumePending(p.id)"
                                        :disabled="pendingBusy"
                                        class="rounded-lg bg-primary-container text-on-primary px-3 py-1.5 text-xs font-semibold hover:bg-primary cursor-pointer disabled:opacity-50"
                                    >
                                        Resume Transaction
                                    </button>
                                    <button
                                        type="button"
                                        @click="promptCancelPending(p)"
                                        :disabled="pendingBusy"
                                        class="rounded-lg border border-red-200 bg-white text-red-700 px-2.5 py-1.5 text-xs font-semibold hover:bg-red-50 cursor-pointer disabled:opacity-50"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Active Cart Conflict Confirmation Modal -->
    <div x-show="conflictModalOpen" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center bg-primary/50 p-4">
        <div class="w-full max-w-md rounded-xl bg-white border border-divider shadow-xl p-5 space-y-4">
            <h3 class="text-base font-semibold text-primary">Active Cart Has Unsaved Items</h3>
            <p class="text-xs text-on-surface-variant leading-relaxed">
                You currently have an active cart (<strong x-text="conflictInfo.active_items_count"></strong> item(s), <strong class="tabular-nums" x-text="formatPendingRp(conflictInfo.active_total)"></strong>). What would you like to do?
            </p>
            <div class="space-y-2 pt-2">
                <button
                    type="button"
                    @click="conflictModalOpen = false; resumePending(conflictTargetId, 'hold_current')"
                    :disabled="pendingBusy"
                    class="w-full rounded-lg bg-primary-container text-on-primary px-3.5 py-2 text-xs font-semibold hover:bg-primary cursor-pointer"
                >
                    Hold Current Cart and Resume Selected Transaction
                </button>
                <button
                    type="button"
                    @click="conflictModalOpen = false; conflictTargetId = null"
                    class="w-full rounded-lg border border-divider bg-white text-on-surface px-3.5 py-2 text-xs font-semibold hover:bg-surface-container-low cursor-pointer"
                >
                    Cancel and Continue Current Cart
                </button>
            </div>
        </div>
    </div>

    <!-- Cancel Pending Confirmation Modal -->
    <div x-show="cancelModalOpen" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center bg-primary/50 p-4">
        <div class="w-full max-w-md rounded-xl bg-white border border-divider shadow-xl p-5 space-y-4">
            <h3 class="text-base font-semibold text-red-700">Cancel Pending Transaction?</h3>
            <p class="text-xs text-on-surface-variant">Are you sure you want to cancel this pending transaction? No sales, stock deduction, or debt settlement will occur.</p>
            <template x-if="cancelTarget">
                <div class="rounded-lg bg-red-50 border border-red-200 px-3.5 py-2.5 text-xs text-red-900 space-y-1">
                    <p>Reference: <strong class="font-mono" x-text="cancelTarget.hold_reference"></strong></p>
                    <p>Customer: <strong x-text="cancelTarget.customer_name"></strong></p>
                    <p>Items: <strong x-text="cancelTarget.items_count"></strong> &bull; Total: <strong class="tabular-nums" x-text="formatPendingRp(cancelTarget.net_total)"></strong></p>
                </div>
            </template>
            <div class="flex justify-end gap-2 pt-2 border-t border-divider">
                <button type="button" @click="cancelModalOpen = false; cancelTarget = null" class="rounded-lg border border-divider px-3.5 py-2 text-xs font-semibold text-on-surface hover:bg-surface-container-low cursor-pointer">
                    Keep Pending
                </button>
                <button type="button" @click="confirmCancelPending()" :disabled="pendingBusy" class="rounded-lg bg-red-600 text-white px-3.5 py-2 text-xs font-semibold hover:bg-red-700 cursor-pointer disabled:opacity-50">
                    Confirm Cancel Pending
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
