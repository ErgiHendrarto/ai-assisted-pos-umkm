<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Debt Repayment Receipt · {{ $payment->transaction?->transaction_code }}</title>
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
            <div class="small">Bukti Pembayaran Piutang</div>
        </div>

        <div class="divider"></div>

        <div class="row"><span>Tanggal Bayar</span><span>{{ $payment->payment_date?->format('d/m/Y H:i') }}</span></div>
        <div class="row"><span>No. Transaksi</span><span class="bold">{{ $payment->transaction?->transaction_code ?? '-' }}</span></div>
        <div class="row"><span>Pelanggan</span><span class="bold">{{ $payment->customer?->customer_code }} - {{ $payment->customer?->name }}</span></div>
        <div class="row"><span>Kasir</span><span>{{ $payment->user?->name ?? '-' }}</span></div>

        <div class="divider"></div>

        <div class="row bold" style="font-size: 14px;"><span>Dibayar</span><span>{{ \App\Support\Format::rupiah($payment->amount) }}</span></div>
        <div class="row"><span>Metode</span><span style="text-transform: uppercase;">{{ $payment->payment_method }}</span></div>
        <div class="row bold"><span>Sisa Hutang</span><span>{{ \App\Support\Format::rupiah($payment->transaction?->outstanding_balance ?? 0) }}</span></div>
        @if ($payment->note)
            <div class="row"><span>Catatan</span><span>{{ $payment->note }}</span></div>
        @endif

        <div class="divider"></div>
        <div class="center small">Terima kasih!</div>
    </div>

    <div class="actions">
        <button type="button" onclick="window.print()" class="btn btn-primary">Print Receipt</button>
        <a href="{{ $payment->customer ? route('customers.show', $payment->customer) : route('debts.index') }}" class="btn">Back</a>
    </div>
</body>
</html>
