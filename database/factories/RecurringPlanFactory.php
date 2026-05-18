<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Enums\PlannedTransactionDirection;
use App\Enums\RecurringPlanStatus;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\RecurringPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringPlan>
 */
class RecurringPlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_entity_id' => Entity::factory(),
            'counterparty_id' => Counterparty::factory(),
            'account_id' => null,
            'direction' => PlannedTransactionDirection::OUTGOING,
            'currency' => Currency::EUR,
            'label' => fake()->randomElement(['Apartment rent', 'Gym membership', 'Car loan', 'Netflix']),
            'purpose' => 'Rent',
            'is_mandatory' => true,
            'status' => RecurringPlanStatus::ACTIVE,
            'starts_on' => now()->startOfMonth()->toDateString(),
            'ends_on' => null,
            'materialized_until' => null,
            'note' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => RecurringPlanStatus::ACTIVE]);
    }

    public function paused(): static
    {
        return $this->state(fn () => ['status' => RecurringPlanStatus::PAUSED]);
    }

    public function ended(): static
    {
        return $this->state(fn () => [
            'status' => RecurringPlanStatus::ENDED,
            'ends_on' => now()->toDateString(),
        ]);
    }
}
