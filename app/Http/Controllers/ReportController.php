<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        return view('reports.index', $this->buildReportData($request));
    }

    public function print(Request $request): View
    {
        return view('reports.print', $this->buildReportData($request));
    }

    public function export(Request $request): StreamedResponse
    {
        $data = $this->buildReportData($request);
        $filename = 'sales-report-' . $data['dateFrom'] . '-to-' . $data['dateTo'] . '.csv';

        return response()->streamDownload(function () use ($data): void {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['AI-Assisted POS UMKM - Sales Report']);
            fputcsv($handle, ['Period', $data['dateFrom'] . ' s/d ' . $data['dateTo']]);
            fputcsv($handle, ['Total Sales', $data['totalSales']]);
            fputcsv($handle, ['Transaction Count', $data['transactionCount']]);
            fputcsv($handle, ['Average Basket', round($data['averageTransaction'], 2)]);
            fputcsv($handle, []);

            fputcsv($handle, ['Transaction Code', 'Date', 'Cashier', 'Payment Method', 'Status', 'Total Amount']);
            foreach ($data['transactions'] as $trx) {
                fputcsv($handle, [
                    $trx->transaction_code,
                    ($trx->transaction_date ?? $trx->created_at)?->format('Y-m-d H:i:s'),
                    $trx->user?->name ?? '-',
                    $trx->paymentMethodLabel(),
                    $trx->statusLabel(),
                    $trx->total_amount,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReportData(Request $request): array
    {
        $dateFrom = $request->query('date_from', Carbon::today()->subDays(29)->format('Y-m-d'));
        $dateTo = $request->query('date_to', Carbon::today()->format('Y-m-d'));
        $paymentMethod = $request->query('payment_method', 'all');
        $status = $request->query('status', Transaction::STATUS_COMPLETED);
        $cashierId = $request->query('user_id');

        $start = Carbon::parse($dateFrom)->startOfDay();
        $end = Carbon::parse($dateTo)->endOfDay();

        $baseQuery = Transaction::query()
            ->whereBetween('created_at', [$start, $end])
            ->when($status !== 'all', fn ($q) => $q->where('transaction_status', $status))
            ->when($paymentMethod !== 'all', fn ($q) => $q->where('payment_method', $paymentMethod))
            ->when($cashierId, fn ($q) => $q->where('user_id', $cashierId));

        $transactions = (clone $baseQuery)->with('user')->latest('created_at')->get();

        $totalSales = (float) $transactions->sum('total_amount');
        $transactionCount = $transactions->count();
        $averageTransaction = $transactionCount > 0 ? $totalSales / $transactionCount : 0.0;

        $dailySales = $transactions
            ->groupBy(fn (Transaction $t) => $t->created_at->format('Y-m-d'))
            ->map(fn ($group, $date) => [
                'date' => Carbon::parse($date)->format('d M Y'),
                'count' => $group->count(),
                'total' => (float) $group->sum('total_amount'),
            ])
            ->values();

        $paymentSummary = $transactions
            ->groupBy('payment_method')
            ->map(fn ($group) => [
                'method' => $group->first()->paymentMethodLabel(),
                'count' => $group->count(),
                'total' => (float) $group->sum('total_amount'),
            ])
            ->values();

        $transactionIds = $transactions->pluck('id');
        $bestSellingProducts = $transactionIds->isEmpty()
            ? collect()
            : TransactionDetail::query()
                ->whereIn('transaction_id', $transactionIds)
                ->select([
                    'product_name_snapshot',
                    'product_code_snapshot',
                    'unit',
                    DB::raw('SUM(quantity) as total_qty'),
                    DB::raw('SUM(subtotal) as total_revenue'),
                ])
                ->groupBy('product_name_snapshot', 'product_code_snapshot', 'unit')
                ->orderByDesc('total_qty')
                ->take(10)
                ->get();

        $cashiers = User::query()->orderBy('name')->get(['id', 'name']);

        return [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'paymentMethod' => $paymentMethod,
            'status' => $status,
            'cashierId' => $cashierId,
            'cashiers' => $cashiers,
            'transactions' => $transactions,
            'totalSales' => $totalSales,
            'transactionCount' => $transactionCount,
            'averageTransaction' => $averageTransaction,
            'dailySales' => $dailySales,
            'paymentSummary' => $paymentSummary,
            'bestSellingProducts' => $bestSellingProducts,
        ];
    }
}
