@extends('layouts.app', [
    'pageTitle' => 'Dashboard',
    'breadcrumb' => 'Dashboard',
])

@section('title', 'Dashboard')

@section('content')
@php
    $user = auth()->user();
    $isAdmin = $user && $user->isAdministrator();
@endphp

<div
    @if ($isAdmin)
        x-data="{
            period: @js($period),
            dateFrom: @js($dateFrom),
            dateTo: @js($dateTo),
            lastUpdated: @js($lastUpdated),
            isRefreshing: false,
            analyticsUrl: @js(route('dashboard.analytics-data')),
            periodSalesFormatted: @js($periodSalesFormatted),
            periodTransactionsCount: {{ (int) $periodTransactionsCount }},
            periodProductsSoldFormatted: @js($periodProductsSoldFormatted),
            periodGrossProfitFormatted: @js($periodGrossProfitFormatted),
            outOfStockCount: {{ (int) $outOfStockCount }},
            totalOutstandingDebtFormatted: @js($totalOutstandingDebtFormatted),
            topProducts: @js($topProductsData),
            categories: @js($topCategories),
            debtTicker: @js($debtTicker),
            restockList: @js($restockRecommendations),
            deadStockList: @js($deadStockItems),
            profitIntel: @js($profitIntelligence),
            salesPatterns: @js($salesPatterns),
            intelTab: 'restock',
            deadDays: {{ (int) $deadDays }},
            deadCategory: '',
            deadStatus: 'all',
            profitCategory: '',
            selectedCatId: @js($topCategories->first()['category_id'] ?? null),
            productFilter: '',
            chartMode: 'bar',
            init() {
                this.$nextTick(() => requestAnimationFrame(() => this.renderTopProductsChart()));
                setInterval(() => this.refreshAnalytics(), 60000);
            },
            setIntelTab(tab) {
                this.intelTab = tab;
                this.$nextTick(() => requestAnimationFrame(() => {
                    if (tab === 'profit') this.renderProfitChart();
                    if (tab === 'patterns') this.renderPatternCharts();
                }));
            },
            get selectedCategory() {
                return this.categories.find(c => c.category_id === this.selectedCatId) || null;
            },
            get filteredTopProducts() {
                const q = (this.productFilter || '').trim().toLowerCase();
                if (!q) return this.topProducts;
                return this.topProducts.filter(p => (p.name || '').toLowerCase().includes(q));
            },
            formatRp(val) {
                return 'Rp ' + Math.round(val).toLocaleString('id-ID');
            },
            setChartMode(mode) {
                this.chartMode = mode;
                this.$nextTick(() => requestAnimationFrame(() => this.renderTopProductsChart()));
            },
            renderTopProductsChart() {
                if (window.__topSellingChart) {
                    window.__topSellingChart.destroy();
                    window.__topSellingChart = null;
                }
                if (this.chartMode === 'table') {
                    return;
                }
                const canvas = document.getElementById('topSellingProductsChart');
                if (!canvas || typeof window.Chart === 'undefined' || this.topProducts.length === 0) {
                    return;
                }

                const isHorizontal = this.chartMode === 'horizontalBar';
                const chartType = this.chartMode === 'line' ? 'line' : 'bar';
                const labels = this.topProducts.map(p => p.name);
                const qtyData = this.topProducts.map(p => p.qty);

                window.__topSellingChart = new window.Chart(canvas, {
                    type: chartType,
                    data: {
                        labels,
                        datasets: [{
                            label: 'Quantity Sold',
                            data: qtyData,
                            backgroundColor: chartType === 'line' ? 'rgba(36, 59, 100, 0.16)' : '#243B64',
                            borderColor: '#243B64',
                            borderWidth: 2,
                            borderRadius: chartType === 'bar' ? 6 : 0,
                            fill: chartType === 'line',
                            tension: 0.3,
                        }],
                    },
                    options: {
                        indexAxis: isHorizontal ? 'y' : 'x',
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: false,
                        onClick: (event, elements) => {
                            if (elements.length > 0) {
                                const idx = elements[0].index;
                                const item = this.topProducts[idx];
                                if (item && item.url) {
                                    window.location.href = item.url;
                                }
                            }
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: '#0a254d',
                                callbacks: {
                                    label: (ctx) => {
                                        const item = this.topProducts[ctx.dataIndex];
                                        return item
                                            ? `${item.qty_formatted} ${item.unit} • ${item.revenue_formatted} (Profit: ${item.profit_formatted})`
                                            : '';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: { beginAtZero: true },
                            y: { beginAtZero: true }
                        }
                    }
                });
                window.__topSellingChart.resize();
            },
            renderProfitChart() {
                if (window.__profitCategoryChart) {
                    window.__profitCategoryChart.destroy();
                    window.__profitCategoryChart = null;
                }
                const canvas = document.getElementById('profitCategoryChart');
                const cats = (this.profitIntel && this.profitIntel.by_category) ? this.profitIntel.by_category : [];
                if (!canvas || typeof window.Chart === 'undefined' || cats.length === 0) return;

                window.__profitCategoryChart = new window.Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: cats.map(c => c.category_name),
                        datasets: [
                            { label: 'Revenue', data: cats.map(c => c.revenue), backgroundColor: '#3b608e', borderRadius: 5 },
                            { label: 'Gross Profit', data: cats.map(c => c.profit), backgroundColor: '#1B7A43', borderRadius: 5 },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: false,
                        scales: { y: { beginAtZero: true } },
                    },
                });
                window.__profitCategoryChart.resize();
            },
            renderPatternCharts() {
                if (window.__salesHourlyChart) { window.__salesHourlyChart.destroy(); window.__salesHourlyChart = null; }
                if (window.__salesWeekdayChart) { window.__salesWeekdayChart.destroy(); window.__salesWeekdayChart = null; }
                if (typeof window.Chart === 'undefined' || !this.salesPatterns) return;

                const hCanvas = document.getElementById('salesHourlyChart');
                if (hCanvas) {
                    window.__salesHourlyChart = new window.Chart(hCanvas, {
                        type: 'bar',
                        data: {
                            labels: this.salesPatterns.hourly_labels || [],
                            datasets: [{ label: 'Transactions', data: this.salesPatterns.hourly_counts || [], backgroundColor: '#243B64', borderRadius: 4 }],
                        },
                        options: { responsive: true, maintainAspectRatio: false, animation: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
                    });
                    window.__salesHourlyChart.resize();
                }

                const wCanvas = document.getElementById('salesWeekdayChart');
                if (wCanvas) {
                    window.__salesWeekdayChart = new window.Chart(wCanvas, {
                        type: 'bar',
                        data: {
                            labels: this.salesPatterns.weekday_labels || [],
                            datasets: [{ label: 'Transactions', data: this.salesPatterns.weekday_counts || [], backgroundColor: '#3b608e', borderRadius: 5 }],
                        },
                        options: { responsive: true, maintainAspectRatio: false, animation: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
                    });
                    window.__salesWeekdayChart.resize();
                }
            },
            async refreshAnalytics() {
                if (this.isRefreshing) return;
                this.isRefreshing = true;
                try {
                    const params = new URLSearchParams({
                        period: this.period,
                        date_from: this.dateFrom,
                        date_to: this.dateTo,
                        dead_days: this.deadDays,
                        dead_category: this.deadCategory,
                        dead_status: this.deadStatus,
                        profit_category: this.profitCategory,
                    });
                    const res = await fetch(this.analyticsUrl + '?' + params.toString(), {
                        headers: { 'Accept': 'application/json' },
                    });
                    if (!res.ok) return;
                    const data = await res.json();
                    this.lastUpdated = data.last_updated;
                    this.periodSalesFormatted = data.periodSalesFormatted;
                    this.periodTransactionsCount = data.periodTransactionsCount;
                    this.periodProductsSoldFormatted = data.periodProductsSoldFormatted;
                    this.periodGrossProfitFormatted = data.periodGrossProfitFormatted;
                    this.outOfStockCount = data.outOfStockCount;
                    this.totalOutstandingDebtFormatted = data.totalOutstandingDebtFormatted;
                    this.topProducts = data.topProducts || [];
                    this.categories = data.topCategories || [];
                    this.debtTicker = data.debtTicker || [];
                    this.restockList = data.restockRecommendations || [];
                    this.deadStockList = data.deadStockItems || [];
                    this.profitIntel = data.profitIntelligence || {};
                    this.salesPatterns = data.salesPatterns || {};
                    if (!this.categories.some(c => c.category_id === this.selectedCatId)) {
                        this.selectedCatId = this.categories.length > 0 ? this.categories[0].category_id : null;
                    }
                    this.$nextTick(() => requestAnimationFrame(() => {
                        this.renderTopProductsChart();
                        if (this.intelTab === 'profit') this.renderProfitChart();
                        if (this.intelTab === 'patterns') this.renderPatternCharts();
                    }));
                } catch (e) {
                    console.error(e);
                } finally {
                    this.isRefreshing = false;
                }
            }
        }"
    @endif
    class="flex flex-col w-full gap-6 pb-4"
>
    @if ($isAdmin)
        <!-- Colored Outstanding Debt Running Bar / Ticker -->
        <div
            class="rounded-xl border overflow-hidden shadow-xs"
            :class="debtTicker.length > 0 ? 'bg-gradient-to-r from-[#B91C1C] via-[#DC2626] to-[#D97706] border-red-700 text-white' : 'bg-[#EBF7EE] border-emerald-200 text-[#1B7A43]'"
        >
            <template x-if="debtTicker.length === 0">
                <div class="px-4 py-2.5 flex items-center justify-between text-xs font-semibold">
                    <span class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">check_circle</span>
                        <span>No outstanding customer debts.</span>
                    </span>
                    <a href="{{ route('debts.index') }}" class="underline hover:opacity-80">Debt Report</a>
                </div>
            </template>

            <template x-if="debtTicker.length > 0">
                <div class="flex items-center">
                    <div class="px-3.5 py-2.5 bg-black/20 font-bold text-xs uppercase tracking-wider shrink-0 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px]">warning</span>
                        <span>Outstanding Debts</span>
                    </div>
                    <div class="flex-1 overflow-hidden py-2.5">
                        <div class="debt-ticker-track items-center gap-8 px-4 text-xs font-semibold">
                            <template x-for="pass in [1, 2]" :key="pass">
                                <div class="flex items-center gap-8 shrink-0">
                                    <template x-for="item in debtTicker" :key="pass + '-' + item.id">
                                        <a
                                            :href="item.url"
                                            class="inline-flex items-center gap-2 whitespace-nowrap hover:underline bg-white/15 px-3 py-1 rounded-full"
                                        >
                                            <span class="font-mono text-[11px] opacity-90" x-text="item.customer_code"></span>
                                            <span>&ndash;</span>
                                            <span x-text="item.name"></span>
                                            <span>&ndash;</span>
                                            <span class="font-bold" x-text="'Outstanding Debt: ' + item.outstanding_debt_formatted"></span>
                                        </a>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    @endif

    <!-- Greeting & Role Quick Actions Banner -->
    <section class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-surface-container-lowest p-4 sm:p-5 rounded-xl border border-divider/80 shadow-[0_1px_3px_0_rgba(37,43,54,0.04)]">
        <div class="flex flex-col gap-1 min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-xl sm:text-2xl font-semibold tracking-[-0.01em] text-primary">
                    {{ $greeting }}, {{ $user->name }}
                </h2>
                <x-status-badge :status="$user->role" :label="$user->roleLabel()" />
            </div>
            <p class="text-[13px] text-on-surface-variant">
                {{ $isAdmin ? "Here's an overview of your store performance and real-time operations today." : "Here's your store register overview and quick tools for today." }}
            </p>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 pt-1 text-xs font-medium text-outline">
                <span class="inline-flex items-center gap-1">
                    <span class="material-symbols-outlined text-[15px] text-secondary">storefront</span>
                    <span class="text-on-surface font-medium">Toko UMKM Retail</span>
                </span>
                <span class="text-outline-variant">&bull;</span>
                <span class="inline-flex items-center gap-1">
                    <span class="material-symbols-outlined text-[15px]">schedule</span>
                    {{ now()->format('l, d F Y') }}
                </span>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="flex flex-wrap items-center gap-2 shrink-0">
            @foreach ($quickActions as $index => $action)
                <a
                    href="{{ $action['route'] }}"
                    class="inline-flex items-center gap-2 px-3.5 h-9 rounded-lg text-[13px] font-medium transition-colors border {{ ! $isAdmin && $index === 0 ? 'bg-primary-container text-on-primary hover:bg-primary border-transparent' : 'bg-surface-container-low hover:bg-surface-container text-on-surface border-divider/70' }}"
                >
                    <span class="material-symbols-outlined text-[18px] {{ ! $isAdmin && $index === 0 ? 'text-on-primary' : 'text-secondary' }}">{{ $action['icon'] }}</span>
                    <span>{{ $action['title'] }}</span>
                </a>
            @endforeach
        </div>
    </section>

    <!-- 4 Core Metric KPI Summary Cards -->
    <section aria-label="Key Performance Indicators" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card 1: Today's Sales -->
        <x-kpi-card
            title="Today's Sales"
            :value="$todaySalesFormatted"
            tone="primary"
            footer-label="Yesterday"
            :footer-value="$yesterdaySalesFormatted"
        >
            <x-slot:icon>
                <span class="material-symbols-outlined text-[20px]">payments</span>
            </x-slot:icon>
            <x-slot:meta>
                @if ($salesDeltaPercent !== null)
                    <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[11px] font-semibold {{ $salesDeltaPercent >= 0 ? 'bg-[#EBF7EE] text-[#1B7A43]' : 'bg-[#FDECEB] text-[#C53030]' }}">
                        <span class="material-symbols-outlined text-[13px]">{{ $salesDeltaPercent >= 0 ? 'trending_up' : 'trending_down' }}</span>
                        {{ $salesDeltaPercent >= 0 ? '+' : '' }}{{ $salesDeltaPercent }}%
                    </span>
                    <span class="text-[11px] text-outline">vs. yesterday</span>
                @else
                    <span class="text-[11px] text-outline">Completed revenue today</span>
                @endif
            </x-slot:meta>
        </x-kpi-card>

        <!-- Card 2: Today's Transactions -->
        <x-kpi-card
            title="Today's Transactions"
            :value="number_format($todayTransactionsCount, 0, ',', '.')"
            tone="secondary"
            footer-label="QRIS : Cash"
            :footer-value="$todayQrisCount . ' : ' . $todayCashCount . ' trx'"
        >
            <x-slot:icon>
                <span class="material-symbols-outlined text-[20px]">point_of_sale</span>
            </x-slot:icon>
            <x-slot:meta>
                <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-secondary-fixed/45 text-on-secondary-fixed-variant text-[11px] font-semibold tabular-nums">
                    Avg. {{ $todayAverageBasketFormatted }}
                </span>
                <span class="text-[11px] text-outline">per order</span>
            </x-slot:meta>
        </x-kpi-card>

        <!-- Card 3: Total Products -->
        <x-kpi-card
            title="Total Products"
            :value="number_format($totalActiveProducts, 0, ',', '.')"
            tone="secondary"
            footer-label="Status"
            footer-value="Active SKUs"
        >
            <x-slot:icon>
                <span class="material-symbols-outlined text-[20px]">category</span>
            </x-slot:icon>
            <x-slot:meta>
                <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-surface-container text-on-surface-variant text-[11px] font-semibold">
                    {{ $totalCategoriesCount }} Categories
                </span>
                <span class="text-[11px] text-outline">active in catalog</span>
            </x-slot:meta>
        </x-kpi-card>

        <!-- Card 4: Low Stock Products -->
        <x-kpi-card
            title="Low Stock Products"
            :value="number_format($lowStockCount, 0, ',', '.')"
            :tone="$lowStockCount > 0 ? 'error' : 'emerald'"
            footer-label="Threshold"
            :footer-value="$lowStockCount > 0 ? 'Action needed' : 'Sufficient'"
            :footer-tone="$lowStockCount > 0 ? 'error' : 'success'"
        >
            <x-slot:icon>
                <span class="material-symbols-outlined text-[20px]">{{ $lowStockCount > 0 ? 'warning' : 'check_circle' }}</span>
            </x-slot:icon>
            <x-slot:meta>
                @if ($lowStockCount > 0)
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-[#FEF7EA] text-[#B57914] text-[11px] font-semibold">
                        Needs Restock
                    </span>
                    <span class="text-[11px] text-outline">{{ $criticalLowStockCount }} critical</span>
                @else
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-[#EBF7EE] text-[#1B7A43] text-[11px] font-semibold">
                        All Sufficient
                    </span>
                @endif
            </x-slot:meta>
        </x-kpi-card>
    </section>

    <!-- Sales Overview Section (7-Day Chart) -->
    <section class="flex flex-col rounded-xl bg-surface-container-lowest border border-divider/80 shadow-[0_1px_3px_0_rgba(37,43,54,0.04)] p-4 sm:p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-divider/60">
            <div>
                <h3 class="text-lg font-semibold text-primary tracking-[-0.01em]">Sales Overview</h3>
                <p class="text-[13px] text-on-surface-variant">
                    Daily completed revenue performance ({{ $salesChart['range_label'] }})
                </p>
            </div>
            <div class="flex items-center gap-3">
                <div class="text-left sm:text-right">
                    <span class="block text-[11px] font-semibold uppercase tracking-wider text-outline">7-Day Total</span>
                    <span class="text-base font-semibold text-primary tabular-nums">{{ $salesChart['total_formatted'] }}</span>
                </div>
                <span class="px-3 py-1 rounded-md bg-primary-container text-on-primary text-xs font-medium">
                    Last 7 Days
                </span>
            </div>
        </div>

        @if ($salesChart['has_data'])
            <div class="relative w-full h-[260px] mt-4">
                <canvas id="salesOverviewChart" aria-label="Sales performance chart for the last 7 days"></canvas>
            </div>

            <!-- 7-Day X-Axis Summary Strip -->
            <div class="grid grid-cols-7 gap-1 pt-3 mt-3 border-t border-divider/60 text-center">
                @foreach ($salesChart['labels'] as $idx => $label)
                    <div class="flex flex-col py-1 rounded {{ $idx === count($salesChart['labels']) - 1 ? 'bg-surface-container-low/80' : '' }}">
                        <span class="text-xs font-semibold {{ $idx === count($salesChart['labels']) - 1 ? 'text-primary' : 'text-on-surface' }}">
                            {{ $label }}
                        </span>
                        <span class="text-[11px] tabular-nums {{ $idx === count($salesChart['labels']) - 1 ? 'text-primary font-semibold' : 'text-outline' }}">
                            {{ $salesChart['short_values'][$idx] }}
                        </span>
                    </div>
                @endforeach
            </div>
        @else
            <x-empty-state
                title="No Sales Records Yet"
                description="Completed transactions from the last 7 days will automatically appear in this chart."
                tone="blue"
            >
                <x-slot:icon>
                    <span class="material-symbols-outlined text-[22px]">show_chart</span>
                </x-slot:icon>
            </x-empty-state>
        @endif
    </section>

    <!-- Operational Split: Recent Transactions & Low Stock Alert -->
    <section class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
        <!-- Left Column (8 cols): Recent Transactions -->
        <div class="lg:col-span-8 flex flex-col rounded-xl bg-surface-container-lowest border border-divider/80 shadow-[0_1px_3px_0_rgba(37,43,54,0.04)] p-4 sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-2 pb-4">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-lg font-semibold text-primary tracking-[-0.01em]">Recent Transactions</h3>
                        <span class="px-2 py-0.5 rounded-full bg-surface-container text-[11px] font-medium text-on-surface-variant">
                            Latest 5
                        </span>
                    </div>
                    <p class="text-[13px] text-on-surface-variant">Most recent transactions recorded in the system</p>
                </div>

                <a href="{{ route('transactions.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-secondary hover:text-primary transition-colors">
                    <span>View all transactions</span>
                    <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                </a>
            </div>

            @if ($recentTransactions->isEmpty())
                <x-empty-state
                    title="No Transactions Found"
                    description="Once transactions are recorded, the latest five orders will appear here."
                />
            @else
                <x-table :headers="['Transaction Code', 'Date', 'Cashier', 'Total', 'Payment Method', 'Status', 'Action']">
                    @foreach ($recentTransactions as $transaction)
                        <tr class="hover:bg-surface-container-low/50 transition-colors">
                            <td class="py-3 px-3 font-medium text-primary whitespace-nowrap">
                                <span class="inline-flex items-center gap-1.5 font-mono text-xs">
                                    <span class="material-symbols-outlined text-[15px] text-outline">receipt</span>
                                    {{ $transaction->transaction_code }}
                                </span>
                            </td>
                            <td class="py-3 px-3 text-on-surface-variant whitespace-nowrap">
                                {{ $transaction->created_at?->format('d M Y, H:i') }}
                            </td>
                            <td class="py-3 px-3 font-medium text-on-surface whitespace-nowrap">
                                {{ $transaction->user?->name ?? 'Unknown' }}
                            </td>
                            <td class="py-3 px-3 font-semibold text-on-surface tabular-nums whitespace-nowrap">
                                {{ $transaction->formattedTotal() }}
                            </td>
                            <td class="py-3 px-3 whitespace-nowrap">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium {{ strtolower((string) $transaction->payment_method) === 'qris' ? 'bg-secondary-container/40 text-on-secondary-container' : 'bg-surface-container text-on-surface-variant' }}">
                                    <span class="material-symbols-outlined text-[13px]">
                                        {{ strtolower((string) $transaction->payment_method) === 'qris' ? 'qr_code_2' : 'payments' }}
                                    </span>
                                    {{ $transaction->paymentMethodLabel() }}
                                </span>
                            </td>
                            <td class="py-3 px-3 whitespace-nowrap">
                                <x-status-badge
                                    :status="$transaction->transaction_status"
                                    :label="$transaction->statusLabel()"
                                />
                            </td>
                            <td class="py-3 px-3 whitespace-nowrap">
                                <x-button variant="ghost" size="sm" :href="route('transactions.show', $transaction)">
                                    View Details
                                </x-button>
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </div>

        <!-- Right Column (4 cols): Low Stock Alert -->
        <div class="lg:col-span-4 flex flex-col rounded-xl bg-surface-container-lowest border border-divider/80 shadow-[0_1px_3px_0_rgba(37,43,54,0.04)] p-4 sm:p-5">
            <div class="flex items-center justify-between gap-2 pb-2">
                <div class="flex items-center gap-2">
                    <span class="p-1.5 rounded-md bg-error-container/40 text-error">
                        <span class="material-symbols-outlined text-[18px]">inventory</span>
                    </span>
                    <h3 class="text-lg font-semibold text-primary tracking-[-0.01em]">Low Stock Alert</h3>
                </div>
                @if ($lowStockProducts->isNotEmpty())
                    <span class="px-2 py-0.5 rounded-full bg-error-container text-on-error-container text-[11px] font-semibold">
                        {{ $lowStockProducts->count() }} {{ $lowStockProducts->count() === 1 ? 'Item' : 'Items' }}
                    </span>
                @endif
            </div>

            <p class="text-[13px] text-on-surface-variant pb-4">
                Products at or below their minimum stock threshold.
            </p>

            @if ($lowStockProducts->isEmpty())
                <x-empty-state
                    title="Stock Levels Are Sufficient"
                    description="All active products are currently above their minimum stock thresholds."
                    tone="emerald"
                >
                    <x-slot:icon>
                        <span class="material-symbols-outlined text-[22px]">check_circle</span>
                    </x-slot:icon>
                </x-empty-state>
            @else
                <div class="flex flex-col gap-3">
                    @foreach ($lowStockProducts as $product)
                        @php
                            $status = $product->stockStatus();
                            $isCritical = in_array($status, ['out_of_stock', 'critical'], true);
                            $percent = $product->minimum_stock > 0
                                ? min(100, max(4, (int) round(($product->stock / $product->minimum_stock) * 100)))
                                : 0;
                            if ($product->stock <= 0) {
                                $percent = 0;
                            }
                        @endphp
                        <div class="p-3 rounded-lg flex flex-col gap-1.5 {{ $isCritical ? 'bg-error-container/25 border border-error-container' : 'bg-surface-container-low border border-divider/60' }}">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-semibold truncate {{ $isCritical ? 'text-error' : 'text-on-surface' }}">
                                    {{ $product->product_name }}
                                </span>
                                <x-status-badge :status="$status" />
                            </div>
                            <div class="flex items-center justify-between text-[11px] font-medium {{ $isCritical ? 'text-error/85' : 'text-outline' }}">
                                <span>
                                    Current: <strong class="{{ $isCritical ? 'text-error' : 'text-on-surface' }} tabular-nums">{{ $product->stock }} {{ $product->unit }}</strong>
                                </span>
                                <span class="tabular-nums">Min: {{ $product->minimum_stock }} {{ $product->unit }}</span>
                            </div>
                            <div class="w-full h-1.5 rounded-full overflow-hidden {{ $isCritical ? 'bg-error-container/70' : 'bg-surface-container-highest' }}">
                                <div
                                    class="h-full rounded-full {{ $isCritical ? 'bg-[#C53030]' : 'bg-[#B57914]' }}"
                                    style="width: {{ $percent }}%"
                                ></div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($isAdmin)
                    <div class="pt-4 mt-2 border-t border-divider/60">
                        <a href="{{ route('products.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-secondary hover:text-primary transition-colors">
                            <span>View product inventory</span>
                            <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                        </a>
                    </div>
                @endif
            @endif
        </div>
    </section>

    @if (! $isAdmin && $quickProducts->isNotEmpty())
        <!-- Cashier Fast Product & Price Check (Real DB Catalog Lookup) -->
        <section
            x-data="{ search: '' }"
            class="rounded-xl bg-surface-container-lowest border border-divider/80 shadow-[0_1px_3px_0_rgba(37,43,54,0.04)] p-4 sm:p-5"
        >
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4">
                <div>
                    <h3 class="text-base font-semibold text-primary">Fast Product &amp; Price Check</h3>
                    <p class="text-[13px] text-on-surface-variant">Quickly check item prices and stock availability from catalog</p>
                </div>
                <div class="relative w-full sm:w-80">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[19px]">search</span>
                    <input
                        id="productQuickSearch"
                        x-model="search"
                        type="text"
                        placeholder="Search product or SKU..."
                        class="w-full bg-surface-container-low text-on-surface text-[13px] pl-10 pr-4 py-2 rounded-lg border border-divider/80 focus:outline-none focus:bg-surface-container-lowest focus:border-primary-container focus:ring-2 focus:ring-primary-fixed transition-all"
                    >
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 max-h-72 overflow-y-auto pr-1">
                @foreach ($quickProducts as $product)
                    <div
                        x-show="search.trim() === '' || '{{ strtolower($product->product_name . ' ' . $product->product_code) }}'.includes(search.trim().toLowerCase())"
                        class="flex items-center justify-between gap-3 p-3 rounded-lg bg-surface-container-low/60 border border-divider/60 hover:bg-surface-container-low transition-colors"
                    >
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-on-surface truncate">{{ $product->product_name }}</p>
                            <p class="text-xs font-medium text-primary tabular-nums">{{ \App\Support\Format::rupiah($product->price) }} <span class="text-outline font-normal">/ {{ $product->unit }}</span></p>
                        </div>
                        <div class="shrink-0 text-right">
                            @if ($product->isLowStock())
                                <x-status-badge :status="$product->stockStatus()" :label="'Stock: ' . $product->stock" />
                            @else
                                <x-status-badge status="sufficient" :label="'Stock: ' . $product->stock" />
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($isAdmin)
        <!-- Admin Analytics Date Filter & Summary -->
        <section class="rounded-xl bg-surface-container-lowest border border-divider/80 shadow-[0_1px_3px_0_rgba(37,43,54,0.04)] p-4 sm:p-5 space-y-5">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 pb-4 border-b border-divider">
                <div>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h3 class="text-lg font-semibold text-primary tracking-[-0.01em]">Sales &amp; Profit Analytics</h3>
                        <span class="inline-flex items-center gap-1 rounded-full bg-surface-container-low px-2.5 py-0.5 text-[11px] font-medium text-outline">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            Last updated: <strong class="text-on-surface tabular-nums" x-text="lastUpdated"></strong>
                        </span>
                    </div>
                    <p class="text-xs text-on-surface-variant mt-0.5">Period: <strong>{{ $periodLabel }}</strong> &bull; Auto-refreshes every 60s</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        @click="refreshAnalytics()"
                        :disabled="isRefreshing"
                        class="inline-flex items-center gap-1 rounded-lg border border-divider bg-white px-3 h-8 text-xs font-semibold text-primary hover:bg-surface-container-low disabled:opacity-50 cursor-pointer"
                    >
                        <span class="material-symbols-outlined text-[16px]" :class="isRefreshing ? 'animate-spin' : ''">refresh</span>
                        <span x-text="isRefreshing ? 'Refreshing...' : 'Refresh Now'"></span>
                    </button>

                    <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-center gap-2">
                        @foreach (['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month'] as $key => $label)
                            <x-button size="sm" :variant="$period === $key ? 'primary' : 'secondary'" :href="route('dashboard', ['period' => $key])">
                                {{ $label }}
                            </x-button>
                        @endforeach
                        <input type="hidden" name="period" value="custom">
                        <input type="date" name="date_from" value="{{ $dateFrom }}" class="rounded-lg border border-divider px-2.5 py-1.5 text-xs">
                        <input type="date" name="date_to" value="{{ $dateTo }}" class="rounded-lg border border-divider px-2.5 py-1.5 text-xs">
                        <x-button type="submit" size="sm" :variant="$period === 'custom' ? 'primary' : 'secondary'">Apply</x-button>
                    </form>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
                <div class="rounded-lg border border-divider/70 bg-surface-container-low/40 p-3.5">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-outline">Period Revenue</span>
                    <p class="mt-1 text-lg font-bold text-primary tabular-nums" x-text="periodSalesFormatted"></p>
                </div>
                <div class="rounded-lg border border-divider/70 bg-surface-container-low/40 p-3.5">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-outline">Transactions</span>
                    <p class="mt-1 text-lg font-bold text-on-surface tabular-nums" x-text="periodTransactionsCount"></p>
                </div>
                <div class="rounded-lg border border-divider/70 bg-surface-container-low/40 p-3.5">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-outline">Products Sold</span>
                    <p class="mt-1 text-lg font-bold text-on-surface tabular-nums" x-text="periodProductsSoldFormatted"></p>
                </div>
                <div class="rounded-lg border border-emerald-200/80 bg-emerald-50/50 p-3.5">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-emerald-800">Est. Gross Profit</span>
                    <p class="mt-1 text-lg font-bold text-emerald-700 tabular-nums" x-text="periodGrossProfitFormatted"></p>
                </div>
                <div class="rounded-lg border border-red-200/80 bg-red-50/50 p-3.5">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-red-800">Out of Stock / Debts</span>
                    <p class="mt-1 text-sm font-bold text-red-700 tabular-nums">
                        <span x-text="outOfStockCount"></span> empty &bull; <span x-text="totalOutstandingDebtFormatted"></span>
                    </p>
                </div>
            </div>

            <!-- Interactive Top 10 Products (Chart Options + Table) & Interactive Category Drill-Down -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                <!-- Top 10 Selling Products -->
                <div class="lg:col-span-6 rounded-xl border border-divider/80 p-4 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h4 class="text-sm font-semibold text-primary">Top 10 Best-Selling Products</h4>
                        <div class="flex flex-wrap items-center gap-1">
                            <button type="button" @click="setChartMode('bar')" :class="chartMode === 'bar' ? 'bg-primary-container text-on-primary' : 'bg-surface-container-low text-on-surface'" class="rounded-md px-2 py-1 text-[11px] font-semibold cursor-pointer">Bar</button>
                            <button type="button" @click="setChartMode('horizontalBar')" :class="chartMode === 'horizontalBar' ? 'bg-primary-container text-on-primary' : 'bg-surface-container-low text-on-surface'" class="rounded-md px-2 py-1 text-[11px] font-semibold cursor-pointer">Horizontal</button>
                            <button type="button" @click="setChartMode('line')" :class="chartMode === 'line' ? 'bg-primary-container text-on-primary' : 'bg-surface-container-low text-on-surface'" class="rounded-md px-2 py-1 text-[11px] font-semibold cursor-pointer">Line</button>
                            <button type="button" @click="setChartMode('table')" :class="chartMode === 'table' ? 'bg-primary-container text-on-primary' : 'bg-surface-container-low text-on-surface'" class="rounded-md px-2 py-1 text-[11px] font-semibold cursor-pointer">Table</button>
                        </div>
                    </div>

                    <template x-if="topProducts.length === 0">
                        <div class="py-8 text-center text-xs text-outline">No completed product sales recorded in this period.</div>
                    </template>

                    <div x-show="topProducts.length > 0 && chartMode !== 'table'" class="space-y-2">
                        <div class="relative h-[260px] w-full">
                            <canvas id="topSellingProductsChart"></canvas>
                        </div>
                        <p class="text-[11px] text-outline">Tip: Click any bar or point on the chart to inspect product details.</p>
                    </div>

                    <div x-show="topProducts.length > 0 && chartMode === 'table'" x-cloak class="space-y-2">
                        <input
                            type="text"
                            x-model="productFilter"
                            placeholder="Filter top products..."
                            class="rounded-md border border-divider px-2.5 py-1.5 text-xs w-full"
                        >
                        <div class="divide-y divide-divider/60 max-h-[260px] overflow-y-auto">
                            <template x-for="(tp, idx) in filteredTopProducts" :key="tp.name + idx">
                                <a :href="tp.url || '#'" class="py-2.5 flex items-center justify-between gap-3 text-xs hover:bg-surface-container-low/40 px-1 rounded">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <span class="h-6 w-6 rounded-full bg-surface-container-low text-primary font-bold flex items-center justify-center shrink-0 tabular-nums" x-text="idx + 1"></span>
                                        <div class="min-w-0">
                                            <p class="font-semibold text-on-surface truncate" x-text="tp.name"></p>
                                            <p class="text-[11px] text-outline tabular-nums" x-text="tp.qty_formatted + ' ' + tp.unit + ' sold'"></p>
                                        </div>
                                    </div>
                                    <div class="text-right tabular-nums shrink-0">
                                        <p class="font-semibold text-primary" x-text="tp.revenue_formatted"></p>
                                        <p class="text-[10px] text-emerald-700" x-text="'Profit: ' + tp.profit_formatted"></p>
                                    </div>
                                </a>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Interactive Top Categories + Drill-Down -->
                <div class="lg:col-span-6 rounded-xl border border-divider/80 p-4 space-y-3">
                    <div>
                        <h4 class="text-sm font-semibold text-primary">Top Selling Categories (Click to Drill Down)</h4>
                        <p class="text-[11px] text-outline">Click any category to inspect products sold, revenue, and profit.</p>
                    </div>

                    <template x-if="categories.length === 0">
                        <div class="py-8 text-center text-xs text-outline">No category sales recorded in this period.</div>
                    </template>

                    <div x-show="categories.length > 0" class="space-y-3">
                        <div class="flex flex-wrap gap-2">
                            <template x-for="cat in categories" :key="cat.category_id">
                                <button
                                    type="button"
                                    @click="selectedCatId = cat.category_id"
                                    :class="selectedCatId === cat.category_id ? 'bg-primary-container text-on-primary border-primary-container' : 'bg-white text-on-surface border-divider hover:bg-surface-container-low'"
                                    class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition-colors cursor-pointer"
                                >
                                    <span x-text="cat.category_name"></span>
                                    <span class="ml-1 opacity-80 tabular-nums" x-text="'(' + formatRp(cat.total_revenue) + ')'"></span>
                                </button>
                            </template>
                        </div>

                        <template x-if="selectedCategory">
                            <div class="rounded-lg border border-divider bg-surface-container-low/40 p-3 space-y-2">
                                <div class="flex items-center justify-between text-xs border-b border-divider pb-2">
                                    <strong class="text-primary" x-text="selectedCategory.category_name + ' — Products Sold'"></strong>
                                    <span class="text-emerald-700 font-semibold tabular-nums" x-text="'Est. Profit: ' + formatRp(selectedCategory.total_profit)"></span>
                                </div>
                                <div class="divide-y divide-divider/60 max-h-56 overflow-y-auto">
                                    <template x-for="prod in selectedCategory.products" :key="prod.name + prod.unit">
                                        <div class="py-2 flex items-center justify-between gap-2 text-xs">
                                            <div>
                                                <p class="font-medium text-on-surface" x-text="prod.name"></p>
                                                <p class="text-[11px] text-outline tabular-nums" x-text="prod.qty + ' ' + prod.unit"></p>
                                            </div>
                                            <div class="text-right tabular-nums">
                                                <p class="font-semibold text-primary" x-text="formatRp(prod.revenue)"></p>
                                                <p class="text-[10px] text-emerald-700" x-text="'Profit: ' + formatRp(prod.profit)"></p>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </section>

        <!-- Weekly AI Business Summary & Outstanding Debts -->
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Weekly AI Business Summary (7 cols) -->
            <div class="lg:col-span-7 rounded-xl bg-surface-container-lowest border border-divider/80 shadow-[0_1px_3px_0_rgba(37,43,54,0.04)] p-5 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-divider">
                    <div class="flex items-center gap-2">
                        <span class="p-1.5 rounded-lg bg-surface-container-low text-primary">
                            <span class="material-symbols-outlined text-[20px]">auto_awesome</span>
                        </span>
                        <div>
                            <h3 class="text-base font-semibold text-primary">Weekly AI Business Summary &amp; Recommendations</h3>
                            <p class="text-[11px] text-outline">
                                Observed data ({{ $weeklyInsight?->week_start?->format('d M') }} – {{ $weeklyInsight?->week_end?->format('d M Y') }})
                                &bull; Source: <strong>{{ $weeklyInsight?->source === 'ai' ? 'AI Analysis' : 'Data-Driven Summary' }}</strong>
                            </p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('dashboard.insight.regenerate') }}">
                        @csrf
                        <input type="hidden" name="period" value="{{ $period }}">
                        <input type="hidden" name="date_from" value="{{ $dateFrom }}">
                        <input type="hidden" name="date_to" value="{{ $dateTo }}">
                        <x-button type="submit" size="sm" variant="secondary">
                            <span class="material-symbols-outlined text-[16px]">refresh</span>
                            <span>Regenerate</span>
                        </x-button>
                    </form>
                </div>

                @if ($weeklyInsight)
                    <div class="rounded-lg bg-surface-container-low/50 border border-divider/70 p-4 text-sm text-on-surface leading-relaxed">
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-secondary mb-1">Observed Business Performance</span>
                        {{ $weeklyInsight->summary_text }}
                    </div>

                    @if (! empty($weeklyInsight->recommendations))
                        <div>
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-outline mb-2">Actionable Recommendations for Next Week</h4>
                            <ul class="space-y-2">
                                @foreach ($weeklyInsight->recommendations as $rec)
                                    <li class="flex items-start gap-2 text-xs text-on-surface">
                                        <span class="material-symbols-outlined text-[16px] text-emerald-600 shrink-0 mt-0.5">task_alt</span>
                                        <span>{{ $rec }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endif
            </div>

            <!-- Outstanding Customer Debts (5 cols) -->
            <div class="lg:col-span-5 rounded-xl bg-surface-container-lowest border border-divider/80 shadow-[0_1px_3px_0_rgba(37,43,54,0.04)] p-5 space-y-4">
                <div class="flex items-center justify-between gap-2 pb-3 border-b border-divider">
                    <div>
                        <h3 class="text-base font-semibold text-primary">Outstanding Customer Debts</h3>
                        <p class="text-[11px] text-outline">Total unpaid receivables: <strong class="text-red-600">{{ $totalOutstandingDebtFormatted }}</strong></p>
                    </div>
                    <x-button size="sm" variant="secondary" :href="route('debts.index')">Manage Debts</x-button>
                </div>

                @if ($outstandingDebts->isEmpty())
                    <x-empty-state title="No Unpaid Debts" description="All customer transactions are settled in full." tone="emerald" />
                @else
                    <div class="divide-y divide-divider/60">
                        @foreach ($outstandingDebts as $debtTrx)
                            <div class="py-2.5 flex items-center justify-between gap-2 text-xs">
                                <div class="min-w-0">
                                    <a href="{{ $debtTrx->customer ? route('customers.show', $debtTrx->customer) : route('transactions.show', $debtTrx) }}" class="font-semibold text-primary hover:underline truncate block">
                                        {{ $debtTrx->customer?->name ?? 'Customer' }}
                                    </a>
                                    <span class="font-mono text-[11px] text-outline">{{ $debtTrx->transaction_code }} &bull; {{ $debtTrx->paymentStatusLabel() }}</span>
                                </div>
                                <div class="text-right tabular-nums shrink-0">
                                    <span class="font-bold text-red-600 block">{{ \App\Support\Format::rupiah($debtTrx->outstanding_balance) }}</span>
                                    <a href="{{ route('transactions.show', $debtTrx) }}" class="text-[11px] text-secondary hover:underline">Pay / Detail</a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        <!-- Smart Retail Intelligence (Restock, Dead Stock, Profit Intelligence, Sales Patterns) -->
        <section class="rounded-xl bg-surface-container-lowest border border-divider/80 shadow-[0_1px_3px_0_rgba(37,43,54,0.04)] p-4 sm:p-5 space-y-5">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 pb-4 border-b border-divider">
                <div>
                    <h3 class="text-lg font-semibold text-primary tracking-[-0.01em]">Smart Retail Intelligence</h3>
                    <p class="text-xs text-on-surface-variant">Data-driven restock planning, dead-stock detection, profit margins, and peak sales patterns.</p>
                </div>

                <div class="flex flex-wrap items-center gap-1.5">
                    <button type="button" @click="setIntelTab('restock')" :class="intelTab === 'restock' ? 'bg-primary-container text-on-primary' : 'bg-surface-container-low text-on-surface'" class="rounded-lg px-3 py-1.5 text-xs font-semibold cursor-pointer">
                        Restock (<span x-text="restockList.length"></span>)
                    </button>
                    <button type="button" @click="setIntelTab('deadstock')" :class="intelTab === 'deadstock' ? 'bg-primary-container text-on-primary' : 'bg-surface-container-low text-on-surface'" class="rounded-lg px-3 py-1.5 text-xs font-semibold cursor-pointer">
                        Slow / Dead Stock (<span x-text="deadStockList.length"></span>)
                    </button>
                    <button type="button" @click="setIntelTab('profit')" :class="intelTab === 'profit' ? 'bg-primary-container text-on-primary' : 'bg-surface-container-low text-on-surface'" class="rounded-lg px-3 py-1.5 text-xs font-semibold cursor-pointer">
                        Profit Intelligence
                    </button>
                    <button type="button" @click="setIntelTab('patterns')" :class="intelTab === 'patterns' ? 'bg-primary-container text-on-primary' : 'bg-surface-container-low text-on-surface'" class="rounded-lg px-3 py-1.5 text-xs font-semibold cursor-pointer">
                        Sales Patterns
                    </button>
                </div>
            </div>

            <!-- Tab 1: Smart Restock Recommendations -->
            <div x-show="intelTab === 'restock'" class="space-y-3">
                <template x-if="restockList.length === 0">
                    <div class="py-8 text-center text-xs text-emerald-700 font-medium">All active products have healthy stock coverage above their reorder points.</div>
                </template>
                <div x-show="restockList.length > 0" class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-surface-container-low text-outline uppercase text-[11px]">
                                <th class="py-2.5 px-3">Priority</th>
                                <th class="py-2.5 px-3">Product</th>
                                <th class="py-2.5 px-3">Current Stock</th>
                                <th class="py-2.5 px-3">Avg Daily Sales</th>
                                <th class="py-2.5 px-3">Coverage</th>
                                <th class="py-2.5 px-3">Recommended Restock</th>
                                <th class="py-2.5 px-3">Reason</th>
                                <th class="py-2.5 px-3">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-divider/70">
                            <template x-for="r in restockList" :key="r.product_id">
                                <tr class="hover:bg-surface-container-low/30">
                                    <td class="py-2.5 px-3">
                                        <span
                                            class="inline-flex rounded px-2 py-0.5 text-[10px] font-bold uppercase"
                                            :class="r.priority === 'critical' ? 'bg-[#FDECEB] text-[#C53030]' : (r.priority === 'urgent' ? 'bg-[#FEF7EA] text-[#B57914]' : 'bg-surface-container-low text-primary')"
                                            x-text="r.priority"
                                        ></span>
                                    </td>
                                    <td class="py-2.5 px-3 font-semibold text-primary" x-text="r.product_name"></td>
                                    <td class="py-2.5 px-3 tabular-nums" x-text="r.current_stock_formatted"></td>
                                    <td class="py-2.5 px-3 tabular-nums" x-text="r.avg_daily_sales + '/day'"></td>
                                    <td class="py-2.5 px-3 tabular-nums" x-text="r.days_coverage !== null ? (r.days_coverage + ' days') : '—'"></td>
                                    <td class="py-2.5 px-3 font-bold text-emerald-700 tabular-nums" x-text="r.recommended_display"></td>
                                    <td class="py-2.5 px-3 text-on-surface-variant max-w-xs" x-text="r.reason"></td>
                                    <td class="py-2.5 px-3">
                                        <a :href="r.url" class="text-secondary font-semibold hover:underline">Review / Restock</a>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 2: Dead Stock & Slow-Moving -->
            <div x-show="intelTab === 'deadstock'" x-cloak class="space-y-4">
                <div class="flex flex-wrap items-center gap-2.5">
                    <select x-model.number="deadDays" @change="refreshAnalytics()" class="rounded-lg border border-divider px-3 py-1.5 text-xs">
                        <option value="7">Last 7 Days</option>
                        <option value="14">Last 14 Days</option>
                        <option value="30">Last 30 Days</option>
                    </select>
                    <select x-model="deadCategory" @change="refreshAnalytics()" class="rounded-lg border border-divider px-3 py-1.5 text-xs">
                        <option value="">All Categories</option>
                        @foreach ($allCategories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                    <select x-model="deadStatus" @change="refreshAnalytics()" class="rounded-lg border border-divider px-3 py-1.5 text-xs">
                        <option value="all">Dead &amp; Slow-Moving</option>
                        <option value="dead_stock">Dead Stock Only (0 sold)</option>
                        <option value="slow_moving">Slow-Moving Only</option>
                    </select>
                </div>

                <template x-if="deadStockList.length === 0">
                    <div class="py-8 text-center text-xs text-emerald-700 font-medium">No slow-moving or dead stock detected for the selected filter.</div>
                </template>
                <div x-show="deadStockList.length > 0" class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-surface-container-low text-outline uppercase text-[11px]">
                                <th class="py-2.5 px-3">Status</th>
                                <th class="py-2.5 px-3">Product</th>
                                <th class="py-2.5 px-3">Stock</th>
                                <th class="py-2.5 px-3">Qty Sold</th>
                                <th class="py-2.5 px-3">Last Sale</th>
                                <th class="py-2.5 px-3">Stock Value (Cost)</th>
                                <th class="py-2.5 px-3">Suggested Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-divider/70">
                            <template x-for="d in deadStockList" :key="d.product_id">
                                <tr class="hover:bg-surface-container-low/30">
                                    <td class="py-2.5 px-3">
                                        <span
                                            class="inline-flex rounded px-2 py-0.5 text-[10px] font-bold uppercase"
                                            :class="d.status === 'dead_stock' ? 'bg-[#FDECEB] text-[#C53030]' : 'bg-[#FEF7EA] text-[#B57914]'"
                                            x-text="d.status === 'dead_stock' ? 'Dead Stock' : 'Slow Moving'"
                                        ></span>
                                    </td>
                                    <td class="py-2.5 px-3 font-semibold text-primary">
                                        <a :href="d.url" class="hover:underline" x-text="d.product_name"></a>
                                    </td>
                                    <td class="py-2.5 px-3 tabular-nums" x-text="d.stock_formatted"></td>
                                    <td class="py-2.5 px-3 tabular-nums" x-text="d.qty_sold_formatted"></td>
                                    <td class="py-2.5 px-3" x-text="d.last_sale_date"></td>
                                    <td class="py-2.5 px-3 tabular-nums font-semibold" x-text="d.inventory_value_formatted"></td>
                                    <td class="py-2.5 px-3 text-on-surface-variant" x-text="d.suggested_action"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 3: Profit Intelligence -->
            <div x-show="intelTab === 'profit'" x-cloak class="space-y-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-xs text-on-surface-variant">Calculated from historical transaction cost &amp; profit snapshots (excludes cancelled sales).</span>
                    <select x-model="profitCategory" @change="refreshAnalytics()" class="rounded-lg border border-divider px-3 py-1.5 text-xs">
                        <option value="">All Categories</option>
                        @foreach ($allCategories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="rounded-xl border border-divider/80 p-4">
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-outline mb-2">Revenue vs Gross Profit by Category</h4>
                    <div class="relative h-[220px] w-full">
                        <canvas id="profitCategoryChart"></canvas>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="rounded-lg border border-divider/80 p-3.5 space-y-2">
                        <h4 class="text-xs font-semibold text-primary uppercase">Top Profit Products</h4>
                        <template x-for="item in (profitIntel.top_profit || [])" :key="'tp-' + item.name + item.unit">
                            <div class="flex justify-between text-xs py-1 border-b border-divider/50 last:border-0">
                                <span class="truncate pr-2" x-text="item.name + ' (' + item.qty_formatted + ' ' + item.unit + ')'"></span>
                                <span class="font-bold text-emerald-700 tabular-nums shrink-0" x-text="item.profit_formatted + ' (' + item.margin_pct + '%)'"></span>
                            </div>
                        </template>
                    </div>
                    <div class="rounded-lg border border-divider/80 p-3.5 space-y-2">
                        <h4 class="text-xs font-semibold text-primary uppercase">Lowest Margin Products</h4>
                        <template x-for="item in (profitIntel.lowest_margin || [])" :key="'lm-' + item.name + item.unit">
                            <div class="flex justify-between text-xs py-1 border-b border-divider/50 last:border-0">
                                <span class="truncate pr-2" x-text="item.name + ' (Rev: ' + item.revenue_formatted + ')'"></span>
                                <span class="font-bold text-amber-700 tabular-nums shrink-0" x-text="item.margin_pct + '% (' + item.profit_formatted + ')'"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Tab 4: Sales Pattern Analysis -->
            <div x-show="intelTab === 'patterns'" x-cloak class="space-y-4">
                <div class="rounded-lg bg-surface-container-low/60 border border-divider/80 p-3.5 text-xs text-on-surface">
                    <strong class="text-primary">Peak Pattern Insight:</strong>
                    <span x-text="salesPatterns.insight"></span>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                    <div class="rounded-lg border border-divider p-3">
                        <span class="text-outline block">Busiest Window</span>
                        <strong class="text-sm text-primary tabular-nums" x-text="salesPatterns.busiest_window"></strong>
                    </div>
                    <div class="rounded-lg border border-divider p-3">
                        <span class="text-outline block">Busiest Day</span>
                        <strong class="text-sm text-primary" x-text="salesPatterns.busiest_day"></strong>
                    </div>
                    <div class="rounded-lg border border-divider p-3">
                        <span class="text-outline block">Avg Trx / Active Hour</span>
                        <strong class="text-sm text-primary tabular-nums" x-text="salesPatterns.avg_per_hour"></strong>
                    </div>
                    <div class="rounded-lg border border-divider p-3">
                        <span class="text-outline block">Weekday vs Weekend</span>
                        <strong class="text-sm text-primary tabular-nums" x-text="(salesPatterns.weekday_total?.count || 0) + ' vs ' + (salesPatterns.weekend_total?.count || 0) + ' trx'"></strong>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="rounded-xl border border-divider/80 p-4">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-outline mb-2">Hourly Transaction Volume (WIB)</h4>
                        <div class="relative h-[220px] w-full"><canvas id="salesHourlyChart"></canvas></div>
                    </div>
                    <div class="rounded-xl border border-divider/80 p-4">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-outline mb-2">Day-of-Week Transaction Volume</h4>
                        <div class="relative h-[220px] w-full"><canvas id="salesWeekdayChart"></canvas></div>
                    </div>
                </div>
            </div>
        </section>
    @endif
</div>
@endsection

@push('scripts')
@if ($salesChart['has_data'])
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const canvas = document.getElementById('salesOverviewChart');
        if (!canvas || typeof window.Chart === 'undefined') {
            return;
        }

        const labels = @json($salesChart['labels']);
        const values = @json($salesChart['values']);

        const formatRupiah = (amount) => {
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(amount));
        };

        const ctx = canvas.getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 240);
        gradient.addColorStop(0, 'rgba(36, 59, 100, 0.22)');
        gradient.addColorStop(1, 'rgba(36, 59, 100, 0.0)');

        new window.Chart(canvas, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Daily Sales',
                    data: values,
                    borderColor: '#243B64',
                    backgroundColor: gradient,
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#243B64',
                    pointBorderWidth: 2,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        backgroundColor: '#0a254d',
                        padding: 10,
                        cornerRadius: 8,
                        callbacks: {
                            label: (context) => ' Sales: ' + formatRupiah(context.parsed.y ?? 0),
                        },
                    },
                },
                scales: {
                    x: {
                        grid: {
                            display: false,
                        },
                        ticks: {
                            color: '#74777f',
                            font: {
                                size: 12,
                            },
                        },
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#dde2f1',
                            tickBorderDash: [3, 3],
                        },
                        border: {
                            display: false,
                            dash: [3, 3],
                        },
                        ticks: {
                            color: '#74777f',
                            font: {
                                size: 11,
                            },
                            callback: (value) => formatRupiah(value),
                        },
                    },
                },
            },
        });
    });
</script>
@endif
@endpush
