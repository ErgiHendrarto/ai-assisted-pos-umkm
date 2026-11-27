<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\DebtPayment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DebtService
{
    /**
     * Record a full or partial debt repayment against an unpaid/partially paid transaction.
     */
    public function recordPayment(
        Transaction $transaction,
        User $user,
        float $amount,
        string $paymentMethod = Transaction::PAYMENT_CASH,
        ?string $note = null
    ): DebtPayment {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Jumlah pembayaran piutang harus lebih dari 0.',
            ]);
        }

        return DB::transaction(function () use ($transaction, $user, $amount, $paymentMethod, $note): DebtPayment {
            /** @var Transaction $locked */
            $locked = Transaction::query()
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->transaction_status === Transaction::STATUS_CANCELLED) {
                throw ValidationException::withMessages([
                    'amount' => 'Transaksi yang sudah dibatalkan tidak dapat menerima pembayaran piutang.',
                ]);
            }

            if (! $locked->customer_id) {
                throw ValidationException::withMessages([
                    'amount' => 'Transaksi ini tidak memiliki data pelanggan.',
                ]);
            }

            $outstanding = round((float) $locked->outstanding_balance, 2);
            if ($outstanding <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Transaksi ini sudah lunas.',
                ]);
            }

            if (round($amount, 2) > $outstanding) {
                throw ValidationException::withMessages([
                    'amount' => 'Jumlah pembayaran melebihi sisa piutang (' . number_format($outstanding, 0, ',', '.') . ').',
                ]);
            }

            $payment = DebtPayment::create([
                'transaction_id' => $locked->id,
                'customer_id' => $locked->customer_id,
                'user_id' => $user->id,
                'amount' => round($amount, 2),
                'payment_method' => $paymentMethod,
                'entry_type' => DebtPayment::TYPE_REPAYMENT,
                'payment_date' => now(),
                'note' => $note,
            ]);

            $newBalance = round(max(0.0, $outstanding - $amount), 2);
            $newPaid = round((float) $locked->amount_paid + $amount, 2);
            $newStatus = $newBalance <= 0
                ? Transaction::PAYMENT_STATUS_PAID
                : Transaction::PAYMENT_STATUS_PARTIAL;

            $locked->update([
                'amount_paid' => $newPaid,
                'outstanding_balance' => $newBalance,
                'payment_status' => $newStatus,
            ]);

            return $payment;
        });
    }

    /**
     * Remove / write off all outstanding debt for a customer while preserving financial history.
     *
     * @return array{removed_amount: float, records_count: int, entry_type: string}
     */
    public function removeCustomerDebt(
        Customer $customer,
        User $user,
        string $reason = 'write_off',
        ?string $notes = null
    ): array {
        return DB::transaction(function () use ($customer, $user, $reason, $notes): array {
            $unpaidDebts = Transaction::query()
                ->where('customer_id', $customer->id)
                ->where('transaction_status', '!=', Transaction::STATUS_CANCELLED)
                ->where('outstanding_balance', '>', 0)
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $totalOutstanding = round((float) $unpaidDebts->sum('outstanding_balance'), 2);

            if ($unpaidDebts->isEmpty() || $totalOutstanding <= 0) {
                throw ValidationException::withMessages([
                    'debt' => 'Pelanggan tidak memiliki piutang yang dapat dihapus.',
                ]);
            }

            $entryType = $reason === 'already_paid_outside'
                ? DebtPayment::TYPE_EXTERNAL_SETTLEMENT
                : DebtPayment::TYPE_WRITE_OFF;

            $paymentMethod = $reason === 'already_paid_outside' ? 'external' : 'write_off';
            $reasonLabel = DebtPayment::REASON_LABELS[$reason] ?? $reason;
            $noteText = $notes && trim($notes) !== ''
                ? "{$reasonLabel}: " . trim($notes)
                : $reasonLabel;

            $recordsCount = 0;

            foreach ($unpaidDebts as $debtTrx) {
                $balance = round((float) $debtTrx->outstanding_balance, 2);
                if ($balance <= 0) {
                    continue;
                }

                DebtPayment::create([
                    'transaction_id' => $debtTrx->id,
                    'customer_id' => $customer->id,
                    'user_id' => $user->id,
                    'amount' => $balance,
                    'payment_method' => $paymentMethod,
                    'entry_type' => $entryType,
                    'reason' => $reason,
                    'payment_date' => now(),
                    'note' => $noteText,
                ]);

                // Keep original transaction's total_amount and amount_paid intact; only clear outstanding_balance
                $debtTrx->update([
                    'outstanding_balance' => 0,
                    'payment_status' => Transaction::PAYMENT_STATUS_PAID,
                ]);

                $recordsCount++;
            }

            return [
                'removed_amount' => $totalOutstanding,
                'records_count' => $recordsCount,
                'entry_type' => $entryType,
            ];
        });
    }
}
