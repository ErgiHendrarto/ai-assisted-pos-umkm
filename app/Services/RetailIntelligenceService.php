<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Support\Format;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RetailIntelligenceService
{
    /**
     * 1. Smart Restock Recommendation
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRestockRecommendations(): array
    {
        $since14 = Carbon::today()->subDays(13)->startOfDay();
        $since7 = Carbon::today()->subDays(6)->startOfDay();
        $prev7Start = Carbon::today()->subDays(13)->startOfDay();
        $prev7End = Carbon::today()->subDays(7)->endOfDay();

        $sales14 = TransactionDetail::query()
            ->join('transactions', 'transaction_details.transaction_id', '=', 'transactions.id')
            ->where('transactions.transaction_status', Transaction::STATUS_COMPLETED)
            ->where('transactions.created_at', '>=', $since14)
            ->whereNotNull('transaction_details.product_id')
            ->select([
                'transaction_details.product_id',
                DB::raw('SUM(CASE WHEN transaction_details.base_quantity > 0 THEN transaction_details.base_quantity ELSE transaction_details.quantity END) as total_base_qty'),
            ])
            ->groupBy('transaction_details.product_id')
            ->pluck('total_base_qty', 'product_id');

        $salesLast7 = TransactionDetail::query()
            ->join('transactions', 'transaction_details.transaction_id', '=', 'transactions.id')
            ->where('transactions.transaction_status', Transaction::STATUS_COMPLETED)
            ->where('transactions.created_at', '>=', $since7)
            ->whereNotNull('transaction_details.product_id')
            ->select([
                'transaction_details.product_id',
                DB::raw('SUM(CASE WHEN transaction_details.base_quantity > 0 THEN transaction_details.base_quantity ELSE transaction_details.quantity END) as total_base_qty'),
            ])
            ->groupBy('transaction_details.product_id')
            ->pluck('total_base_qty', 'product_id');

        $salesPrev7 = TransactionDetail::query()
            ->join('transactions', 'transaction_details.transaction_id', '=', 'transactions.id')
            ->where('transactions.transaction_status', Transaction::STATUS_COMPLETED)
            ->whereBetween('transactions.created_at', [$prev7Start, $prev7End])
            ->whereNotNull('transaction_details.product_id')
            ->select([
                'transaction_details.product_id',
                DB::raw('SUM(CASE WHEN transaction_details.base_quantity > 0 THEN transaction_details.base_quantity ELSE transaction_details.quantity END) as total_base_qty'),
            ])
            ->groupBy('transaction_details.product_id')
            ->pluck('total_base_qty', 'product_id');

        $products = Product::query()->with(['category', 'units'])->active()->get();
        $recommendations = [];

        foreach ($products as $product) {
            $stock = (float) $product->stock;
            $leadTime = max(1, (int) ($product->lead_time_days ?? 2));
            $safetyStock = max(0.0, (float) ($product->safety_stock ?? $product->minimum_stock));
            $sold14 = (float) ($sales14->get($product->id, 0));
            $avgDailySales = round($sold14 / 14.0, 2);

            $last7Qty = (float) ($salesLast7->get($product->id, 0));
            $prev7Qty = (float) ($salesPrev7->get($product->id, 0));
            $trend = $prev7Qty > 0
                ? ($last7Qty > $prev7Qty * 1.15 ? 'increasing' : ($last7Qty < $prev7Qty * 0.85 ? 'decreasing' : 'stable'))
                : ($last7Qty > 0 ? 'active' : 'none');

            $daysCoverage = $avgDailySales > 0 ? round($stock / $avgDailySales, 1) : null;
            $reorderPoint = ($avgDailySales * $leadTime) + $safetyStock;
            $needsRestock = $stock <= 0
                || $stock <= $product->minimum_stock
                || ($avgDailySales > 0 && $stock <= $reorderPoint);

            if (! $needsRestock) {
                continue;
            }

            // Target 7 days post-lead-time coverage + safety stock
            $targetStock = max((float) $product->minimum_stock * 2, ($avgDailySales * ($leadTime + 7)) + $safetyStock);
            $recommendedBaseQty = max(1, (int) ceil($targetStock - $stock));

            $priority = 'normal';
            if ($stock <= 0 || ($daysCoverage !== null && $daysCoverage <= $leadTime)) {
                $priority = 'critical';
            } elseif ($stock <= $reorderPoint || ($daysCoverage !== null && $daysCoverage <= $leadTime + 3)) {
                $priority = 'urgent';
            }

            $unitConversionLabel = $this->formatRecommendedUnits($product, $recommendedBaseQty);

            $reasons = [];
            if ($stock <= 0) {
                $reasons[] = 'Out of stock (0 ' . $product->unit . ')';
            } else {
                $reasons[] = 'Stock ' . Format::quantity($stock) . ' ' . $product->unit . ' <= reorder point (' . Format::quantity(round($reorderPoint, 1)) . ')';
            }
            if ($avgDailySales > 0) {
                $reasons[] = "avg {$avgDailySales}/day (~{$daysCoverage} days left, lead time {$leadTime}d)";
            } else {
                $reasons[] = 'below minimum threshold (' . Format::quantity($product->minimum_stock) . ' ' . $product->unit . ')';
            }

            $recommendations[] = [
                'product_id' => $product->id,
                'product_code' => $product->product_code,
                'product_name' => $product->product_name,
                'category_name' => $product->category?->name ?? '—',
                'current_stock' => $stock,
                'current_stock_formatted' => Format::quantity($stock) . ' ' . $product->unit,
                'avg_daily_sales' => $avgDailySales,
                'days_coverage' => $daysCoverage,
                'lead_time_days' => $leadTime,
                'safety_stock' => $safetyStock,
                'recommended_qty' => $recommendedBaseQty,
                'recommended_display' => $unitConversionLabel,
                'trend' => $trend,
                'priority' => $priority,
                'reason' => implode('; ', $reasons),
                'url' => route('products.show', $product),
            ];
        }

        $priorityWeight = ['critical' => 0, 'urgent' => 1, 'normal' => 2];
        usort($recommendations, function (array $a, array $b) use ($priorityWeight): int {
            $cmp = ($priorityWeight[$a['priority']] ?? 9) <=> ($priorityWeight[$b['priority']] ?? 9);

            return $cmp !== 0 ? $cmp : ($a['current_stock'] <=> $b['current_stock']);
        });

        return $recommendations;
    }

    /**
     * 2. Dead Stock & Slow-Moving Product Detection
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSlowMovingAndDeadStock(int $days = 30, ?int $categoryId = null, string $statusFilter = 'all'): array
    {
        $days = in_array($days, [7, 14, 30], true) ? $days : 30;
        $periodStart = Carbon::today()->subDays($days - 1)->startOfDay();
        $graceCutoff = Carbon::today()->subDays(min($days, 14))->startOfDay();

        $salesInPeriod = TransactionDetail::query()
            ->join('transactions', 'transaction_details.transaction_id', '=', 'transactions.id')
            ->where('transactions.transaction_status', Transaction::STATUS_COMPLETED)
            ->where('transactions.created_at', '>=', $periodStart)
            ->whereNotNull('transaction_details.product_id')
            ->select([
                'transaction_details.product_id',
                DB::raw('SUM(CASE WHEN transaction_details.base_quantity > 0 THEN transaction_details.base_quantity ELSE transaction_details.quantity END) as qty_sold'),
                DB::raw('MAX(transactions.created_at) as last_sale_at'),
            ])
            ->groupBy('transaction_details.product_id')
            ->get()
            ->keyBy('product_id');

        $allTimeLastSale = TransactionDetail::query()
            ->join('transactions', 'transaction_details.transaction_id', '=', 'transactions.id')
            ->where('transactions.transaction_status', Transaction::STATUS_COMPLETED)
            ->whereNotNull('transaction_details.product_id')
            ->select([
                'transaction_details.product_id',
                DB::raw('MAX(transactions.created_at) as last_sale_at'),
            ])
            ->groupBy('transaction_details.product_id')
            ->pluck('last_sale_at', 'product_id');

        $products = Product::query()
            ->with('category')
            ->active()
            ->where('stock', '>', 0)
            ->where('created_at', '<=', $graceCutoff)
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->get();

        $results = [];
        foreach ($products as $product) {
            $stock = (float) $product->stock;
            $periodRow = $salesInPeriod->get($product->id);
            $qtySold = (float) ($periodRow->qty_sold ?? 0);
            $lastSaleRaw = $periodRow->last_sale_at ?? $allTimeLastSale->get($product->id);
            $lastSaleDate = $lastSaleRaw ? Carbon::parse($lastSaleRaw)->format('d M Y') : 'Never';

            $dailyRate = $qtySold / $days;
            $daysRemaining = $dailyRate > 0 ? round($stock / $dailyRate, 0) : null;

            $status = null;
            $suggestedAction = '';

            if ($qtySold <= 0) {
                $status = 'dead_stock';
                $suggestedAction = $stock >= 20
                    ? 'Stop purchasing; bundle with fast-moving items or run clearance promotion.'
                    : 'Stop purchasing and review whether product should remain in catalog.';
            } elseif (($daysRemaining !== null && $daysRemaining > 45) || $qtySold < ($stock * 0.15)) {
                $status = 'slow_moving';
                $suggestedAction = 'Reduce next purchase order and consider promotional discount.';
            }

            if ($status === null) {
                continue;
            }

            if ($statusFilter !== 'all' && $statusFilter !== $status) {
                continue;
            }

            $inventoryValue = round($stock * (float) $product->cost_price, 2);

            $results[] = [
                'product_id' => $product->id,
                'product_code' => $product->product_code,
                'product_name' => $product->product_name,
                'category_name' => $product->category?->name ?? '—',
                'stock' => $stock,
                'stock_formatted' => Format::quantity($stock) . ' ' . $product->unit,
                'qty_sold' => $qtySold,
                'qty_sold_formatted' => Format::quantity($qtySold) . ' ' . $product->unit,
                'last_sale_date' => $lastSaleDate,
                'days_remaining' => $daysRemaining,
                'inventory_value' => $inventoryValue,
                'inventory_value_formatted' => Format::rupiah($inventoryValue),
                'status' => $status,
                'suggested_action' => $suggestedAction,
                'url' => route('products.show', $product),
            ];
        }

        usort($results, fn (array $a, array $b) => $b['inventory_value'] <=> $a['inventory_value']);

        return $results;
    }

    /**
     * 3. Profit Intelligence (using historical cost_price_snapshot & profit_snapshot)
     *
     * @return array<string, mixed>
     */
    public function getProfitIntelligence(Carbon $start, Carbon $end, ?int $categoryId = null): array
    {
        $rows = TransactionDetail::query()
            ->join('transactions', 'transaction_details.transaction_id', '=', 'transactions.id')
            ->leftJoin('products', 'transaction_details.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('transactions.transaction_status', Transaction::STATUS_COMPLETED)
            ->whereBetween('transactions.created_at', [$start, $end])
            ->when($categoryId, fn ($q) => $q->where('products.category_id', $categoryId))
            ->select([
                'transaction_details.product_id',
                'transaction_details.product_name_snapshot',
                'transaction_details.product_code_snapshot',
                'transaction_details.unit',
                DB::raw('COALESCE(categories.name, "Uncategorized") as category_name'),
                DB::raw('SUM(transaction_details.quantity) as total_qty'),
                DB::raw('SUM(transaction_details.subtotal) as total_revenue'),
                DB::raw('SUM(transaction_details.cost_price_snapshot * transaction_details.quantity) as total_cost'),
                DB::raw('SUM(transaction_details.profit_snapshot) as total_profit'),
            ])
            ->groupBy(
                'transaction_details.product_id',
                'transaction_details.product_name_snapshot',
                'transaction_details.product_code_snapshot',
                'transaction_details.unit',
                'categories.name'
            )
            ->get()
            ->map(function ($r): array {
                $rev = (float) $r->total_revenue;
                $cost = (float) $r->total_cost;
                $profit = (float) $r->total_profit;
                $margin = $rev > 0 ? round(($profit / $rev) * 100, 1) : 0.0;

                return [
                    'product_id' => $r->product_id,
                    'name' => $r->product_name_snapshot,
                    'code' => $r->product_code_snapshot,
                    'unit' => $r->unit,
                    'category_name' => $r->category_name,
                    'qty' => (float) $r->total_qty,
                    'qty_formatted' => Format::quantity($r->total_qty),
                    'revenue' => $rev,
                    'revenue_formatted' => Format::rupiah($rev),
                    'cost' => $cost,
                    'cost_formatted' => Format::rupiah($cost),
                    'profit' => $profit,
                    'profit_formatted' => Format::rupiah($profit),
                    'margin_pct' => $margin,
                ];
            });

        $byCategory = $rows->groupBy('category_name')
            ->map(function (Collection $group, string $catName): array {
                $rev = (float) $group->sum('revenue');
                $cost = (float) $group->sum('cost');
                $profit = (float) $group->sum('profit');

                return [
                    'category_name' => $catName,
                    'revenue' => $rev,
                    'revenue_formatted' => Format::rupiah($rev),
                    'cost' => $cost,
                    'profit' => $profit,
                    'profit_formatted' => Format::rupiah($profit),
                    'margin_pct' => $rev > 0 ? round(($profit / $rev) * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('profit')
            ->values()
            ->all();

        return [
            'top_revenue' => $rows->sortByDesc('revenue')->take(5)->values()->all(),
            'top_profit' => $rows->sortByDesc('profit')->take(5)->values()->all(),
            'top_quantity' => $rows->sortByDesc('qty')->take(5)->values()->all(),
            'lowest_margin' => $rows->filter(fn ($r) => $r['revenue'] > 0)->sortBy('margin_pct')->take(5)->values()->all(),
            'by_category' => $byCategory,
        ];
    }

    /**
     * 4. Sales Pattern Analysis (Peak Hours, Days of Week, Weekday vs Weekend)
     *
     * @return array<string, mixed>
     */
    public function getSalesPatterns(Carbon $start, Carbon $end): array
    {
        $transactions = Transaction::completed()
            ->whereBetween('created_at', [$start, $end])
            ->get(['total_amount', 'created_at']);

        $totalCount = $transactions->count();
        $totalRevenue = (float) $transactions->sum('total_amount');

        $hourlyCounts = array_fill(0, 24, 0);
        $hourlyRevenue = array_fill(0, 24, 0.0);
        $dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $weekdayCounts = array_fill_keys($dayNames, 0);
        $weekdayRevenue = array_fill_keys($dayNames, 0.0);

        $weekdayTotal = ['count' => 0, 'revenue' => 0.0];
        $weekendTotal = ['count' => 0, 'revenue' => 0.0];

        foreach ($transactions as $trx) {
            $dt = $trx->created_at->timezone('Asia/Jakarta');
            $hour = (int) $dt->format('G');
            $dayShort = $dt->format('D');
            $amt = (float) $trx->total_amount;

            $hourlyCounts[$hour]++;
            $hourlyRevenue[$hour] += $amt;

            if (isset($weekdayCounts[$dayShort])) {
                $weekdayCounts[$dayShort]++;
                $weekdayRevenue[$dayShort] += $amt;
            }

            if ($dt->isWeekend()) {
                $weekendTotal['count']++;
                $weekendTotal['revenue'] += $amt;
            } else {
                $weekdayTotal['count']++;
                $weekdayTotal['revenue'] += $amt;
            }
        }

        // Find busiest 3-hour window
        $bestWindowStart = 0;
        $bestWindowCount = 0;
        for ($h = 0; $h <= 21; $h++) {
            $sum = $hourlyCounts[$h] + $hourlyCounts[$h + 1] + $hourlyCounts[$h + 2];
            if ($sum > $bestWindowCount) {
                $bestWindowCount = $sum;
                $bestWindowStart = $h;
            }
        }

        $busiestHour = array_keys($hourlyCounts, max($hourlyCounts))[0] ?? 0;
        $busiestDay = array_keys($weekdayCounts, max($weekdayCounts))[0] ?? 'Mon';
        $activeHours = count(array_filter($hourlyCounts, fn ($c) => $c > 0));
        $avgPerActiveHour = $activeHours > 0 ? round($totalCount / $activeHours, 1) : 0.0;
        $avgBasket = $totalCount > 0 ? round($totalRevenue / $totalCount, 0) : 0.0;

        $insight = 'Insufficient transaction history to detect reliable peak patterns.';
        if ($totalCount >= 3 && $bestWindowCount > 0) {
            $pct = round(($bestWindowCount / $totalCount) * 100);
            $startLabel = sprintf('%02d:00', $bestWindowStart);
            $endLabel = sprintf('%02d:00', $bestWindowStart + 3);
            $insight = "Your busiest period is between {$startLabel} and {$endLabel} ({$pct}% of transactions). Peak day is {$busiestDay}. Ensure fast-moving items are stocked and cashier capacity is ready before peak hours.";
        }

        return [
            'has_sufficient_data' => $totalCount >= 3,
            'total_transactions' => $totalCount,
            'avg_per_hour' => $avgPerActiveHour,
            'avg_basket_formatted' => Format::rupiah($avgBasket),
            'busiest_window' => sprintf('%02d:00 - %02d:00', $bestWindowStart, $bestWindowStart + 3),
            'busiest_hour' => sprintf('%02d:00', $busiestHour),
            'busiest_day' => $busiestDay,
            'weekday_total' => $weekdayTotal,
            'weekend_total' => $weekendTotal,
            'hourly_labels' => array_map(fn ($h) => sprintf('%02d:00', $h), range(0, 23)),
            'hourly_counts' => array_values($hourlyCounts),
            'weekday_labels' => $dayNames,
            'weekday_counts' => array_values($weekdayCounts),
            'insight' => $insight,
        ];
    }

    /**
     * 5. Smart Product Alternatives When Out of Stock
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAlternativesForProduct(Product $product, int $limit = 4): array
    {
        $results = collect();
        $seenIds = [$product->id];

        // 1. Admin-configured manual alternatives first
        $manual = $product->alternatives()
            ->with('units')
            ->where('products.is_active', true)
            ->where('products.stock', '>', 0)
            ->take($limit)
            ->get();

        foreach ($manual as $alt) {
            $seenIds[] = $alt->id;
            $results->push($this->formatAlternative($alt, 'Configured Alternative'));
        }

        if ($results->count() >= $limit) {
            return $results->take($limit)->values()->all();
        }

        // 2. Automatic similarity scoring among active in-stock products
        $candidates = Product::query()
            ->with(['category', 'units'])
            ->active()
            ->where('stock', '>', 0)
            ->whereNotIn('id', $seenIds)
            ->get();

        $basePrice = (float) $product->price;
        $baseName = mb_strtolower($product->product_name);

        $scored = $candidates->map(function (Product $cand) use ($product, $basePrice, $baseName): array {
            $score = 0;
            $reasons = [];

            if ($cand->category_id === $product->category_id) {
                $score += 40;
                $reasons[] = 'Same category';
            }

            similar_text($baseName, mb_strtolower($cand->product_name), $pct);
            if ($pct >= 35) {
                $score += (int) round(($pct / 100) * 35);
                $reasons[] = 'Similar product';
            }

            if (strtolower($cand->unit) === strtolower($product->unit)) {
                $score += 15;
            }

            if ($basePrice > 0) {
                $diffRatio = abs((float) $cand->price - $basePrice) / $basePrice;
                if ($diffRatio <= 0.40) {
                    $score += 10;
                    $reasons[] = 'Similar price';
                }
            }

            return [
                'product' => $cand,
                'score' => $score,
                'reason' => implode(' • ', $reasons) ?: 'In-stock substitute',
            ];
        })
            ->filter(fn (array $row) => $row['score'] >= 40)
            ->sortByDesc('score')
            ->take($limit - $results->count());

        foreach ($scored as $row) {
            $results->push($this->formatAlternative($row['product'], $row['reason']));
        }

        return $results->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatAlternative(Product $product, string $reason): array
    {
        return [
            'id' => $product->id,
            'product_code' => $product->product_code,
            'product_name' => $product->product_name,
            'price' => (float) $product->price,
            'price_formatted' => Format::rupiah($product->price),
            'stock' => (float) $product->stock,
            'stock_formatted' => Format::quantity($product->stock),
            'unit' => $product->unit,
            'reason' => $reason,
        ];
    }

    private function formatRecommendedUnits(Product $product, int $baseQty): string
    {
        $largestUnit = $product->units
            ->sortByDesc('conversion_ratio')
            ->first(fn ($u) => (float) $u->conversion_ratio > 1 && $baseQty >= (float) $u->conversion_ratio);

        if ($largestUnit) {
            $ratio = (float) $largestUnit->conversion_ratio;
            $unitQty = round($baseQty / $ratio, 1);

            return Format::quantity($unitQty) . ' ' . $largestUnit->unit_name . ' (' . Format::quantity($baseQty) . ' ' . $product->unit . ')';
        }

        return Format::quantity($baseQty) . ' ' . $product->unit;
    }
}
