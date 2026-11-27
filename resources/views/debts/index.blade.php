@extends('layouts.app', [
    'pageTitle' => 'Customer Debts & Repayments',
    'breadcrumb' => 'Customer Debts',
])

@section('title', 'Customer Debts')

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

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <x-kpi-card title="Total Outstanding Receivables" :value="\App\Support\Format::rupiah($totalOutstanding)" subtitle="Unpaid & partially paid transactions" tone="error">
            <x-slot:icon><span class="material-symbols-outlined text-[20px]">account_balance_wallet</span></x-slot:icon>
        </x-kpi-card>
        <x-kpi-card title="Total Debt Repayments Collected" :value="\App\Support\Format::rupiah($totalRepaid)" subtitle="Tracked separately from new sales revenue" tone="emerald">
            <x-slot:icon><span class="material-symbols-outlined text-[20px]">payments</span></x-slot:icon>
        </x-kpi-card>
    </div>

    <div class="rounded-xl bg-surface-container-lowest border border-divider/80 p-5 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-primary">Outstanding Customer Debts</h2>
            <form method="GET" action="{{ route('debts.index') }}" class="flex items-center gap-2">
                <select name="customer_id" onchange="this.form.submit()" class="rounded-lg border border-divider px-3 py-2 text-sm">
                    <option value="">All Customers</option>
                    @foreach ($customers as $c)
                        <option value="{{ $c->id }}" @selected((string) $customerId === (string) $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        @if ($unpaidTransactions->isEmpty())
            <x-empty-state title="No Outstanding Debts" description="All customer transactions are paid in full." tone="emerald" />
        @else
            <x-table :headers="['Transaction', 'Customer', 'Date', 'Total', 'Paid', 'Remaining Debt', 'Status', 'Record Repayment']">
                @foreach ($unpaidTransactions as $trx)
                    <tr>
                        <td class="py-2.5 px-3 font-mono text-xs font-semibold text-primary">
                            <a href="{{ route('transactions.show', $trx) }}" class="hover:underline">{{ $trx->transaction_code }}</a>
                        </td>
                        <td class="py-2.5 px-3 text-sm font-medium">
                            @if ($trx->customer)
                                <a href="{{ route('customers.show', $trx->customer) }}" class="text-primary hover:underline">
                                    <span class="font-mono text-xs text-outline">{{ $trx->customer->customer_code }}</span>
                                    {{ $trx->customer->name }}
                                </a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="py-2.5 px-3 text-xs">{{ ($trx->transaction_date ?? $trx->created_at)?->format('d M Y') }}</td>
                        <td class="py-2.5 px-3 tabular-nums">{{ $trx->formattedTotal() }}</td>
                        <td class="py-2.5 px-3 tabular-nums text-emerald-700">{{ \App\Support\Format::rupiah($trx->amount_paid) }}</td>
                        <td class="py-2.5 px-3 tabular-nums font-bold text-red-600">{{ \App\Support\Format::rupiah($trx->outstanding_balance) }}</td>
                        <td class="py-2.5 px-3"><x-status-badge :status="$trx->payment_status === 'partially_paid' ? 'pending' : 'cancelled'" :label="$trx->paymentStatusLabel()" /></td>
                        <td class="py-2.5 px-3">
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
                        </td>
                    </tr>
                @endforeach
            </x-table>
            <div class="mt-4">{{ $unpaidTransactions->links() }}</div>
        @endif
    </div>

    <div class="rounded-xl bg-surface-container-lowest border border-divider/80 p-5 space-y-4">
        <h3 class="text-base font-semibold text-primary">Recent Debt Repayment History</h3>
        @if ($recentPayments->isEmpty())
            <x-empty-state title="No Repayments Recorded" description="Partial or full debt repayments will appear here." />
        @else
            <x-table :headers="['Date', 'Customer', 'Transaction', 'Amount', 'Method', 'Recorded By']">
                @foreach ($recentPayments as $payment)
                    <tr>
                        <td class="py-2.5 px-3 text-xs">{{ $payment->payment_date?->format('d M Y H:i') }}</td>
                        <td class="py-2.5 px-3 font-medium">{{ $payment->customer?->name ?? '—' }}</td>
                        <td class="py-2.5 px-3 font-mono text-xs">{{ $payment->transaction?->transaction_code ?? '—' }}</td>
                        <td class="py-2.5 px-3 tabular-nums font-semibold {{ $payment->entry_type === \App\Models\DebtPayment::TYPE_WRITE_OFF ? 'text-amber-700' : 'text-emerald-700' }}">
                            {{ \App\Support\Format::rupiah($payment->amount) }}
                            <span class="block text-[10px] font-normal text-outline">{{ $payment->entryTypeLabel() }}</span>
                        </td>
                        <td class="py-2.5 px-3 text-xs uppercase">{{ $payment->payment_method }}</td>
                        <td class="py-2.5 px-3 text-xs">{{ $payment->user?->name ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </div>
</div>
@endsection
