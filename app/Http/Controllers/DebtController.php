<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DebtPayment;
use App\Models\Transaction;
use App\Services\DebtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DebtController extends Controller
{
    public function __construct(
        private readonly DebtService $debtService
    ) {}

    public function index(Request $request): View
    {
        $customerId = $request->query('customer_id');

        $unpaidTransactions = Transaction::query()
            ->with(['customer', 'user'])
            ->where('transaction_status', '!=', Transaction::STATUS_CANCELLED)
            ->where('outstanding_balance', '>', 0)
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->latest('created_at')
            ->paginate(15, ['*'], 'debt_page')
            ->withQueryString();

        $totalOutstanding = (float) Transaction::query()
            ->where('transaction_status', '!=', Transaction::STATUS_CANCELLED)
            ->where('outstanding_balance', '>', 0)
            ->sum('outstanding_balance');

        $totalRepaid = (float) DebtPayment::query()
            ->where('entry_type', DebtPayment::TYPE_REPAYMENT)
            ->sum('amount');

        $recentPayments = DebtPayment::query()
            ->with(['customer', 'transaction', 'user'])
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->latest('payment_date')
            ->latest('id')
            ->take(15)
            ->get();

        $customers = Customer::query()->orderBy('name')->get(['id', 'name']);

        return view('debts.index', [
            'unpaidTransactions' => $unpaidTransactions,
            'totalOutstanding' => $totalOutstanding,
            'totalRepaid' => $totalRepaid,
            'recentPayments' => $recentPayments,
            'customers' => $customers,
            'customerId' => $customerId,
        ]);
    }

    public function storePayment(Request $request, Transaction $transaction): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:999999999'],
            'payment_method' => ['required', Rule::in([
                Transaction::PAYMENT_CASH,
                Transaction::PAYMENT_QRIS,
                Transaction::PAYMENT_OTHER,
            ])],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->debtService->recordPayment(
            $transaction,
            $request->user(),
            (float) $validated['amount'],
            $validated['payment_method'],
            $validated['note'] ?? null
        );

        return back()->with('status', 'Pembayaran piutang berhasil dicatat.');
    }

    public function receipt(DebtPayment $debtPayment): View
    {
        $debtPayment->load(['customer', 'transaction', 'user']);

        return view('debts.receipt', [
            'payment' => $debtPayment,
        ]);
    }

    public function removeCustomerDebt(Request $request, Customer $customer): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', Rule::in(array_keys(DebtPayment::REASON_LABELS))],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $this->debtService->removeCustomerDebt(
            $customer,
            $request->user(),
            $validated['reason'],
            $validated['notes'] ?? null
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Piutang pelanggan berhasil dihapus/diselesaikan.',
                'customer_id' => $customer->id,
                'outstanding_debt' => 0.0,
                'removed_amount' => $result['removed_amount'],
                'entry_type' => $result['entry_type'],
            ]);
        }

        return back()->with('status', 'Piutang pelanggan berhasil dihapus/diselesaikan.');
    }
}
