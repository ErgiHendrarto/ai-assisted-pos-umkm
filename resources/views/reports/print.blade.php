<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sales Report · {{ $dateFrom }} to {{ $dateTo }}</title>
    <style>
        body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; color: #161c26; background: #f9f9ff; margin: 0; padding: 24px; }
        .container { max-width: 900px; margin: 0 auto; background: #fff; border: 1px solid #e5e8ed; border-radius: 12px; padding: 28px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0a254d; padding-bottom: 14px; margin-bottom: 20px; }
        h1 { margin: 0; font-size: 20px; color: #0a254d; }
        .meta { font-size: 12px; color: #44474e; margin-top: 4px; }
        .kpis { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 24px; }
        .kpi { border: 1px solid #e5e8ed; border-radius: 8px; padding: 12px; background: #f9f9ff; }
        .kpi-label { font-size: 11px; text-transform: uppercase; color: #74777f; font-weight: 600; }
        .kpi-val { font-size: 18px; font-weight: 700; color: #0a254d; margin-top: 4px; }
        h2 { font-size: 14px; color: #0a254d; margin: 20px 0 8px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 16px; }
        th, td { border-bottom: 1px solid #e5e8ed; padding: 8px 10px; text-align: left; }
        th { background: #f0f3ff; color: #44474e; font-size: 11px; text-transform: uppercase; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .actions { max-width: 900px; margin: 0 auto 16px; display: flex; gap: 10px; justify-content: flex-end; }
        .btn { padding: 8px 14px; font-size: 13px; font-weight: 600; border-radius: 8px; border: 1px solid #e5e8ed; background: #fff; cursor: pointer; text-decoration: none; color: #161c26; }
        .btn-primary { background: #243b64; color: #fff; border-color: #243b64; }
        @media print {
            body { background: #fff; padding: 0; }
            .container { border: none; padding: 0; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="actions">
        <a href="{{ route('reports.index', request()->only(['date_from', 'date_to', 'status', 'payment_method', 'user_id'])) }}" class="btn">Back</a>
        <button type="button" onclick="window.print()" class="btn btn-primary">Print / Save as PDF</button>
    </div>

    <div class="container">
        <div class="header">
            <div>
                <h1>AI-Assisted POS UMKM — Sales Report</h1>
                <div class="meta">Period: {{ $dateFrom }} to {{ $dateTo }} &bull; Status: {{ ucfirst($status) }}</div>
            </div>
            <div class="meta">Printed: {{ now()->format('d M Y, H:i') }} WIB</div>
        </div>

        <div class="kpis">
            <div class="kpi">
                <div class="kpi-label">Total Sales</div>
                <div class="kpi-val">{{ \App\Support\Format::rupiah($totalSales) }}</div>
            </div>
            <div class="kpi">
                <div class="kpi-label">Transactions</div>
                <div class="kpi-val">{{ $transactionCount }}</div>
            </div>
            <div class="kpi">
                <div class="kpi-label">Average Basket</div>
                <div class="kpi-val">{{ \App\Support\Format::rupiah($averageTransaction) }}</div>
            </div>
        </div>

        <h2>Best-Selling Products</h2>
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th class="num">Qty Sold</th>
                    <th class="num">Revenue</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($bestSellingProducts as $item)
                    <tr>
                        <td>{{ $item->product_name_snapshot }}</td>
                        <td>{{ $item->product_code_snapshot ?: '—' }}</td>
                        <td class="num">{{ \App\Support\Format::quantity($item->total_qty) }} {{ $item->unit }}</td>
                        <td class="num">{{ \App\Support\Format::rupiah($item->total_revenue) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">No product sales in this period.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>Transactions List</h2>
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Date</th>
                    <th>Cashier</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th class="num">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($transactions as $trx)
                    <tr>
                        <td>{{ $trx->transaction_code }}</td>
                        <td>{{ ($trx->transaction_date ?? $trx->created_at)?->format('d M Y H:i') }}</td>
                        <td>{{ $trx->user?->name ?? '-' }}</td>
                        <td>{{ $trx->paymentMethodLabel() }}</td>
                        <td>{{ $trx->statusLabel() }}</td>
                        <td class="num">{{ $trx->formattedTotal() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">No transactions in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
