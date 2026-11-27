<?php

namespace App\Http\Controllers;

use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Services\InventoryService;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public const UNITS = ['pcs', 'piece', 'stick', 'pack', 'box', 'carton', 'can', 'bottle', 'bundle', 'slop', 'ball', 'kg', 'gram', 'liter'];

    public function __construct(
        private readonly ProductService $productService,
        private readonly InventoryService $inventoryService
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $categoryId = $request->query('category_id');
        $stockStatus = $request->query('stock_status', 'all');
        $activeFilter = $request->query('active', 'all');

        $products = Product::query()
            ->with(['category', 'units'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('product_name', 'like', "%{$search}%")
                        ->orWhere('product_code', 'like', "%{$search}%");
                });
            })
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->when($activeFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($activeFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($stockStatus === 'out_of_stock', fn ($q) => $q->where('stock', '<=', 0))
            ->when($stockStatus === 'low', fn ($q) => $q->where('stock', '>', 0)->whereColumn('stock', '<=', 'minimum_stock'))
            ->when($stockStatus === 'in_stock', fn ($q) => $q->whereColumn('stock', '>', 'minimum_stock'))
            ->orderBy('product_name')
            ->paginate(15)
            ->withQueryString();

        $categories = Category::query()->orderBy('name')->get(['id', 'name']);

        return view('products.index', [
            'products' => $products,
            'categories' => $categories,
            'search' => $search,
            'categoryId' => $categoryId,
            'stockStatus' => $stockStatus,
            'activeFilter' => $activeFilter,
        ]);
    }

    public function create(): View
    {
        return view('products.create', [
            'categories' => Category::query()->active()->orderBy('name')->get(),
            'units' => $this->availableUnits(),
            'suggestedSku' => $this->productService->generateNextSku(),
            'allProducts' => Product::query()->active()->orderBy('product_name')->get(['id', 'product_code', 'product_name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'product_code' => ['nullable', 'string', 'max:50', Rule::unique('products', 'product_code')],
            'product_name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'stock' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'minimum_stock' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:90'],
            'safety_stock' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'unit' => ['required', 'string', 'max:30'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
            'product_units' => ['nullable', 'array'],
            'product_units.*.unit_name' => ['nullable', 'string', 'max:50'],
            'product_units.*.conversion_ratio' => ['nullable', 'numeric', 'min:0.001', 'max:999999'],
            'product_units.*.selling_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'alternative_product_ids' => ['nullable', 'array'],
            'alternative_product_ids.*' => ['integer', 'exists:products,id'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $product = $this->productService->create(
            $validated,
            $request->file('image'),
            $request->user()
        );

        return redirect()
            ->route('products.show', $product)
            ->with('status', 'Produk berhasil ditambahkan.');
    }

    public function show(Product $product): View
    {
        $product->load(['category', 'units']);

        $movements = $product->stockMovements()
            ->with(['user', 'transaction'])
            ->latest('created_at')
            ->latest('id')
            ->paginate(10);

        return view('products.show', [
            'product' => $product,
            'movements' => $movements,
        ]);
    }

    public function edit(Product $product): View
    {
        $product->load(['units', 'alternatives']);

        return view('products.edit', [
            'product' => $product,
            'categories' => Category::query()->orderBy('name')->get(),
            'units' => $this->availableUnits(),
            'allProducts' => Product::query()->active()->where('id', '!=', $product->id)->orderBy('product_name')->get(['id', 'product_code', 'product_name']),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'product_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('products', 'product_code')->ignore($product->id),
            ],
            'product_name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'minimum_stock' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:90'],
            'safety_stock' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'unit' => ['required', 'string', 'max:30'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'product_units' => ['nullable', 'array'],
            'product_units.*.unit_name' => ['nullable', 'string', 'max:50'],
            'product_units.*.conversion_ratio' => ['nullable', 'numeric', 'min:0.001', 'max:999999'],
            'product_units.*.selling_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'alternative_product_ids' => ['nullable', 'array'],
            'alternative_product_ids.*' => ['integer', 'exists:products,id'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['product_units'] = $validated['product_units'] ?? [];
        $validated['alternative_product_ids'] = $validated['alternative_product_ids'] ?? [];

        $this->productService->update(
            $product,
            $validated,
            $request->file('image'),
            $request->boolean('remove_image')
        );

        return redirect()
            ->route('products.show', $product)
            ->with('status', 'Data produk berhasil diperbarui. Riwayat transaksi lama tetap aman menggunakan snapshot.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->productService->delete($product);

        return redirect()
            ->route('products.index')
            ->with('status', 'Produk berhasil diarsipkan.');
    }

    public function adjustStock(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'movement_type' => ['required', Rule::in([
                StockMovementType::StockIn->value,
                StockMovementType::StockOut->value,
                StockMovementType::Adjustment->value,
            ])],
            'quantity' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'note' => ['required', 'string', 'max:500'],
        ]);

        $type = StockMovementType::from($validated['movement_type']);

        if ($type === StockMovementType::Adjustment) {
            $this->inventoryService->setExactStock(
                $product,
                (float) $validated['quantity'],
                $request->user(),
                $validated['note']
            );
        } else {
            $this->inventoryService->adjustStock(
                $product,
                (float) $validated['quantity'],
                $type,
                $request->user(),
                $validated['note']
            );
        }

        return redirect()
            ->route('products.show', $product)
            ->with('status', 'Stok produk berhasil diperbarui dan dicatat di riwayat pergerakan stok.');
    }

    /**
     * @return array<int, string>
     */
    private function availableUnits(): array
    {
        $dbUnits = Unit::query()->active()->orderBy('name')->pluck('name')->all();

        return ! empty($dbUnits) ? $dbUnits : self::UNITS;
    }
}
