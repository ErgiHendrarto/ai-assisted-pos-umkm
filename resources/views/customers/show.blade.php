@extends('layouts.app', [
    'pageTitle' => $customer->name,
    'breadcrumb' => 'Customers / ' . $customer->name,
])

@section('title', $customer->name)

@section('content')
<div class="space-y-6">
    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc list-inside text-xs">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="md:col-span-2 rounded-xl bg-surface-container-lowest border border-divider/80 p-5 space-y-2">
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2.5 flex-wrap">
                    <span class="font-mono text-xs font-semibold text-primary bg-surface-container-low px-2.5 py-1 rounded-md border border-divider">{{ $customer->customer_code }}</span>
                    <h2 class="text-lg font-bold text-primary">{{ $customer->name }}</h2>
                </div>
                <x-button size="sm" variant="secondary" :href="route('customers.index')">Back to Customers</x-button>
            </div>
            <p class="text-xs text-on-surface-variant">Phone: <strong>{{ $customer->phone ?: '—' }}</strong> &bull; Address: {{ $customer->address ?: '—' }}</p>
            @if ($customer->notes)
                <p class="text-xs text-outline">Notes: {{ $customer->notes }}</p>
            @endif
        </div>

        <x-kpi-card
            title="Total Outstanding Debt"
            :value="\App\Support\Format::rupiah($totalOutstandingDebt)"
            subtitle="Remaining unpaid balance"
            :tone="$totalOutstandingDebt > 0 ? 'error' : 'emerald'"
        >
            <x-slot:icon><span class="material-symbols-outlined text-[20px]">account_balance_wallet</span></x-slot:icon>
        </x-kpi-card>
    </div>

    <!-- Transactions & Debt Repayment -->
    <div class="rounded-xl bg-surface-container-lowest border border-divider/80 p-5 space-y-4">
        <h3 class="text-base font-semibold text-primary">Customer Transactions</h3>
        @if ($transactions->isEmpty())
            <x-empty-state title="No Transactions" description="Transactions linked to this customer will appear here." />
        @else
            <x-table :headers="['Code', 'Date', 'Total', 'Paid', 'Outstanding', 'Payment Status', 'Action']">
                @foreach ($transactions as $trx)
                    <tr>
                        <td class="py-2.5 px-3 font-mono text-xs font-semibold text-primary">
                            <a href="{{ route('transactions.show', $trx) }}" class="hover:underline">{{ $trx->transaction_code }}</a>
                        </td>
                        <td class="py-2.5 px-3 text-xs">{{ ($trx->transaction_date ?? $trx->created_at)?->format('d M Y H:i') }}</td>
                        <td class="py-2.5 px-3 tabular-nums font-semibold">{{ $trx->formattedTotal() }}</td>
                        <td class="py-2.5 px-3 tabular-nums text-emerald-700">{{ \App\Support\Format::rupiah($trx->amount_paid) }}</td>
                        <td class="py-2.5 px-3 tabular-nums font-semibold {{ (float) $trx->outstanding_balance > 0 ? 'text-red-600' : 'text-outline' }}">
                            {{ \App\Support\Format::rupiah($trx->outstanding_balance) }}
                        </td>
                        <td class="py-2.5 px-3"><x-status-badge :status="$trx->payment_status === 'paid' ? 'completed' : ($trx->payment_status === 'partially_paid' ? 'pending' : 'cancelled')" :label="$trx->paymentStatusLabel()" /></td>
                        <td class="py-2.5 px-3">
                            @if ($trx->transaction_status !== 'cancelled' && (float) $trx->outstanding_balance > 0)
                                <form method="POST" action="{{ route('debts.pay', $trx) }}" class="flex flex-wrap items-center gap-1.5">
                                    @csrf
                                    <input type="number" name="amount" min="1" max="{{ (float) $trx->outstanding_balance }}" step="1" value="{{ (int) $trx->outstanding_balance }}" required class="w-28 rounded border border-divider px-2 py-1 text-xs tabular-nums">
                                    <select name="payment_method" class="rounded border border-divider px-2 py-1 text-xs">
                                        <option value="cash">Cash</option>
                                        <option value="qris">QRIS</option>
                                        <option value="other">Other</option>
                                    </select>
                                    <button type="submit" class="rounded bg-primary-container text-on-primary px-2.5 py-1 text-xs font-semibold hover:bg-primary cursor-pointer">Pay</button>
                                </form>
                            @else
                                <x-button size="sm" variant="secondary" :href="route('transactions.show', $trx)">Detail</x-button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>
            <div class="mt-3">{{ $transactions->links() }}</div>
        @endif
    </div>

    <!-- Debt Repayment History -->
    <div class="rounded-xl bg-surface-container-lowest border border-divider/80 p-5 space-y-4">
        <h3 class="text-base font-semibold text-primary">Debt Repayment History</h3>
        @if ($debtPayments->isEmpty())
            <x-empty-state title="No Repayments Yet" description="Subsequent debt payments will be logged here." />
        @else
            <x-table :headers="['Date', 'Original Debt Trx', 'Settled Via', 'Amount Paid', 'Method', 'Recorded By', 'Note']">
                @foreach ($debtPayments as $payment)
                    <tr>
                        <td class="py-2.5 px-3 text-xs">{{ $payment->payment_date?->format('d M Y H:i') }}</td>
                        <td class="py-2.5 px-3 font-mono text-xs text-primary">{{ $payment->transaction?->transaction_code ?? '—' }}</td>
                        <td class="py-2.5 px-3 font-mono text-xs text-secondary">{{ $payment->settledByTransaction?->transaction_code ?? $payment->entryTypeLabel() }}</td>
                        <td class="py-2.5 px-3 tabular-nums font-semibold {{ $payment->entry_type === \App\Models\DebtPayment::TYPE_WRITE_OFF ? 'text-amber-700' : 'text-emerald-700' }}">{{ \App\Support\Format::rupiah($payment->amount) }}</td>
                        <td class="py-2.5 px-3 text-xs uppercase">{{ $payment->payment_method }}</td>
                        <td class="py-2.5 px-3 text-xs">{{ $payment->user?->name ?? '—' }}</td>
                        <td class="py-2.5 px-3 text-xs text-on-surface-variant">{{ $payment->note ?: ($payment->reasonLabel() ?: '—') }}</td>
                    </tr>
                @endforeach
            </x-table>
            <div class="mt-3">{{ $debtPayments->links() }}</div>
        @endif
    </div>
</div>
@endsection
