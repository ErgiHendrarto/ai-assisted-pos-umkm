<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionService $transactionService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $search = trim((string) $request->query('search', ''));
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $paymentMethod = $request->query('payment_method', 'all');
        $status = $request->query('status', 'all');
        $cashierId = $request->query('user_id');
        $takenBy = $request->query('taken_by', 'all');

        $transactions = Transaction::query()
            ->with(['user', 'customer', 'takenByStaff'])
            ->withCount('details')
            ->when(! $user->isAdministrator(), fn ($q) => $q->where('user_id', $user->id))
            ->when($user->isAdministrator() && $cashierId, fn ($q) => $q->where('user_id', $cashierId))
            ->when($takenBy === 'unassigned', fn ($q) => $q->whereNull('taken_by_staff_id'))
            ->when($takenBy !== 'all' && $takenBy !== 'unassigned' && $takenBy !== '', fn ($q) => $q->where('taken_by_staff_id', $takenBy))
            ->when($search !== '', fn ($q) => $q->where('transaction_code', 'like', "%{$search}%"))
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->when($paymentMethod !== 'all', fn ($q) => $q->where('payment_method', $paymentMethod))
            ->when($status !== 'all', fn ($q) => $q->where('transaction_status', $status))
            ->latest('created_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $cashiers = $user->isAdministrator()
            ? User::query()->orderBy('name')->get(['id', 'name'])
            : collect();

        $staffList = Staff::query()->orderBy('name')->get(['id', 'staff_code', 'name', 'is_active']);

        return view('transactions.index', [
            'transactions' => $transactions,
            'cashiers' => $cashiers,
            'staffList' => $staffList,
            'search' => $search,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'paymentMethod' => $paymentMethod,
            'status' => $status,
            'cashierId' => $cashierId,
            'takenBy' => $takenBy,
        ]);
    }

    public function show(Request $request, Transaction $transaction): View
    {
        $this->authorizeView($request->user(), $transaction);

        $transaction->load(['user', 'customer', 'takenByStaff', 'details.product', 'stockMovements', 'debtPayments.user', 'debtPayments.settledByTransaction']);

        return view('transactions.show', [
            'transaction' => $transaction,
        ]);
    }

    public function receipt(Request $request, Transaction $transaction): View
    {
        $this->authorizeView($request->user(), $transaction);

        $transaction->load(['user', 'customer', 'takenByStaff', 'details']);

        return view('transactions.receipt', [
            'transaction' => $transaction,
        ]);
    }

    public function cancel(Request $request, Transaction $transaction): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $this->transactionService->cancel($transaction, $request->user(), $validated['reason']);

        return redirect()
            ->route('transactions.show', $transaction)
            ->with('status', 'Transaksi dibatalkan dan stok produk telah dikembalikan.');
    }

    private function authorizeView(User $user, Transaction $transaction): void
    {
        if (! $user->isAdministrator() && $transaction->user_id !== $user->id) {
            abort(403, 'Anda hanya dapat melihat riwayat transaksi Anda sendiri.');
        }
    }
}
