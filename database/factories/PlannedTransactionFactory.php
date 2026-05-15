<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Enums\PlannedTransactionDirection;
use App\Enums\PlannedTransactionStatus;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\PlannedTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlannedTransaction>
 */
class PlannedTransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_entity_id' => Entity::factory(),
            'counterparty_id' => Counterparty::factory(),
            'direction' => fake()->randomElement(PlannedTransactionDirection::cases()),
            'amount' => fake()->randomFloat(2, 10, 5000),
            'currency' => Currency::EUR,
            'due_date' => fake()->dateTimeBetween('now', '+3 months')->format('Y-m-d'),
            'purpose' => fake()->randomElement(['Rent', 'Salary', 'Tax', 'Invoice', 'Dividend', 'Loan']),
            'status' => PlannedTransactionStatus::PLANNED,
            'is_mandatory' => true,
            'note' => null,
            'transfer_group_id' => null,
        ];
    }

    public function incoming(): static
    {
        return $this->state(fn () => [
            'direction' => PlannedTransactionDirection::INCOMING,
        ]);
    }

    public function outgoing(): static
    {
        return $this->state(fn () => [
            'direction' => PlannedTransactionDirection::OUTGOING,
        ]);
    }

    public function settled(): static
    {
        return $this->state(fn () => [
            'status' => PlannedTransactionStatus::SETTLED,
        ]);
    }

    public function flexible(): static
    {
        return $this->state(fn () => [
            'is_mandatory' => false,
        ]);
    }
}
