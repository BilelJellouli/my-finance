<?php

namespace Database\Factories;

use App\Models\PlannedTransaction;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'planned_transaction_id' => PlannedTransaction::factory(),
            'amount' => fake()->randomFloat(2, 10, 500),
            'occurred_on' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'note' => null,
        ];
    }
}
