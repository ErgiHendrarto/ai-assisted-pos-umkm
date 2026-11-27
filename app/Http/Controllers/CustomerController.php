<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $customers = Customer::query()
            ->withCount('transactions')
            ->withSum([
                'transactions as outstanding_debt_sum' => fn ($q) => $q
                    ->where('transaction_status', '!=', Transaction::STATUS_CANCELLED)
                    ->where('outstanding_balance', '>', 0),
            ], 'outstanding_balance')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('customer_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        Customer::create([
            'name' => trim($validated['name']),
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('customers.index')
            ->with('status', 'Pelanggan berhasil ditambahkan.');
    }

    public function quickStore(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $customer = Customer::create([
            'name' => trim($validated['name']),
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'is_active' => true,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['customer' => $customer], 201);
        }

        return back()->with('status', "Pelanggan '{$customer->name}' berhasil ditambahkan.");
    }

    public function show(Customer $customer): View
    {
        $transactions = $customer->transactions()
            ->with('user')
            ->latest('created_at')
            ->latest('id')
            ->paginate(10, ['*'], 'trx_page');

        $debtPayments = $customer->debtPayments()
            ->with(['transaction', 'settledByTransaction', 'user'])
            ->latest('payment_date')
            ->latest('id')
            ->paginate(10, ['*'], 'pay_page');

        return view('customers.show', [
            'customer' => $customer,
            'totalOutstandingDebt' => $customer->totalOutstandingDebt(),
            'transactions' => $transactions,
            'debtPayments' => $debtPayments,
        ]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $customer->update([
            'name' => trim($validated['name']),
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('customers.index')
            ->with('status', 'Data pelanggan berhasil diperbarui.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        if ($customer->totalOutstandingDebt() > 0) {
            throw ValidationException::withMessages([
                'customer' => 'Pelanggan masih memiliki piutang/hutang yang belum lunas.',
            ]);
        }

        $customer->delete();

        return redirect()
            ->route('customers.index')
            ->with('status', 'Pelanggan berhasil diarsipkan.');
    }
}
