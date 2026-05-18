<?php

namespace Database\Factories;

use App\Enums\RecurringFrequency;
use App\Models\RecurringPlan;
use App\Models\RecurringPlanPhase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringPlanPhase>
 */
class RecurringPlanPhaseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recurring_plan_id' => RecurringPlan::factory(),
            'amount' => fake()->randomFloat(2, 50, 5000),
            'frequency' => RecurringFrequency::MONTHLY,
            'interval_step' => 1,
            'anchor_day' => 1,
            'starts_on' => now()->startOfMonth()->toDateString(),
            'ends_on' => null,
            'occurrence_count' => null,
            'reason' => null,
        ];
    }

    public function monthly(): static
    {
        return $this->state(fn () => [
            'frequency' => RecurringFrequency::MONTHLY,
            'interval_step' => 1,
        ]);
    }

    public function weekly(): static
    {
        return $this->state(fn () => [
            'frequency' => RecurringFrequency::WEEKLY,
            'interval_step' => 1,
            'anchor_day' => 1,
        ]);
    }

    public function closed(string $endsOn): static
    {
        return $this->state(fn () => ['ends_on' => $endsOn]);
    }
}
