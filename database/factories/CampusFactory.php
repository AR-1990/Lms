<?php

namespace Database\Factories;

use App\Models\Campus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campus>
 */
class CampusFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->city().' Campus',
            'code' => strtoupper(fake()->unique()->bothify('??-##')),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'phone' => fake()->numerify('03#########'),
            'email' => fake()->unique()->safeEmail(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
