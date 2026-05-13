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
            'type' => EntityType::LLC,
            'color' => fake()->randomElement(EntityColor::cases()),
        ];
    }

    public function personal(): static
    {
        return $this->state(fn () => [
            'name' => 'Personal',
            'type' => EntityType::PERSONAL,
            'color' => EntityColor::GREEN,
        ]);
    }

    public function llc(): static
    {
        return $this->state(fn () => [
            'type' => EntityType::LLC,
        ]);
    }
}
