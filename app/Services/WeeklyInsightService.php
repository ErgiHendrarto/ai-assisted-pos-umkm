<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Models\WeeklyInsight;
use App\Services\AI\AiGatewayService;
use App\Support\Format;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class WeeklyInsightService
{
    public function __construct(
        private readonly AiGatewayService $aiGateway
    ) {}

    public function getLatestOrGenerate(?User $user = null): WeeklyInsight
    {
        $weekStart = Carbon::today()->subDays(6)->startOfDay();
        $existing = WeeklyInsight::query()
            ->whereDate('week_start', $weekStart->toDateString())
            ->latest('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->regenerate($user);
    }

    public function regenerate(?User $user = null): WeeklyInsight
    {
        $thisWeekStart = Carbon::today()->subDays(6)->startOfDay();
        $thisWeekEnd = Carbon::today()->endOfDay();
        $prevWeekStart = Carbon::today()->subDays(13)->startOfDay();
        $prevWeekEnd = Carbon::today()->subDays(7)->endOfDay();

        $metrics = $this->collectWeeklyMetrics($thisWeekStart, $thisWeekEnd, $prevWeekStart, $prevWeekEnd);

        $source = 'rule_based';
        $summaryText = $this->buildRuleBasedSummary($metrics);
        $recommendations = $this->buildRuleBasedRecommendations($metrics);

        try {
            $setting = $this->aiGateway->getActiveSetting();
            $prompt = $this->buildAiPrompt($metrics);
            $raw = $this->aiGateway->generateText($setting, $prompt, null, true);
            $cleaned = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($raw)) ?? $raw;
            $decoded = json_decode($cleaned, true);

            if (is_array($decoded) && ! empty($decoded['summary']) && is_string($decoded['summary'])) {
                $summaryText = trim($decoded['summary']);
                if (! empty($decoded['recommendations']) && is_array($decoded['recommendations'])) {
                    $recommendations = array_values(array_filter(array_map('strval', $decoded['recommendations'])));
                }
                $source = 'ai';
            }
        } catch (Throwable) {
            // Gracefully fall back to deterministic rule-based summary if AI is unavailable
        }

        return WeeklyInsight::create([
            'week_start' => $thisWeekStart->toDateString(),
            'week_end' => $thisWeekEnd->toDateString(),
            'source' => $source,
            'summary_text' => $summaryText,
            'observed_metrics' => $metrics,
            'recommendations' => $recommendations,
            'generated_by' => $user?->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function collectWeeklyMetrics(
        Carbon $thisStart,
        Carbon $thisEnd,
        Carbon $prevStart,
        Carbon $prevEnd
    ): array {
        $thisWeekTrx = Transaction::completed()
            ->whereBetween('created_at', [$thisStart, $thisEnd])
            ->get(['id', 'total_amount']);

        $prevWeekTrx = Transaction::completed()
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->get(['id', 'total_amount']);

        $thisSales = (float) $thisWeekTrx->sum('total_amount');
        $prevSales = (float) $prevWeekTrx->sum('total_amount');
        $salesChangePct = $prevSales > 0 ? round((($thisSales - $prevSales) / $prevSales) * 100, 1) : null;

        $thisIds = $thisWeekTrx->pluck('id');
        $prevIds = $prevWeekTrx->pluck('id');

        $topProducts = $thisIds->isEmpty()
            ? []
            : TransactionDetail::query()
                ->whereIn('transaction_id', $thisIds)
                ->select(['product_name_snapshot', DB::raw('SUM(quantity) as qty'), DB::raw('SUM(subtotal) as revenue')])
                ->groupBy('product_name_snapshot')
                ->orderByDesc('qty')
                ->take(5)
                ->get()
                ->map(fn ($r) => [
                    'name' => $r->product_name_snapshot,
                    'qty' => (float) $r->qty,
                    'revenue' => (float) $r->revenue,
                ])
                ->all();

        $prevByProduct = $prevIds->isEmpty()
            ? collect()
            : TransactionDetail::query()
                ->whereIn('transaction_id', $prevIds)
                ->select(['product_name_snapshot', DB::raw('SUM(quantity) as qty')])
                ->groupBy('product_name_snapshot')
                ->pluck('qty', 'product_name_snapshot');

        $growing = [];
        $declining = [];
        foreach ($topProducts as $tp) {
            $prevQty = (float) ($prevByProduct->get($tp['name'], 0));
            if ($prevQty > 0) {
                $diff = $tp['qty'] - $prevQty;
                if ($diff > 0) {
                    $growing[] = $tp['name'];
                } elseif ($diff < 0) {
                    $declining[] = $tp['name'];
                }
            }
        }

        $topCategories = $thisIds->isEmpty()
            ? []
            : TransactionDetail::query()
                ->join('products', 'transaction_details.product_id', '=', 'products.id')
                ->join('categories', 'products.category_id', '=', 'categories.id')
                ->whereIn('transaction_details.transaction_id', $thisIds)
                ->select(['categories.name', DB::raw('SUM(transaction_details.subtotal) as revenue')])
                ->groupBy('categories.name')
                ->orderByDesc('revenue')
                ->take(3)
                ->get()
                ->map(fn ($r) => ['name' => $r->name, 'revenue' => (float) $r->revenue])
                ->all();

        $lowStockNames = Product::query()
            ->active()
            ->whereColumn('stock', '<=', 'minimum_stock')
            ->orderBy('stock')
            ->take(5)
            ->pluck('product_name')
            ->all();

        $outOfStockCount = Product::query()->active()->where('stock', '<=', 0)->count();
        $outstandingDebt = (float) Transaction::query()
            ->where('transaction_status', '!=', Transaction::STATUS_CANCELLED)
            ->where('outstanding_balance', '>', 0)
            ->sum('outstanding_balance');

        return [
            'this_week_sales' => $thisSales,
            'this_week_transactions' => $thisWeekTrx->count(),
            'prev_week_sales' => $prevSales,
            'sales_change_pct' => $salesChangePct,
            'top_products' => $topProducts,
            'growing_products' => $growing,
            'declining_products' => $declining,
            'top_categories' => $topCategories,
            'low_stock_products' => $lowStockNames,
            'out_of_stock_count' => $outOfStockCount,
            'outstanding_debt' => $outstandingDebt,
        ];
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function buildRuleBasedSummary(array $metrics): string
    {
        if ($metrics['this_week_transactions'] === 0) {
            return 'Belum ada transaksi selesai dalam 7 hari terakhir. Mulai lakukan transaksi di POS agar ringkasan penjualan mingguan dapat dianalisis.';
        }

        $parts = [];
        $salesFmt = Format::rupiah($metrics['this_week_sales']);
        $parts[] = "Minggu ini tercatat {$metrics['this_week_transactions']} transaksi selesai dengan total penjualan {$salesFmt}.";

        if ($metrics['sales_change_pct'] !== null) {
            $dir = $metrics['sales_change_pct'] >= 0 ? 'naik' : 'turun';
            $parts[] = 'Penjualan ' . $dir . ' ' . abs($metrics['sales_change_pct']) . '% dibanding minggu sebelumnya.';
        }

        if (! empty($metrics['top_products'])) {
            $best = $metrics['top_products'][0];
            $parts[] = "Produk terlaris adalah {$best['name']} (" . Format::quantity($best['qty']) . " unit terjual).";
        }

        if (! empty($metrics['top_categories'])) {
            $cat = $metrics['top_categories'][0];
            $parts[] = "Kategori dengan kontribusi terbesar adalah {$cat['name']} (" . Format::rupiah($cat['revenue']) . ').';
        }

        return implode(' ', $parts);
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array<int, string>
     */
    private function buildRuleBasedRecommendations(array $metrics): array
    {
        $recs = [];

        if (! empty($metrics['low_stock_products'])) {
            $list = implode(', ', array_slice($metrics['low_stock_products'], 0, 3));
            $recs[] = "Segera restock produk dengan stok menipis/habis: {$list}.";
        }

        if ($metrics['out_of_stock_count'] > 0) {
            $recs[] = "Terdapat {$metrics['out_of_stock_count']} produk aktif yang stoknya habis (0) dan tidak bisa dijual di POS.";
        }

        if ($metrics['outstanding_debt'] > 0) {
            $recs[] = 'Terdapat piutang pelanggan sebesar ' . Format::rupiah($metrics['outstanding_debt']) . ' yang perlu ditindaklanjuti.';
        }

        if (empty($recs)) {
            $recs[] = 'Pertahankan ketersediaan stok produk terlaris dan pantau pergerakan stok secara berkala.';
        }

        return $recs;
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function buildAiPrompt(array $metrics): string
    {
        $json = json_encode($metrics, JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Anda adalah analis bisnis ritel UMKM Indonesia. Berdasarkan data faktual penjualan & stok berikut (JANGAN mengarang angka di luar data ini), buat ringkasan mingguan yang singkat, jelas, dan rekomendasi praktis untuk minggu depan dalam Bahasa Indonesia.
Kembalikan JSON valid SAJA dengan format:
{
  "summary": "paragraf ringkasan 2-4 kalimat berdasarkan angka observasi",
  "recommendations": ["rekomendasi 1", "rekomendasi 2", "rekomendasi 3"]
}

Data faktual:
{$json}
PROMPT;
    }
}
