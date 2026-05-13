<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\Account;
use App\Models\Entity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_id' => Entity::factory(),
            'name' => fake()->randomElement(['Main', 'Checking', 'Savings', 'Operating']),
            'currency' => fake()->randomElement(Currency::cases()),
            'amount' => fake()->randomFloat(2, 0, 10000),
            'is_main' => false,
        ];
    }

    public function main(): static
    {
        return $this->state(fn () => [
            'name' => 'Main',
            'is_main' => true,
        ]);
    }
}
