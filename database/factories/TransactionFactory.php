<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = fake()->randomElement([25000, 50000, 125000, 250000]);

        return [
            'transaction_code' => 'TRX-' . fake()->unique()->numerify('######'),
            'user_id' => User::factory()->cashier(),
            'cart_id' => null,
            'subtotal' => $subtotal,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => $subtotal,
            'amount_paid' => $subtotal,
            'change_amount' => 0,
            'payment_method' => fake()->randomElement(['cash', 'qris', 'other']),
            'transaction_status' => Transaction::STATUS_COMPLETED,
            'transaction_date' => now(),
            'notes' => null,
        ];
    }
}
