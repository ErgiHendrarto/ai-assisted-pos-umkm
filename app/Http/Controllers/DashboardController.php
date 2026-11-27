<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Services\RetailIntelligenceService;
use App\Services\WeeklyInsightService;
use App\Support\Format;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly WeeklyInsightService $weeklyInsightService,
        private readonly RetailIntelligenceService $intelligenceService
    ) {}

    /**
     * Display the main retail POS dashboard.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $isAdmin = $user->isAdministrator();

        $todayStart = Carbon::today()->startOfDay();
        $todayEnd = Carbon::today()->endOfDay();
        $yesterdayStart = Carbon::yesterday()->startOfDay();
        $yesterdayEnd = Carbon::yesterday()->endOfDay();

        $todayCompleted = Transaction::completed()
            ->when(! $isAdmin, fn ($q) => $q->where('user_id', $user->id))
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->get(['total_amount', 'payment_method']);

        $todaySales = (float) $todayCompleted->sum('total_amount');
        $todayTransactionsCount = $todayCompleted->count();
        $todayAverageBasket = $todayTransactionsCount > 0 ? $todaySales / $todayTransactionsCount : 0.0;

        $yesterdaySales = (float) Transaction::completed()
            ->when(! $isAdmin, fn ($q) => $q->where('user_id', $user->id))
            ->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])
            ->sum('total_amount');

        $salesDeltaPercent = $yesterdaySales > 0
            ? round((($todaySales - $yesterdaySales) / $yesterdaySales) * 100, 1)
            : null;

        $todayCashCount = $todayCompleted->where('payment_method', 'cash')->count();
        $todayQrisCount = $todayCompleted->where('payment_method', 'qris')->count();

        $totalActiveProducts = (int) Product::active()->count();
        $totalCategoriesCount = (int) Category::count();

        $lowStockProducts = Product::with('category')
            ->lowStock()
            ->orderByRaw('(CAST(stock AS SIGNED) - CAST(minimum_stock AS SIGNED)) ASC')
            ->orderBy('stock')
            ->get();

        $lowStockCount = $lowStockProducts->count();
        $criticalLowStockCount = $lowStockProducts
            ->filter(fn (Product $product) => in_array($product->stockStatus(), ['out_of_stock', 'critical'], true))
            ->count();

        $outOfStockProducts = Product::with('category')
            ->active()
            ->where('stock', '<=', 0)
            ->orderBy('product_name')
            ->get();
        $outOfStockCount = $outOfStockProducts->count();

        $salesChart = $this->buildSevenDaySalesChart($isAdmin ? null : $user->id);

        $recentTransactions = Transaction::with(['user', 'customer'])
            ->when(! $isAdmin, fn ($q) => $q->where('user_id', $user->id))
            ->latest('created_at')
            ->latest('id')
            ->take(5)
            ->get();

        $quickProducts = $user->isCashier()
            ? Product::with('category')->active()->orderBy('product_name')->get()
            : collect();

        $quickActions = $this->buildQuickActions($isAdmin);

        // Date-filtered Admin Analytics
        [$period, $dateFrom, $dateTo, $periodStart, $periodEnd, $periodLabel] = $this->resolvePeriod($request);

        $analytics = $isAdmin
            ? $this->buildAdminAnalytics($periodStart, $periodEnd)
            : [
                'periodSales' => $todaySales,
                'periodTransactionsCount' => $todayTransactionsCount,
                'periodProductsSold' => 0.0,
                'periodGrossProfit' => 0.0,
                'topProducts' => collect(),
                'topProductsData' => [],
                'topCategories' => collect(),
                'outstandingDebts' => collect(),
                'debtTicker' => [],
                'totalOutstandingDebt' => 0.0,
            ];

        $weeklyInsight = $isAdmin
            ? $this->weeklyInsightService->getLatestOrGenerate($user)
            : null;

        $deadDays = (int) $request->query('dead_days', 30);
        $deadCategory = $request->query('dead_category') ? (int) $request->query('dead_category') : null;
        $deadStatus = (string) $request->query('dead_status', 'all');
        $profitCategory = $request->query('profit_category') ? (int) $request->query('profit_category') : null;

        $restockRecommendations = $isAdmin ? $this->intelligenceService->getRestockRecommendations() : [];
        $deadStockItems = $isAdmin ? $this->intelligenceService->getSlowMovingAndDeadStock($deadDays, $deadCategory, $deadStatus) : [];
        $profitIntelligence = $isAdmin ? $this->intelligenceService->getProfitIntelligence($periodStart, $periodEnd, $profitCategory) : [];
        $salesPatterns = $isAdmin ? $this->intelligenceService->getSalesPatterns($periodStart, $periodEnd) : [];
        $allCategories = $isAdmin ? Category::query()->orderBy('name')->get(['id', 'name']) : collect();

        return view('dashboard.index', [
            'greeting' => $this->greeting(),
            'todaySales' => $todaySales,
            'todaySalesFormatted' => Format::rupiah($todaySales),
            'yesterdaySalesFormatted' => Format::rupiah($yesterdaySales),
            'salesDeltaPercent' => $salesDeltaPercent,
            'todayTransactionsCount' => $todayTransactionsCount,
            'todayAverageBasketFormatted' => Format::rupiah($todayAverageBasket),
            'todayCashCount' => $todayCashCount,
            'todayQrisCount' => $todayQrisCount,
            'totalActiveProducts' => $totalActiveProducts,
            'totalCategoriesCount' => $totalCategoriesCount,
            'lowStockCount' => $lowStockCount,
            'criticalLowStockCount' => $criticalLowStockCount,
            'outOfStockCount' => $outOfStockCount,
            'outOfStockProducts' => $outOfStockProducts,
            'salesChart' => $salesChart,
            'recentTransactions' => $recentTransactions,
            'lowStockProducts' => $lowStockProducts,
            'quickProducts' => $quickProducts,
            'quickActions' => $quickActions,
            // Admin analytics & weekly insight
            'period' => $period,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'periodLabel' => $periodLabel,
            'periodSalesFormatted' => Format::rupiah($analytics['periodSales']),
            'periodTransactionsCount' => $analytics['periodTransactionsCount'],
            'periodProductsSoldFormatted' => Format::quantity($analytics['periodProductsSold']),
            'periodGrossProfitFormatted' => Format::rupiah($analytics['periodGrossProfit']),
            'topProducts' => $analytics['topProducts'],
            'topProductsData' => $analytics['topProductsData'],
            'topCategories' => $analytics['topCategories'],
            'outstandingDebts' => $analytics['outstandingDebts'],
            'debtTicker' => $analytics['debtTicker'],
            'totalOutstandingDebtFormatted' => Format::rupiah($analytics['totalOutstandingDebt']),
            'lastUpdated' => now()->format('H:i:s') . ' WIB',
            'weeklyInsight' => $weeklyInsight,
            'allCategories' => $allCategories,
            'restockRecommendations' => $restockRecommendations,
            'deadStockItems' => $deadStockItems,
            'profitIntelligence' => $profitIntelligence,
            'salesPatterns' => $salesPatterns,
            'deadDays' => $deadDays,
        ]);
    }

    public function analyticsData(Request $request): JsonResponse
    {
        [$period, $dateFrom, $dateTo, $periodStart, $periodEnd, $periodLabel] = $this->resolvePeriod($request);
        $analytics = $this->buildAdminAnalytics($periodStart, $periodEnd);
        $outOfStockCount = Product::active()->where('stock', '<=', 0)->count();

        $deadDays = (int) $request->query('dead_days', 30);
        $deadCategory = $request->query('dead_category') ? (int) $request->query('dead_category') : null;
        $deadStatus = (string) $request->query('dead_status', 'all');
        $profitCategory = $request->query('profit_category') ? (int) $request->query('profit_category') : null;

        return response()->json([
            'last_updated' => now()->format('H:i:s') . ' WIB',
            'period_label' => $periodLabel,
            'periodSalesFormatted' => Format::rupiah($analytics['periodSales']),
            'periodTransactionsCount' => $analytics['periodTransactionsCount'],
            'periodProductsSoldFormatted' => Format::quantity($analytics['periodProductsSold']),
            'periodGrossProfitFormatted' => Format::rupiah($analytics['periodGrossProfit']),
            'outOfStockCount' => $outOfStockCount,
            'totalOutstandingDebtFormatted' => Format::rupiah($analytics['totalOutstandingDebt']),
            'topProducts' => $analytics['topProductsData'],
            'topCategories' => $analytics['topCategories']->values()->all(),
            'debtTicker' => $analytics['debtTicker'],
            'restockRecommendations' => $this->intelligenceService->getRestockRecommendations(),
            'deadStockItems' => $this->intelligenceService->getSlowMovingAndDeadStock($deadDays, $deadCategory, $deadStatus),
            'profitIntelligence' => $this->intelligenceService->getProfitIntelligence($periodStart, $periodEnd, $profitCategory),
            'salesPatterns' => $this->intelligenceService->getSalesPatterns($periodStart, $periodEnd),
        ]);
    }

    public function regenerateInsight(Request $request): RedirectResponse
    {
        $this->weeklyInsightService->regenerate($request->user());

        return redirect()
            ->route('dashboard', $request->only(['period', 'date_from', 'date_to']))
            ->with('status', 'Ringkasan bisnis mingguan berhasil diperbarui.');
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: Carbon, 4: Carbon, 5: string}
     */
    private function resolvePeriod(Request $request): array
    {
        $period = $request->query('period', 'today');
        $today = Carbon::today();

        return match ($period) {
            'week' => [
                'week',
                $today->copy()->subDays(6)->format('Y-m-d'),
                $today->format('Y-m-d'),
                $today->copy()->subDays(6)->startOfDay(),
                $today->copy()->endOfDay(),
                'This Week (7 Days)',
            ],
            'month' => [
                'month',
                $today->copy()->startOfMonth()->format('Y-m-d'),
                $today->format('Y-m-d'),
                $today->copy()->startOfMonth()->startOfDay(),
                $today->copy()->endOfDay(),
                'This Month',
            ],
            'custom' => (function () use ($request, $today): array {
                $from = $request->query('date_from', $today->copy()->subDays(6)->format('Y-m-d'));
                $to = $request->query('date_to', $today->format('Y-m-d'));
                $start = Carbon::parse($from)->startOfDay();
                $end = Carbon::parse($to)->endOfDay();

                return ['custom', $from, $to, $start, $end, "{$from} to {$to}"];
            })(),
            default => [
                'today',
                $today->format('Y-m-d'),
                $today->format('Y-m-d'),
                $today->copy()->startOfDay(),
                $today->copy()->endOfDay(),
                'Today',
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAdminAnalytics(Carbon $start, Carbon $end): array
    {
        $completedTrx = Transaction::completed()
            ->whereBetween('created_at', [$start, $end])
            ->get(['id', 'total_amount']);

        $trxIds = $completedTrx->pluck('id');
        $periodSales = (float) $completedTrx->sum('total_amount');
        $periodTransactionsCount = $completedTrx->count();

        $periodProductsSold = 0.0;
        $periodGrossProfit = 0.0;
        $topProducts = collect();
        $topCategories = collect();

        if ($trxIds->isNotEmpty()) {
            $totalsRow = TransactionDetail::query()
                ->whereIn('transaction_id', $trxIds)
                ->selectRaw('COALESCE(SUM(quantity), 0) as total_qty, COALESCE(SUM(profit_snapshot), 0) as total_profit')
                ->first();

            $periodProductsSold = (float) ($totalsRow->total_qty ?? 0);
            $periodGrossProfit = (float) ($totalsRow->total_profit ?? 0);

            $topProducts = TransactionDetail::query()
                ->whereIn('transaction_id', $trxIds)
                ->select([
                    'product_id',
                    'product_name_snapshot',
                    'product_code_snapshot',
                    'unit',
                    DB::raw('SUM(quantity) as total_qty'),
                    DB::raw('SUM(subtotal) as total_revenue'),
                    DB::raw('SUM(profit_snapshot) as total_profit'),
                ])
                ->groupBy('product_id', 'product_name_snapshot', 'product_code_snapshot', 'unit')
                ->orderByDesc('total_qty')
                ->take(10)
                ->get();

            $categoryProductRows = TransactionDetail::query()
                ->join('products', 'transaction_details.product_id', '=', 'products.id')
                ->join('categories', 'products.category_id', '=', 'categories.id')
                ->whereIn('transaction_details.transaction_id', $trxIds)
                ->select([
                    'categories.id as category_id',
                    'categories.name as category_name',
                    'transaction_details.product_name_snapshot',
                    'transaction_details.unit',
                    DB::raw('SUM(transaction_details.quantity) as total_qty'),
                    DB::raw('SUM(transaction_details.subtotal) as total_revenue'),
                    DB::raw('SUM(transaction_details.profit_snapshot) as total_profit'),
                ])
                ->groupBy(
                    'categories.id',
                    'categories.name',
                    'transaction_details.product_name_snapshot',
                    'transaction_details.unit'
                )
                ->orderByDesc('total_revenue')
                ->get();

            $topCategories = $categoryProductRows
                ->groupBy('category_id')
                ->map(function ($rows, $catId): array {
                    $first = $rows->first();

                    return [
                        'category_id' => (int) $catId,
                        'category_name' => $first->category_name,
                        'total_qty' => (float) $rows->sum('total_qty'),
                        'total_revenue' => (float) $rows->sum('total_revenue'),
                        'total_profit' => (float) $rows->sum('total_profit'),
                        'products' => $rows->map(fn ($r): array => [
                            'name' => $r->product_name_snapshot,
                            'unit' => $r->unit,
                            'qty' => (float) $r->total_qty,
                            'revenue' => (float) $r->total_revenue,
                            'profit' => (float) $r->total_profit,
                        ])->values()->all(),
                    ];
                })
                ->sortByDesc('total_revenue')
                ->values();
        }

        $outstandingDebts = Transaction::query()
            ->with('customer:id,name,phone')
            ->where('transaction_status', '!=', Transaction::STATUS_CANCELLED)
            ->where('outstanding_balance', '>', 0)
            ->latest('created_at')
            ->take(8)
            ->get();

        $totalOutstandingDebt = (float) Transaction::query()
            ->where('transaction_status', '!=', Transaction::STATUS_CANCELLED)
            ->where('outstanding_balance', '>', 0)
            ->sum('outstanding_balance');

        $debtTicker = Customer::query()
            ->select(['customers.id', 'customers.customer_code', 'customers.name'])
            ->selectRaw('SUM(transactions.outstanding_balance) as outstanding_debt')
            ->join('transactions', 'customers.id', '=', 'transactions.customer_id')
            ->where('transactions.transaction_status', '!=', Transaction::STATUS_CANCELLED)
            ->where('transactions.outstanding_balance', '>', 0)
            ->groupBy('customers.id', 'customers.customer_code', 'customers.name')
            ->orderByDesc('outstanding_debt')
            ->get()
            ->map(fn ($row): array => [
                'id' => $row->id,
                'customer_code' => $row->customer_code,
                'name' => $row->name,
                'outstanding_debt' => (float) $row->outstanding_debt,
                'outstanding_debt_formatted' => Format::rupiah($row->outstanding_debt),
                'url' => route('customers.show', $row->id),
            ])
            ->values()
            ->all();

        $topProductsData = $topProducts->map(fn ($tp): array => [
            'product_id' => $tp->product_id,
            'name' => $tp->product_name_snapshot,
            'code' => $tp->product_code_snapshot,
            'unit' => $tp->unit,
            'qty' => (float) $tp->total_qty,
            'qty_formatted' => Format::quantity($tp->total_qty),
            'revenue' => (float) $tp->total_revenue,
            'revenue_formatted' => Format::rupiah($tp->total_revenue),
            'profit' => (float) $tp->total_profit,
            'profit_formatted' => Format::rupiah($tp->total_profit),
            'url' => $tp->product_id ? route('products.show', $tp->product_id) : null,
        ])->values()->all();

        return [
            'periodSales' => $periodSales,
            'periodTransactionsCount' => $periodTransactionsCount,
            'periodProductsSold' => $periodProductsSold,
            'periodGrossProfit' => $periodGrossProfit,
            'topProducts' => $topProducts,
            'topProductsData' => $topProductsData,
            'topCategories' => $topCategories,
            'outstandingDebts' => $outstandingDebts,
            'debtTicker' => $debtTicker,
            'totalOutstandingDebt' => $totalOutstandingDebt,
        ];
    }

    private function greeting(): string
    {
        $hour = (int) now()->format('G');

        return match (true) {
            $hour < 11 => 'Good morning',
            $hour < 15 => 'Good afternoon',
            default => 'Good evening',
        };
    }

    /**
     * @return array{
     *   labels: array<int, string>,
     *   values: array<int, float>,
     *   short_values: array<int, string>,
     *   has_data: bool,
     *   total_formatted: string,
     *   range_label: string
     * }
     */
    private function buildSevenDaySalesChart(?int $userId = null): array
    {
        $startDate = Carbon::today()->subDays(6)->startOfDay();
        $endDate = Carbon::today()->endOfDay();

        $transactions = Transaction::completed()
            ->when($userId !== null, fn ($q) => $q->where('user_id', $userId))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get(['total_amount', 'created_at']);

        $totalsByDate = $transactions
            ->groupBy(fn (Transaction $transaction) => $transaction->created_at->format('Y-m-d'))
            ->map(fn ($group) => (float) $group->sum('total_amount'));

        $labels = [];
        $values = [];
        $shortValues = [];

        for ($offset = 6; $offset >= 0; $offset--) {
            $date = Carbon::today()->subDays($offset);
            $dateKey = $date->format('Y-m-d');
            $dayTotal = (float) ($totalsByDate->get($dateKey, 0.0));

            $labels[] = $date->format('d M');
            $values[] = $dayTotal;
            $shortValues[] = $this->shortRupiah($dayTotal);
        }

        $totalSevenDays = array_sum($values);

        return [
            'labels' => $labels,
            'values' => $values,
            'short_values' => $shortValues,
            'has_data' => $transactions->isNotEmpty() && $totalSevenDays > 0,
            'total_formatted' => Format::rupiah($totalSevenDays),
            'range_label' => $startDate->format('d M') . ' - ' . $endDate->format('d M Y'),
        ];
    }

    private function shortRupiah(float $amount): string
    {
        if ($amount >= 1000000) {
            return 'Rp ' . number_format($amount / 1000000, 2, ',', '.') . 'm';
        }

        if ($amount >= 1000) {
            return 'Rp ' . number_format($amount / 1000, 0, ',', '.') . 'k';
        }

        return Format::rupiah($amount);
    }

    /**
     * @return array<int, array{title: string, description: string, route: string, icon: string, badge: string}>
     */
    private function buildQuickActions(bool $isAdministrator): array
    {
        if ($isAdministrator) {
            return [
                [
                    'title' => 'Manage Products',
                    'description' => 'Product catalog, units, cost & profit.',
                    'route' => route('products.index'),
                    'icon' => 'inventory_2',
                    'badge' => 'Catalog',
                ],
                [
                    'title' => 'View Transactions',
                    'description' => 'Completed, pending & debt receipts.',
                    'route' => route('transactions.index'),
                    'icon' => 'receipt_long',
                    'badge' => 'History',
                ],
                [
                    'title' => 'Manage Users',
                    'description' => 'Administrator & cashier accounts.',
                    'route' => route('users.index'),
                    'icon' => 'group',
                    'badge' => 'Staff',
                ],
            ];
        }

        return [
            [
                'title' => 'Start New Transaction',
                'description' => 'Open cashier checkout terminal.',
                'route' => route('pos.index'),
                'icon' => 'add_shopping_cart',
                'badge' => 'POS',
            ],
            [
                'title' => 'View Transaction History',
                'description' => 'Inspect store sales & receipt records.',
                'route' => route('transactions.index'),
                'icon' => 'receipt_long',
                'badge' => 'History',
            ],
        ];
    }
}
