<?php

namespace Database\Factories;

use App\Enums\CounterpartyKind;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Counterparty>
 */
class CounterpartyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->company(),
            'kind' => CounterpartyKind::EXTERNAL,
            'entity_id' => null,
        ];
    }

    public function external(): static
    {
        return $this->state(fn () => [
            'kind' => CounterpartyKind::EXTERNAL,
            'entity_id' => null,
        ]);
    }

    public function internal(Entity $entity): static
    {
        return $this->state(fn () => [
            'user_id' => $entity->user_id,
            'name' => $entity->name,
            'kind' => CounterpartyKind::INTERNAL,
            'entity_id' => $entity->id,
        ]);
    }
}
