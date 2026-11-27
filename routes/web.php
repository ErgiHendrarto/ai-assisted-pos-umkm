<?php

use App\Http\Controllers\AiOrderController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DebtController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Administrator + Cashier shared POS & operational routes
    Route::middleware('role:administrator,cashier')->group(function (): void {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // POS & Cart
        Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
        Route::get('/pos/products/search', [PosController::class, 'searchProducts'])->name('pos.products.search');
        Route::get('/pos/products/{product}/alternatives', [PosController::class, 'alternatives'])->name('pos.products.alternatives');
        Route::post('/pos/cart/add', [PosController::class, 'addToCart'])->name('pos.cart.add');
        Route::patch('/pos/cart/items/{cartItem}', [PosController::class, 'updateCartItem'])->name('pos.cart.update');
        Route::patch('/pos/cart/items/{cartItem}/price', [PosController::class, 'updateCartItemPrice'])->name('pos.cart.update-price');
        Route::delete('/pos/cart/items/{cartItem}', [PosController::class, 'removeCartItem'])->name('pos.cart.remove');
        Route::delete('/pos/cart/clear', [PosController::class, 'clearCart'])->name('pos.cart.clear');
        Route::post('/pos/hold', [PosController::class, 'holdCart'])->name('pos.hold');
        Route::get('/pos/pending', [PosController::class, 'pendingTransactions'])->name('pos.pending.index');
        Route::post('/pos/pending/{cart}/resume', [PosController::class, 'resumePending'])->name('pos.pending.resume');
        Route::delete('/pos/pending/{cart}', [PosController::class, 'cancelPending'])->name('pos.pending.cancel');
        Route::post('/pos/checkout/review', [PosController::class, 'prepareCheckout'])->name('pos.checkout.prepare');
        Route::get('/pos/checkout/review', [PosController::class, 'reviewCheckout'])->name('pos.checkout.review');
        Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');

        // AI-Assisted Order Input (Voice, Image/OCR, Review & Confirmation)
        Route::get('/pos/ai/voice', [AiOrderController::class, 'voiceForm'])->name('pos.ai.voice');
        Route::post('/pos/ai/voice', [AiOrderController::class, 'processVoice'])->name('pos.ai.voice.process');
        Route::get('/pos/ai/image', [AiOrderController::class, 'imageForm'])->name('pos.ai.image');
        Route::post('/pos/ai/image', [AiOrderController::class, 'processImage'])->name('pos.ai.image.process');
        Route::post('/pos/ai/{orderInput}/extract', [AiOrderController::class, 'extractFromText'])->name('pos.ai.extract');
        Route::get('/pos/ai/{orderInput}/review', [AiOrderController::class, 'review'])->name('pos.ai.review');
        Route::post('/pos/ai/{orderInput}/confirm', [AiOrderController::class, 'confirm'])->name('pos.ai.confirm');

        // Transactions & Receipts
        Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
        Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
        Route::get('/transactions/{transaction}/receipt', [TransactionController::class, 'receipt'])->name('transactions.receipt');

        // Quick Customer Add & Debt Repayments from POS / Cashier
        Route::post('/pos/customers/quick', [CustomerController::class, 'quickStore'])->name('pos.customers.quick');
        Route::get('/pos/customers/{customer}/debt', [PosController::class, 'customerDebt'])->name('pos.customers.debt');
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::get('/debts', [DebtController::class, 'index'])->name('debts.index');
        Route::post('/transactions/{transaction}/debt-payments', [DebtController::class, 'storePayment'])->name('debts.pay');
        Route::get('/debt-payments/{debtPayment}/receipt', [DebtController::class, 'receipt'])->name('debts.receipt');

        // Settings
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    });

    // Administrator-only management routes
    Route::middleware('role:administrator')->group(function (): void {
        Route::get('/dashboard/analytics-data', [DashboardController::class, 'analyticsData'])->name('dashboard.analytics-data');
        Route::post('/dashboard/weekly-insight/regenerate', [DashboardController::class, 'regenerateInsight'])->name('dashboard.insight.regenerate');
        Route::put('/settings/ai', [SettingsController::class, 'updateAi'])->name('settings.ai.update');
        Route::post('/units', [UnitController::class, 'store'])->name('units.store');

        // Customers (Admin write/delete)
        Route::post('/pos/customers/{customer}/remove-debt', [DebtController::class, 'removeCustomerDebt'])->name('pos.customers.remove-debt');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');

        // Categories
        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

        // Products & Stock Adjustments
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
        Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
        Route::post('/products/{product}/adjust-stock', [ProductController::class, 'adjustStock'])->name('products.adjust-stock');

        // Inventory Movement History
        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');

        // Transaction Cancellation
        Route::post('/transactions/{transaction}/cancel', [TransactionController::class, 'cancel'])->name('transactions.cancel');

        // Sales Reports & AI History
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/print', [ReportController::class, 'print'])->name('reports.print');
        Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
        Route::get('/ai-history', [AiOrderController::class, 'history'])->name('ai-history.index');

        // Users & Staff
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
        Route::put('/staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
        Route::patch('/staff/{staff}/toggle-active', [StaffController::class, 'toggleActive'])->name('staff.toggle-active');
    });
});
