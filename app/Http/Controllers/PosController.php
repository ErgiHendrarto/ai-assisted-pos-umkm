<?php

namespace App\Http\Controllers;

use App\Enums\CartStatus;
use App\Models\AiSetting;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Staff;
use App\Models\Transaction;
use App\Services\CartService;
use App\Services\RetailIntelligenceService;
use App\Services\TransactionService;
use App\Support\Format;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PosController extends Controller
{
    private const CLIENT_CATALOG_LIMIT = 500;

    public function __construct(
        private readonly CartService $cartService,
        private readonly TransactionService $transactionService,
        private readonly RetailIntelligenceService $intelligenceService
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $categoryId = $request->query('category_id');

        $totalActiveProducts = Product::query()->active()->count();
        $useClientFiltering = $totalActiveProducts <= self::CLIENT_CATALOG_LIMIT;

        $catalogProducts = Product::query()
            ->with(['category:id,name', 'units'])
            ->active()
            ->when(! $useClientFiltering && $search !== '', function ($query) use ($search): void {
                $this->applySearchFilter($query, $search);
            })
            ->when(! $useClientFiltering && $categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->orderBy('product_name')
            ->when(! $useClientFiltering, fn ($q) => $q->limit(60))
            ->get()
            ->map(fn (Product $product): array => $this->serializeProduct($product))
            ->values();

        $categories = Category::query()->active()->orderBy('name')->get(['id', 'name']);
        $customers = Customer::query()
            ->active()
            ->withSum([
                'transactions as outstanding_debt_sum' => fn ($q) => $q
                    ->where('transaction_status', '!=', Transaction::STATUS_CANCELLED)
                    ->where('outstanding_balance', '>', 0),
            ], 'outstanding_balance')
            ->orderBy('name')
            ->get(['id', 'customer_code', 'name', 'phone'])
            ->map(fn (Customer $c): array => [
                'id' => $c->id,
                'customer_code' => $c->customer_code,
                'name' => $c->name,
                'phone' => $c->phone,
                'outstanding_debt' => round((float) ($c->outstanding_debt_sum ?? 0), 2),
            ])
            ->values();
        $cart = $this->cartService->getOrCreateActiveCart($request->user());
        $aiSetting = AiSetting::current();
        $pendingTransactions = $this->getPendingTransactionsForUser($request->user());

        return view('pos.index', [
            'catalogProducts' => $catalogProducts,
            'useClientFiltering' => $useClientFiltering,
            'categories' => $categories,
            'customers' => $customers,
            'cart' => $cart,
            'pendingTransactions' => $pendingTransactions,
            'checkoutDraft' => $request->session()->get('pos_checkout_draft', []),
            'search' => $search,
            'categoryId' => $categoryId ? (string) $categoryId : '',
            'aiAvailable' => $aiSetting->is_active && $aiSetting->hasApiKey(),
            'aiSetting' => $aiSetting,
        ]);
    }

    public function searchProducts(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $categoryId = $request->query('category_id');

        $products = Product::query()
            ->with(['category:id,name', 'units'])
            ->active()
            ->when($search !== '', fn ($q) => $this->applySearchFilter($q, $search))
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->orderBy('product_name')
            ->limit(60)
            ->get()
            ->map(fn (Product $product): array => $this->serializeProduct($product))
            ->values();

        return response()->json([
            'products' => $products,
        ]);
    }

    public function alternatives(Product $product): JsonResponse
    {
        return response()->json([
            'product_name' => $product->product_name,
            'alternatives' => $this->intelligenceService->getAlternativesForProduct($product, 4),
        ]);
    }

    private function applySearchFilter($query, string $search): void
    {
        $tokens = preg_split('/\s+/', trim($search)) ?: [];
        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }
            $query->where(function ($q) use ($token): void {
                $q->where('product_name', 'like', "%{$token}%")
                    ->orWhere('product_code', 'like', "%{$token}%");
            });
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProduct(Product $product): array
    {
        $stock = (float) $product->stock;

        $units = [
            [
                'id' => '',
                'unit_name' => $product->unit,
                'conversion_ratio' => 1.0,
                'price' => (float) $product->price,
                'price_formatted' => Format::rupiah($product->price),
            ],
        ];

        foreach ($product->units as $unit) {
            $units[] = [
                'id' => (string) $unit->id,
                'unit_name' => $unit->unit_name,
                'conversion_ratio' => (float) $unit->conversion_ratio,
                'price' => (float) $unit->selling_price,
                'price_formatted' => Format::rupiah($unit->selling_price),
            ];
        }

        return [
            'id' => $product->id,
            'category_id' => $product->category_id,
            'category_name' => $product->category?->name ?? '',
            'product_code' => $product->product_code,
            'product_name' => $product->product_name,
            'price' => (float) $product->price,
            'price_formatted' => Format::rupiah($product->price),
            'stock' => $stock,
            'stock_formatted' => Format::quantity($stock),
            'unit' => $product->unit,
            'units' => $units,
            'selected_unit_id' => '',
            'is_low_stock' => $product->isLowStock(),
            'out_of_stock' => $stock <= 0,
        ];
    }

    public function addToCart(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'product_unit_id' => ['nullable', 'exists:product_units,id'],
            'quantity' => ['nullable', 'numeric', 'min:0.001', 'max:99999'],
        ]);

        $product = Product::query()->findOrFail($validated['product_id']);
        $productUnit = ! empty($validated['product_unit_id'])
            ? ProductUnit::query()->findOrFail($validated['product_unit_id'])
            : null;
        $quantity = (float) ($validated['quantity'] ?? 1);

        $this->cartService->addItem($request->user(), $product, $quantity, $productUnit);

        return redirect()
            ->route('pos.index', $request->only(['search', 'category_id']))
            ->with('status', "{$product->product_name} ditambahkan ke keranjang.");
    }

    public function updateCartItem(Request $request, CartItem $cartItem): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0', 'max:99999'],
        ]);

        $this->cartService->updateItemQuantity($request->user(), $cartItem, (float) $validated['quantity']);

        $target = $request->input('redirect_to') === 'checkout'
            ? route('pos.checkout.review')
            : route('pos.index', $request->only(['search', 'category_id']));

        return redirect($target)
            ->with('status', 'Keranjang diperbarui.');
    }

    public function removeCartItem(Request $request, CartItem $cartItem): RedirectResponse
    {
        $this->cartService->removeItem($request->user(), $cartItem);

        $target = $request->input('redirect_to') === 'checkout'
            ? route('pos.checkout.review')
            : route('pos.index', $request->only(['search', 'category_id']));

        return redirect($target)
            ->with('status', 'Produk dihapus dari keranjang.');
    }

    public function clearCart(Request $request): RedirectResponse
    {
        $this->cartService->clearCart($request->user());
        $request->session()->forget('pos_checkout_draft');

        return redirect()
            ->route('pos.index')
            ->with('status', 'Keranjang dikosongkan.');
    }

    public function updateCartItemPrice(Request $request, CartItem $cartItem): RedirectResponse
    {
        $validated = $request->validate([
            'price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $this->cartService->updateItemPrice(
            $request->user(),
            $cartItem,
            (float) $validated['price'],
            $validated['reason'] ?? null
        );

        $target = $request->input('redirect_to') === 'checkout'
            ? route('pos.checkout.review')
            : route('pos.index', $request->only(['search', 'category_id']));

        return redirect($target)->with('status', 'Harga item di keranjang diperbarui.');
    }

    public function holdCart(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'payment_method' => ['nullable', Rule::in([
                Transaction::PAYMENT_CASH,
                Transaction::PAYMENT_QRIS,
                Transaction::PAYMENT_OTHER,
            ])],
            'amount_paid' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'include_previous_debt' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $heldCart = $this->cartService->holdActiveCart($request->user(), [
            'customer_id' => $validated['customer_id'] ?? null,
            'payment_method' => $validated['payment_method'] ?? Transaction::PAYMENT_CASH,
            'amount_paid' => $validated['amount_paid'] ?? null,
            'discount_amount' => (float) ($validated['discount_amount'] ?? 0),
            'include_previous_debt' => $request->boolean('include_previous_debt'),
            'notes' => $validated['notes'] ?? null,
        ]);

        $request->session()->forget('pos_checkout_draft');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => "Transaksi ditahan ({$heldCart->hold_reference}).",
                'hold_reference' => $heldCart->hold_reference,
                'pending_transactions' => $this->getPendingTransactionsForUser($request->user()),
            ]);
        }

        return redirect()
            ->route('pos.index')
            ->with('status', "Transaksi berhasil ditahan ({$heldCart->hold_reference}).");
    }

    public function pendingTransactions(Request $request): JsonResponse
    {
        return response()->json([
            'pending_transactions' => $this->getPendingTransactionsForUser($request->user()),
        ]);
    }

    public function resumePending(Request $request, Cart $cart): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'conflict_action' => ['nullable', Rule::in(['abort', 'hold_current'])],
            'current_draft' => ['nullable', 'array'],
        ]);

        $result = $this->cartService->resumePendingCart(
            $request->user(),
            $cart,
            $validated['conflict_action'] ?? 'abort',
            $validated['current_draft'] ?? $request->session()->get('pos_checkout_draft', [])
        );

        if ($result['conflict']) {
            if ($request->expectsJson()) {
                return response()->json([
                    'conflict' => true,
                    'message' => 'Anda memiliki keranjang aktif yang belum diselesaikan.',
                    'active_items_count' => $result['active_items_count'],
                    'active_total' => $result['active_total'],
                ], 409);
            }

            return redirect()
                ->route('pos.index')
                ->withErrors(['cart' => 'Keranjang aktif masih berisi produk. Tahan atau kosongkan keranjang terlebih dahulu.']);
        }

        /** @var Cart $resumedCart */
        $resumedCart = $result['cart'];
        $request->session()->put('pos_checkout_draft', [
            'customer_id' => $resumedCart->customer_id,
            'payment_method' => $resumedCart->payment_method ?: Transaction::PAYMENT_CASH,
            'amount_paid' => $resumedCart->amount_paid !== null ? (float) $resumedCart->amount_paid : null,
            'discount_amount' => (float) $resumedCart->discount_amount,
            'include_previous_debt' => (bool) $resumedCart->include_previous_debt,
            'notes' => $resumedCart->notes,
        ]);

        $warnings = $result['warnings'] ?? [];
        if (! empty($warnings)) {
            $request->session()->flash('pos_resume_warnings', $warnings);
        }

        $statusMessage = "Transaksi pending ({$resumedCart->hold_reference}) dilanjutkan.";
        $request->session()->flash('status', $statusMessage);

        if ($request->expectsJson()) {
            return response()->json([
                'conflict' => false,
                'message' => $statusMessage,
                'warnings' => $warnings,
                'redirect' => route('pos.index'),
            ]);
        }

        return redirect()->route('pos.index');
    }

    public function cancelPending(Request $request, Cart $cart): JsonResponse|RedirectResponse
    {
        $cancelled = $this->cartService->cancelPendingCart($request->user(), $cart);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => "Transaksi pending ({$cancelled->hold_reference}) dibatalkan.",
                'pending_transactions' => $this->getPendingTransactionsForUser($request->user()),
            ]);
        }

        return redirect()
            ->route('pos.index')
            ->with('status', "Transaksi pending ({$cancelled->hold_reference}) dibatalkan.");
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function getPendingTransactionsForUser(\App\Models\User $user)
    {
        return Cart::query()
            ->with(['customer:id,customer_code,name', 'user:id,name'])
            ->withCount('items')
            ->withSum('items as total_quantity', 'quantity')
            ->where('cart_status', CartStatus::Pending)
            ->when(! $user->isAdministrator(), fn ($q) => $q->where('user_id', $user->id))
            ->latest('held_at')
            ->latest('id')
            ->get()
            ->map(fn (Cart $c): array => [
                'id' => $c->id,
                'hold_reference' => $c->hold_reference ?? ('HOLD-' . $c->id),
                'customer_id' => $c->customer_id,
                'customer_name' => $c->customer?->name ?? ($c->customer_name_snapshot ?: 'Walk-in Customer'),
                'customer_code' => $c->customer?->customer_code,
                'items_count' => (int) ($c->items_count ?? 0),
                'total_quantity' => round((float) ($c->total_quantity ?? 0), 3),
                'subtotal' => round((float) $c->subtotal, 2),
                'discount_amount' => round((float) $c->discount_amount, 2),
                'net_total' => round(max(0.0, (float) $c->subtotal - (float) $c->discount_amount), 2),
                'include_previous_debt' => (bool) $c->include_previous_debt,
                'held_at_formatted' => ($c->held_at ?? $c->updated_at)?->format('d M H:i'),
                'cashier_name' => $c->user?->name ?? '—',
            ])
            ->values();
    }

    public function customerDebt(Customer $customer): JsonResponse
    {
        return response()->json([
            'customer_id' => $customer->id,
            'customer_code' => $customer->customer_code,
            'name' => $customer->name,
            'outstanding_debt' => round($customer->totalOutstandingDebt(), 2),
        ]);
    }

    public function prepareCheckout(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'payment_method' => ['required', Rule::in([
                Transaction::PAYMENT_CASH,
                Transaction::PAYMENT_QRIS,
                Transaction::PAYMENT_OTHER,
            ])],
            'amount_paid' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'notes' => ['nullable', 'string', 'max:500'],
            'include_previous_debt' => ['nullable', 'boolean'],
        ]);

        $cart = $this->cartService->getOrCreateActiveCart($request->user());
        if ($cart->items->isEmpty()) {
            return redirect()
                ->route('pos.index')
                ->withErrors(['cart' => 'Keranjang belanja masih kosong.']);
        }

        $request->session()->put('pos_checkout_draft', [
            'customer_id' => $validated['customer_id'] ?? null,
            'payment_method' => $validated['payment_method'],
            'amount_paid' => isset($validated['amount_paid']) ? (float) $validated['amount_paid'] : null,
            'discount_amount' => (float) ($validated['discount_amount'] ?? 0),
            'notes' => $validated['notes'] ?? null,
            'include_previous_debt' => $request->boolean('include_previous_debt'),
        ]);

        return redirect()->route('pos.checkout.review');
    }

    public function reviewCheckout(Request $request): View|RedirectResponse
    {
        $cart = $this->cartService->getOrCreateActiveCart($request->user());

        if ($cart->items->isEmpty()) {
            return redirect()
                ->route('pos.index')
                ->with('status', 'Keranjang kosong. Silakan pilih produk terlebih dahulu.');
        }

        foreach ($cart->items as $item) {
            if ($item->product) {
                $this->cartService->updateItemQuantity($request->user(), $item, (float) $item->quantity);
            }
        }
        $cart = $this->cartService->getOrCreateActiveCart($request->user());

        $draft = $request->session()->get('pos_checkout_draft', []);
        $paymentMethod = $draft['payment_method'] ?? Transaction::PAYMENT_CASH;
        $customerId = $draft['customer_id'] ?? null;
        $subtotal = (float) $cart->subtotal;
        $discount = min($subtotal, max(0.0, (float) ($draft['discount_amount'] ?? 0)));
        $netTotal = round($subtotal - $discount, 2);
        $amountPaid = array_key_exists('amount_paid', $draft) && $draft['amount_paid'] !== null
            ? (float) $draft['amount_paid']
            : $netTotal;

        $customers = Customer::query()
            ->active()
            ->withSum([
                'transactions as outstanding_debt_sum' => fn ($q) => $q
                    ->where('transaction_status', '!=', Transaction::STATUS_CANCELLED)
                    ->where('outstanding_balance', '>', 0),
            ], 'outstanding_balance')
            ->orderBy('name')
            ->get(['id', 'customer_code', 'name', 'phone'])
            ->map(fn (Customer $c): array => [
                'id' => $c->id,
                'customer_code' => $c->customer_code,
                'name' => $c->name,
                'phone' => $c->phone,
                'outstanding_debt' => round((float) ($c->outstanding_debt_sum ?? 0), 2),
            ])
            ->values();

        $staffMembers = Staff::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'staff_code', 'name'])
            ->map(fn (Staff $s): array => [
                'id' => $s->id,
                'staff_code' => $s->staff_code,
                'name' => $s->name,
            ])
            ->values();

        return view('pos.checkout', [
            'cart' => $cart,
            'customers' => $customers,
            'staffMembers' => $staffMembers,
            'takenByStaffId' => $draft['taken_by_staff_id'] ?? null,
            'customerId' => $customerId,
            'paymentMethod' => $paymentMethod,
            'discount' => $discount,
            'netTotal' => $netTotal,
            'amountPaid' => $amountPaid,
            'notes' => $draft['notes'] ?? '',
            'includePreviousDebt' => (bool) ($draft['include_previous_debt'] ?? false),
        ]);
    }

    public function checkout(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'taken_by_staff_id' => ['nullable', Rule::exists('staff', 'id')->where('is_active', true)],
            'payment_method' => ['required', Rule::in([
                Transaction::PAYMENT_CASH,
                Transaction::PAYMENT_QRIS,
                Transaction::PAYMENT_OTHER,
            ])],
            'amount_paid' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'notes' => ['nullable', 'string', 'max:500'],
            'include_previous_debt' => ['nullable', 'boolean'],
        ]);

        $cart = $this->cartService->getOrCreateActiveCart($request->user());

        $transaction = $this->transactionService->checkout(
            $request->user(),
            $cart,
            $validated['payment_method'],
            isset($validated['amount_paid']) ? (float) $validated['amount_paid'] : null,
            (float) ($validated['discount_amount'] ?? 0),
            $validated['notes'] ?? null,
            ! empty($validated['customer_id']) ? (int) $validated['customer_id'] : null,
            'full',
            $request->boolean('include_previous_debt'),
            ! empty($validated['taken_by_staff_id']) ? (int) $validated['taken_by_staff_id'] : null
        );

        $request->session()->forget('pos_checkout_draft');

        return redirect()
            ->route('transactions.show', $transaction)
            ->with('checkout_success', 'Transaksi berhasil diselesaikan!');
    }
}
