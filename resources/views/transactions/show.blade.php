@extends('layouts.app', [
    'pageTitle' => 'Transaction Details',
    'breadcrumb' => 'Transaction History / ' . $transaction->transaction_code,
])

@section('title', $transaction->transaction_code)

@section('content')
<div class="max-w-4xl space-y-6">
    @if (session('checkout_success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3.5 text-sm text-emerald-900 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-[20px] text-emerald-600">check_circle</span>
                <span class="font-semibold">{{ session('checkout_success') }}</span>
            </div>
            <div class="flex items-center gap-2">
                <x-button size="sm" variant="primary" :href="route('pos.index')">New Transaction</x-button>
                <x-button size="sm" variant="secondary" :href="route('transactions.receipt', $transaction)" target="_blank">Print Receipt</x-button>
            </div>
        </div>
    @endif

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

    <div class="rounded-xl bg-surface-container-lowest border border-divider/80 shadow-[0_1px_3px_0_rgba(37,43,54,0.04)] p-6 space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4 pb-5 border-b border-divider">
            <div>
                <div class="flex items-center gap-2.5 flex-wrap">
                    <h2 class="text-xl font-bold font-mono text-primary">{{ $transaction->transaction_code }}</h2>
                    <x-status-badge :status="$transaction->transaction_status" :label="$transaction->statusLabel()" />
                    <x-status-badge
                        :status="$transaction->payment_status === 'paid' ? 'completed' : ($transaction->payment_status === 'partially_paid' ? 'pending' : 'cancelled')"
                        :label="$transaction->paymentStatusLabel()"
                    />
                </div>
                <p class="mt-1 text-xs text-on-surface-variant">
                    {{ ($transaction->transaction_date ?? $transaction->created_at)?->format('d M Y, H:i') }} WIB &bull; Cashier: <strong>{{ $transaction->user?->name ?? '—' }}</strong>
                    &bull; Taken By: <strong>{{ $transaction->takenByLabel() }}</strong>
                    @if ($transaction->customer)
                        &bull; Customer: <strong>{{ $transaction->customer->customer_code }} &ndash; {{ $transaction->customer->name }}</strong>
                    @endif
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <x-button size="sm" variant="secondary" :href="route('transactions.receipt', $transaction)" target="_blank">
                    <span class="material-symbols-outlined text-[16px]">print</span>
                    <span>View / Print Receipt</span>
                </x-button>
                <x-button size="sm" variant="secondary" :href="route('transactions.index')">Back to History</x-button>
            </div>
        </div>

        <x-table :headers="['Product (Snapshot)', 'SKU', 'Unit Price', 'Quantity', 'Subtotal']">
            @foreach ($transaction->details as $detail)
                <tr>
                    <td class="py-3 px-3 font-medium text-on-surface">
                        {{ $detail->product_name_snapshot ?: ($detail->product?->product_name ?? 'Archived Product') }}
                    </td>
                    <td class="py-3 px-3 font-mono text-xs text-outline">{{ $detail->product_code_snapshot ?: ($detail->product?->product_code ?? '—') }}</td>
                    <td class="py-3 px-3 tabular-nums">{{ \App\Support\Format::rupiah($detail->price) }}</td>
                    <td class="py-3 px-3 tabular-nums">{{ \App\Support\Format::quantity($detail->quantity) }} {{ $detail->unit }}</td>
                    <td class="py-3 px-3 tabular-nums font-semibold text-primary">{{ \App\Support\Format::rupiah($detail->subtotal) }}</td>
                </tr>
            @endforeach
        </x-table>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 pt-4 border-t border-divider">
            <div class="text-xs text-on-surface-variant space-y-1.5">
                <p><strong class="text-on-surface">Payment Method:</strong> {{ $transaction->paymentMethodLabel() }}</p>
                @if ($transaction->notes)
                    <p><strong class="text-on-surface">Notes:</strong> {{ $transaction->notes }}</p>
                @endif
            </div>

            <div class="space-y-1.5 text-sm">
                <div class="flex justify-between text-on-surface-variant">
                    <span>Subtotal</span>
                    <span class="tabular-nums">{{ \App\Support\Format::rupiah($transaction->subtotal ?: $transaction->total_amount) }}</span>
                </div>
                @if ((float) $transaction->discount_amount > 0)
                    <div class="flex justify-between text-on-surface-variant">
                        <span>Discount</span>
                        <span class="tabular-nums">-{{ \App\Support\Format::rupiah($transaction->discount_amount) }}</span>
                    </div>
                @endif
                <div class="flex justify-between text-base font-bold text-primary pt-1.5 border-t border-divider">
                    <span>Current Purchase Total</span>
                    <span class="tabular-nums">{{ $transaction->formattedTotal() }}</span>
                </div>
                @if ((float) $transaction->previous_debt_amount > 0)
                    <div class="flex justify-between text-xs text-red-700 font-semibold">
                        <span>Previous Outstanding Debt</span>
                        <span class="tabular-nums">{{ \App\Support\Format::rupiah($transaction->previous_debt_amount) }}</span>
                    </div>
                    <div class="flex justify-between text-sm font-bold text-primary pt-1 border-t border-divider/60">
                        <span>Grand Total</span>
                        <span class="tabular-nums">{{ \App\Support\Format::rupiah($transaction->grand_total) }}</span>
                    </div>
                    <div class="flex justify-between text-xs text-emerald-700 font-semibold">
                        <span>Previous Debt Paid</span>
                        <span class="tabular-nums">{{ \App\Support\Format::rupiah($transaction->previous_debt_paid) }}</span>
                    </div>
                @endif
                <div class="flex justify-between text-xs text-on-surface-variant">
                    <span>Amount Paid (Cash Received)</span>
                    <span class="tabular-nums">{{ \App\Support\Format::rupiah((float) $transaction->cash_received > 0 ? $transaction->cash_received : $transaction->amount_paid) }}</span>
                </div>
                @if ((float) $transaction->outstanding_balance > 0)
                    <div class="flex justify-between text-xs text-red-600 font-bold">
                        <span>Outstanding Debt</span>
                        <span class="tabular-nums">{{ \App\Support\Format::rupiah($transaction->outstanding_balance) }}</span>
                    </div>
                @else
                    <div class="flex justify-between text-xs text-emerald-700 font-semibold">
                        <span>Change</span>
                        <span class="tabular-nums">{{ \App\Support\Format::rupiah($transaction->change_amount) }}</span>
                    </div>
                @endif
            </div>
        </div>

        @if ($transaction->transaction_status !== 'cancelled' && (float) $transaction->outstanding_balance > 0 && $transaction->customer)
            <div class="pt-5 border-t border-divider space-y-3">
                <h3 class="text-sm font-semibold text-primary">Record Debt Repayment</h3>
                <form method="POST" action="{{ route('debts.pay', $transaction) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold mb-1">Amount (Rp)</label>
                        <input type="number" name="amount" min="1" max="{{ (float) $transaction->outstanding_balance }}" step="1" value="{{ (int) $transaction->outstanding_balance }}" required class="w-40 rounded-lg border border-divider px-3 py-2 text-sm tabular-nums">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Method</label>
                        <select name="payment_method" class="rounded-lg border border-divider px-3 py-2 text-sm">
                            <option value="cash">Cash</option>
                            <option value="qris">QRIS</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="flex-1 min-w-[180px]">
                        <label class="block text-xs font-semibold mb-1">Note</label>
                        <input type="text" name="note" placeholder="Optional note..." class="w-full rounded-lg border border-divider px-3 py-2 text-sm">
                    </div>
                    <x-button type="submit" variant="primary">Record Payment</x-button>
                </form>
            </div>
        @endif

        @if ($transaction->debtPayments->isNotEmpty())
            <div class="pt-5 border-t border-divider space-y-2">
                <h3 class="text-sm font-semibold text-primary">Debt Repayment History</h3>
                <x-table :headers="['Date', 'Amount', 'Method', 'Recorded By', 'Note', 'Receipt']">
                    @foreach ($transaction->debtPayments as $payment)
                        <tr>
                            <td class="py-2 px-3 text-xs">{{ $payment->payment_date?->format('d M Y H:i') }}</td>
                            <td class="py-2 px-3 tabular-nums font-semibold text-emerald-700">{{ \App\Support\Format::rupiah($payment->amount) }}</td>
                            <td class="py-2 px-3 text-xs uppercase">{{ $payment->payment_method }}</td>
                            <td class="py-2 px-3 text-xs">{{ $payment->user?->name ?? '—' }}</td>
                            <td class="py-2 px-3 text-xs">{{ $payment->note ?: '—' }}</td>
                            <td class="py-2 px-3 text-xs">
                                <a href="{{ route('debts.receipt', $payment) }}" target="_blank" class="text-secondary font-semibold hover:underline">Print</a>
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            </div>
        @endif

        @if (auth()->user()->isAdministrator() && $transaction->transaction_status === \App\Models\Transaction::STATUS_COMPLETED)
            <div class="pt-5 border-t border-divider">
                <form method="POST" action="{{ route('transactions.cancel', $transaction) }}" onsubmit="return confirm('Batalkan transaksi ini dan kembalikan stok?');" class="flex flex-col sm:flex-row items-stretch sm:items-end gap-3">
                    @csrf
                    <div class="flex-1">
                        <label class="block text-xs font-semibold text-red-700 mb-1">Cancel Transaction (Admin Only — Restores Stock)</label>
                        <input type="text" name="reason" required minlength="5" placeholder="Required cancellation reason..." class="w-full rounded-lg border border-red-200 px-3 py-2 text-sm focus:border-red-500 focus:outline-none">
                    </div>
                    <x-button type="submit" variant="danger">Cancel Transaction</x-button>
                </form>
            </div>
        @endif
    </div>
</div>
@endsection
