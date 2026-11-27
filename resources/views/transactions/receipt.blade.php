<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt · {{ $transaction->transaction_code }}</title>
    <style>
        body { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; background: #f3f4f6; margin: 0; padding: 24px; color: #111827; }
        .receipt { max-width: 340px; margin: 0 auto; background: #fff; padding: 20px; border: 1px solid #e5e7eb; }
        .center { text-align: center; }
        .divider { border-top: 1px dashed #9ca3af; margin: 10px 0; }
        .row { display: flex; justify-content: space-between; font-size: 12px; margin: 4px 0; }
        .bold { font-weight: 700; }
        .small { font-size: 11px; color: #4b5563; }
        .actions { max-width: 340px; margin: 14px auto 0; display: flex; gap: 8px; }
        .btn { flex: 1; padding: 8px 12px; font-size: 12px; font-family: sans-serif; font-weight: 600; border-radius: 6px; border: 1px solid #d1d5db; background: #fff; cursor: pointer; text-align: center; text-decoration: none; color: #111827; }
        .btn-primary { background: #0a254d; color: #fff; border-color: #0a254d; }
        @media print {
            body { background: #fff; padding: 0; }
            .receipt { border: none; max-width: 100%; padding: 0; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="center">
            <div class="bold" style="font-size: 15px;">AI-Assisted POS UMKM</div>
            <div class="small">Struk Pembayaran Resmi</div>
        </div>

        <div class="divider"></div>

        <div class="row"><span>No. Transaksi</span><span class="bold">{{ $transaction->transaction_code }}</span></div>
        <div class="row"><span>Tanggal</span><span>{{ ($transaction->transaction_date ?? $transaction->created_at)?->format('d/m/Y H:i') }}</span></div>
        <div class="row"><span>Kasir</span><span>{{ $transaction->user?->name ?? '-' }}</span></div>
        @if ($transaction->taken_by_staff_name_snapshot || $transaction->takenByStaff)
            <div class="row"><span>Taken By</span><span>{{ $transaction->taken_by_staff_name_snapshot ?: $transaction->takenByStaff->name }}</span></div>
        @endif
        @if ($transaction->customer)
            <div class="row"><span>Pelanggan</span><span class="bold">{{ $transaction->customer->customer_code }} - {{ $transaction->customer->name }}</span></div>
        @endif
        <div class="row"><span>Status Bayar</span><span class="bold">{{ $transaction->paymentStatusLabel() }}</span></div>

        <div class="divider"></div>

        @foreach ($transaction->details as $detail)
            <div style="margin-bottom: 6px;">
                <div class="bold" style="font-size: 12px;">{{ $detail->product_name_snapshot }}</div>
                <div class="row">
                    <span>{{ \App\Support\Format::quantity($detail->quantity) }} {{ $detail->unit }} x {{ \App\Support\Format::rupiah($detail->price) }}</span>
                    <span>{{ \App\Support\Format::rupiah($detail->subtotal) }}</span>
                </div>
            </div>
        @endforeach

        <div class="divider"></div>

        <div class="row"><span>Subtotal</span><span>{{ \App\Support\Format::rupiah($transaction->subtotal ?: $transaction->total_amount) }}</span></div>
        @if ((float) $transaction->discount_amount > 0)
            <div class="row"><span>Diskon</span><span>-{{ \App\Support\Format::rupiah($transaction->discount_amount) }}</span></div>
        @endif
        <div class="row bold" style="font-size: 13px;"><span>Belanja Saat Ini</span><span>{{ $transaction->formattedTotal() }}</span></div>
        @if ((float) $transaction->previous_debt_amount > 0)
            <div class="row"><span>Hutang Sebelumnya</span><span>{{ \App\Support\Format::rupiah($transaction->previous_debt_amount) }}</span></div>
            <div class="row bold" style="font-size: 14px;"><span>GRAND TOTAL</span><span>{{ \App\Support\Format::rupiah($transaction->grand_total) }}</span></div>
            <div class="row"><span>Bayar ({{ $transaction->paymentMethodLabel() }})</span><span>{{ \App\Support\Format::rupiah($transaction->cash_received) }}</span></div>
            <div class="row"><span>Pelunasan Hutang Lama</span><span>{{ \App\Support\Format::rupiah($transaction->previous_debt_paid) }}</span></div>
            @php
                $totalRemainingAfterTrx = ($transaction->customer ? $transaction->customer->totalOutstandingDebt() : (float) $transaction->outstanding_balance);
            @endphp
            @if ($totalRemainingAfterTrx > 0)
                <div class="row bold"><span>Sisa Hutang</span><span>{{ \App\Support\Format::rupiah($totalRemainingAfterTrx) }}</span></div>
            @else
                <div class="row bold"><span>Sisa Hutang</span><span>Rp 0</span></div>
                <div class="row"><span>Kembali</span><span>{{ \App\Support\Format::rupiah($transaction->change_amount) }}</span></div>
            @endif
        @else
            <div class="row"><span>Bayar ({{ $transaction->paymentMethodLabel() }})</span><span>{{ \App\Support\Format::rupiah((float) $transaction->cash_received > 0 ? $transaction->cash_received : $transaction->amount_paid) }}</span></div>
            @if ((float) $transaction->outstanding_balance > 0)
                <div class="row bold"><span>Sisa Hutang</span><span>{{ \App\Support\Format::rupiah($transaction->outstanding_balance) }}</span></div>
            @else
                <div class="row"><span>Kembali</span><span>{{ \App\Support\Format::rupiah($transaction->change_amount) }}</span></div>
            @endif
        @endif

        <div class="divider"></div>
        <div class="center small">Terima kasih telah berbelanja!</div>
    </div>

    <div class="actions">
        <button type="button" onclick="window.print()" class="btn btn-primary">Print Receipt</button>
        <a href="{{ route('transactions.show', $transaction) }}" class="btn">Back</a>
    </div>
</body>
</html>
