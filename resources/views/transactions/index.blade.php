@extends('layouts.app', [
    'pageTitle' => 'Transaction History',
    'breadcrumb' => 'Transaction History',
])

@section('title', 'Transaction History')

@section('content')
<div class="space-y-6">
    <div class="rounded-xl bg-surface-container-lowest border border-divider/80 shadow-[0_1px_3px_0_rgba(37,43,54,0.04)]">
        <div class="p-5 border-b border-divider">
            <form method="GET" action="{{ route('transactions.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-2.5">
                <input
                    type="text"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Transaction code..."
                    class="rounded-lg border border-divider bg-white px-3 py-2 text-sm focus:border-primary focus:outline-none"
                >
                <input type="date" name="date_from" value="{{ $dateFrom }}" class="rounded-lg border border-divider bg-white px-3 py-2 text-sm">
                <input type="date" name="date_to" value="{{ $dateTo }}" class="rounded-lg border border-divider bg-white px-3 py-2 text-sm">
                <select name="payment_method" class="rounded-lg border border-divider bg-white px-3 py-2 text-sm">
                    <option value="all" @selected($paymentMethod === 'all')>All Payments</option>
                    <option value="cash" @selected($paymentMethod === 'cash')>Cash</option>
                    <option value="qris" @selected($paymentMethod === 'qris')>QRIS</option>
                    <option value="other" @selected($paymentMethod === 'other')>Other</option>
                </select>
                <select name="status" class="rounded-lg border border-divider bg-white px-3 py-2 text-sm">
                    <option value="all" @selected($status === 'all')>All Statuses</option>
                    <option value="completed" @selected($status === 'completed')>Completed</option>
                    <option value="pending" @selected($status === 'pending')>Pending</option>
                    <option value="cancelled" @selected($status === 'cancelled')>Cancelled</option>
                </select>
                @if (auth()->user()->isAdministrator())
                    <select name="user_id" class="rounded-lg border border-divider bg-white px-3 py-2 text-sm">
                        <option value="">All Cashiers</option>
                        @foreach ($cashiers as $cashier)
                            <option value="{{ $cashier->id }}" @selected((string) $cashierId === (string) $cashier->id)>
                                {{ $cashier->name }}
                            </option>
                        @endforeach
                    </select>
                @endif
                <select name="taken_by" class="rounded-lg border border-divider bg-white px-3 py-2 text-sm">
                    <option value="all" @selected($takenBy === 'all')>All Staff (Taken By)</option>
                    <option value="unassigned" @selected($takenBy === 'unassigned')>Not Assigned</option>
                    @foreach ($staffList as $staff)
                        <option value="{{ $staff->id }}" @selected((string) $takenBy === (string) $staff->id)>
                            {{ $staff->name }} ({{ $staff->staff_code }})
                        </option>
                    @endforeach
                </select>
                <div class="flex items-center gap-2 sm:col-span-2 lg:col-span-6">
                    <x-button type="submit" variant="primary" size="sm">Apply Filter</x-button>
                    <x-button variant="secondary" size="sm" :href="route('transactions.index')">Reset</x-button>
                </div>
            </form>
        </div>

        <div class="p-5">
            @if ($transactions->isEmpty())
                <x-empty-state title="No Transactions Found" description="Completed or pending transactions matching your filter will appear here." />
            @else
                <x-table :headers="['Transaction Code', 'Date', 'Cashier', 'Taken By', 'Items', 'Payment', 'Total', 'Status', 'Actions']">
                    @foreach ($transactions as $transaction)
                        <tr class="hover:bg-surface-container-low/40">
                            <td class="py-3 px-3 font-mono text-xs font-semibold text-primary">{{ $transaction->transaction_code }}</td>
                            <td class="py-3 px-3 text-xs text-on-surface-variant whitespace-nowrap">{{ $transaction->created_at?->format('d M Y, H:i') }}</td>
                            <td class="py-3 px-3 text-sm">{{ $transaction->user?->name ?? '—' }}</td>
                            <td class="py-3 px-3 text-xs">
                                @if ($transaction->taken_by_staff_name_snapshot || $transaction->takenByStaff)
                                    <span class="font-medium text-on-surface">{{ $transaction->taken_by_staff_name_snapshot ?: $transaction->takenByStaff->name }}</span>
                                @else
                                    <span class="text-outline">Not Assigned</span>
                                @endif
                            </td>
                            <td class="py-3 px-3 tabular-nums text-xs">{{ $transaction->details_count }}</td>
                            <td class="py-3 px-3 text-xs font-medium">{{ $transaction->paymentMethodLabel() }}</td>
                            <td class="py-3 px-3 tabular-nums font-semibold text-on-surface">{{ $transaction->formattedTotal() }}</td>
                            <td class="py-3 px-3"><x-status-badge :status="$transaction->transaction_status" :label="$transaction->statusLabel()" /></td>
                            <td class="py-3 px-3">
                                <div class="flex items-center gap-1.5">
                                    <x-button size="sm" variant="secondary" :href="route('transactions.show', $transaction)">Detail</x-button>
                                    <x-button size="sm" variant="ghost" :href="route('transactions.receipt', $transaction)" target="_blank">Receipt</x-button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-table>

                <div class="mt-4">{{ $transactions->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
