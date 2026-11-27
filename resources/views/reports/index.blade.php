@extends('layouts.app', [
    'pageTitle' => 'Sales Reports',
    'breadcrumb' => 'Sales Reports',
])

@section('title', 'Sales Reports')

@section('content')
<div class="space-y-6">
    <!-- Filters -->
    @php
        $todayStr = \Carbon\Carbon::today()->format('Y-m-d');
        $presets = [
            ['label' => 'Today', 'from' => $todayStr, 'to' => $todayStr],
            ['label' => '7 Days', 'from' => \Carbon\Carbon::today()->subDays(6)->format('Y-m-d'), 'to' => $todayStr],
            ['label' => '1 Month', 'from' => \Carbon\Carbon::today()->subDays(29)->format('Y-m-d'), 'to' => $todayStr],
            ['label' => '1 Year', 'from' => \Carbon\Carbon::today()->subDays(364)->format('Y-m-d'), 'to' => $todayStr],
        ];
        $sharedParams = array_filter([
            'status' => $status,
            'payment_method' => $paymentMethod,
            'user_id' => $cashierId,
        ], fn ($v) => $v !== null && $v !== '');
    @endphp
    <div class="rounded-xl bg-surface-container-lowest border border-divider/80 p-5 space-y-4">
        <div class="flex flex-wrap items-center gap-2 pb-3 border-b border-divider/70">
            <span class="text-xs font-semibold text-outline mr-1">Quick Period:</span>
            @foreach ($presets as $preset)
                @php
                    $isActivePreset = $dateFrom === $preset['from'] && $dateTo === $preset['to'];
                @endphp
                <x-button
                    size="sm"
                    :variant="$isActivePreset ? 'primary' : 'secondary'"
                    :href="route('reports.index', array_merge($sharedParams, ['date_from' => $preset['from'], 'date_to' => $preset['to']]))"
                >
                    {{ $preset['label'] }}
                </x-button>
            @endforeach
        </div>

        <form method="GET" action="{{ route('reports.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
            <div>
                <label class="block text-xs font-semibold text-on-surface mb-1">From Date</label>
                <input type="date" name="date_from" value="{{ $dateFrom }}" class="w-full rounded-lg border border-divider px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-on-surface mb-1">To Date</label>
                <input type="date" name="date_to" value="{{ $dateTo }}" class="w-full rounded-lg border border-divider px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-on-surface mb-1">Status</label>
                <select name="status" class="w-full rounded-lg border border-divider px-3 py-2 text-sm">
                    <option value="completed" @selected($status === 'completed')>Completed</option>
                    <option value="all" @selected($status === 'all')>All Statuses</option>
                    <option value="cancelled" @selected($status === 'cancelled')>Cancelled</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-on-surface mb-1">Payment</label>
                <select name="payment_method" class="w-full rounded-lg border border-divider px-3 py-2 text-sm">
                    <option value="all" @selected($paymentMethod === 'all')>All Methods</option>
                    <option value="cash" @selected($paymentMethod === 'cash')>Cash</option>
                    <option value="qris" @selected($paymentMethod === 'qris')>QRIS</option>
                    <option value="other" @selected($paymentMethod === 'other')>Other</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-on-surface mb-1">Cashier</label>
                <select name="user_id" class="w-full rounded-lg border border-divider px-3 py-2 text-sm">
                    <option value="">All Cashiers</option>
                    @foreach ($cashiers as $cashier)
                        <option value="{{ $cashier->id }}" @selected((string) $cashierId === (string) $cashier->id)>{{ $cashier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-wrap items-center gap-2 lg:col-span-6 pt-1">
                <x-button type="submit" variant="primary">Generate Report</x-button>
                <x-button
                    variant="secondary"
                    :href="route('reports.print', request()->only(['date_from', 'date_to', 'status', 'payment_method', 'user_id']))"
                    target="_blank"
                >
                    <span class="material-symbols-outlined text-[18px]">print</span>
                    <span>Print Report</span>
                </x-button>
                <x-button
                    variant="secondary"
                    :href="route('reports.export', request()->only(['date_from', 'date_to', 'status', 'payment_method', 'user_id']))"
                >
                    <span class="material-symbols-outlined text-[18px]">download</span>
                    <span>Download CSV</span>
                </x-button>
            </div>
        </form>
    </div>

    <!-- Summary KPIs -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <x-kpi-card title="Total Sales" :value="\App\Support\Format::rupiah($totalSales)" :subtitle="$dateFrom . ' to ' . $dateTo" tone="emerald">
            <x-slot:icon><span class="material-symbols-outlined text-[20px]">payments</span></x-slot:icon>
        </x-kpi-card>
        <x-kpi-card title="Transactions" :value="$transactionCount" subtitle="Matching selected filters" tone="primary">
            <x-slot:icon><span class="material-symbols-outlined text-[20px]">receipt_long</span></x-slot:icon>
        </x-kpi-card>
        <x-kpi-card title="Average Basket" :value="\App\Support\Format::rupiah($averageTransaction)" subtitle="Mean per transaction" tone="secondary">
            <x-slot:icon><span class="material-symbols-outlined text-[20px]">analytics</span></x-slot:icon>
        </x-kpi-card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Daily Sales -->
        <div class="rounded-xl bg-surface-container-lowest border border-divider/80 p-5">
            <h3 class="text-base font-semibold text-primary mb-4">Daily Sales Breakdown</h3>
            @if ($dailySales->isEmpty())
                <x-empty-state title="No Sales Data" description="No transactions found for this period." />
            @else
                <x-table :headers="['Date', 'Transactions', 'Total Sales']">
                    @foreach ($dailySales as $day)
                        <tr>
                            <td class="py-2.5 px-3 font-medium">{{ $day['date'] }}</td>
                            <td class="py-2.5 px-3 tabular-nums">{{ $day['count'] }}</td>
                            <td class="py-2.5 px-3 tabular-nums font-semibold text-primary">{{ \App\Support\Format::rupiah($day['total']) }}</td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </div>

        <!-- Payment Method Summary -->
        <div class="rounded-xl bg-surface-container-lowest border border-divider/80 p-5">
            <h3 class="text-base font-semibold text-primary mb-4">Payment Method Summary</h3>
            @if ($paymentSummary->isEmpty())
                <x-empty-state title="No Payment Data" description="No transactions found for this period." />
            @else
                <x-table :headers="['Payment Method', 'Transactions', 'Total Amount']">
                    @foreach ($paymentSummary as $row)
                        <tr>
                            <td class="py-2.5 px-3 font-medium">{{ $row['method'] }}</td>
                            <td class="py-2.5 px-3 tabular-nums">{{ $row['count'] }}</td>
                            <td class="py-2.5 px-3 tabular-nums font-semibold text-primary">{{ \App\Support\Format::rupiah($row['total']) }}</td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </div>
    </div>

    <!-- Best-Selling Products -->
    <div class="rounded-xl bg-surface-container-lowest border border-divider/80 p-5">
        <h3 class="text-base font-semibold text-primary mb-4">Top 10 Best-Selling Products</h3>
        @if ($bestSellingProducts->isEmpty())
            <x-empty-state title="No Product Sales" description="No product sales recorded for this period." />
        @else
            <x-table :headers="['Product', 'SKU', 'Total Qty Sold', 'Total Revenue']">
                @foreach ($bestSellingProducts as $item)
                    <tr>
                        <td class="py-2.5 px-3 font-semibold text-on-surface">{{ $item->product_name_snapshot }}</td>
                        <td class="py-2.5 px-3 font-mono text-xs text-outline">{{ $item->product_code_snapshot ?: '—' }}</td>
                        <td class="py-2.5 px-3 tabular-nums">{{ \App\Support\Format::quantity($item->total_qty) }} {{ $item->unit }}</td>
                        <td class="py-2.5 px-3 tabular-nums font-semibold text-primary">{{ \App\Support\Format::rupiah($item->total_revenue) }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </div>
</div>
@endsection
