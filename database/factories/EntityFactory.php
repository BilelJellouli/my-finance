<?php

namespace Database\Factories;

use App\Enums\EntityColor;
use App\Enums\EntityType;
use App\Models\Entity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Entity>
 */
class EntityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->company(),
            'type' => EntityType::Llc,
            'color' => fake()->randomElement(EntityColor::cases()),
        ];
    }

    public function personal(): static
    {
        return $this->state(fn () => [
            'name' => 'Personal',
            'type' => EntityType::Personal,
            'color' => EntityColor::Green,
        ]);
    }

    public function llc(): static
    {
        return $this->state(fn () => [
            'type' => EntityType::Llc,
        ]);
    }
}
