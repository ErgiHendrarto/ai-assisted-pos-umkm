<?php

namespace App\Http\Controllers;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $movementType = $request->query('movement_type', 'all');

        $totalProducts = Product::active()->count();
        $lowStockCount = Product::active()
            ->where('stock', '>', 0)
            ->whereColumn('stock', '<=', 'minimum_stock')
            ->count();
        $outOfStockCount = Product::active()
            ->where('stock', '<=', 0)
            ->count();

        $lowStockProducts = Product::query()
            ->with('category')
            ->active()
            ->whereColumn('stock', '<=', 'minimum_stock')
            ->orderBy('stock')
            ->take(8)
            ->get();

        $movements = StockMovement::query()
            ->with(['product', 'user', 'transaction'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereHas('product', function ($q) use ($search): void {
                    $q->where('product_name', 'like', "%{$search}%")
                        ->orWhere('product_code', 'like', "%{$search}%");
                });
            })
            ->when($movementType !== 'all', fn ($q) => $q->where('movement_type', $movementType))
            ->latest('created_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('inventory.index', [
            'totalProducts' => $totalProducts,
            'lowStockCount' => $lowStockCount,
            'outOfStockCount' => $outOfStockCount,
            'lowStockProducts' => $lowStockProducts,
            'movements' => $movements,
            'movementTypes' => StockMovementType::cases(),
            'search' => $search,
            'movementType' => $movementType,
        ]);
    }
}
