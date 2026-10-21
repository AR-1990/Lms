<?php

namespace Database\Factories;

use App\Models\Lookup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lookup>
 */
class LookupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'document_type',
            'name' => fake()->unique()->words(2, true),
            'code' => fake()->unique()->bothify('??##'),
            'description' => fake()->optional()->sentence(),
            'sort_order' => fake()->numberBetween(0, 50),
            'is_active' => true,
            'is_system' => false,
        ];
    }

    /**
     * A built-in option that cannot be deleted.
     */
    public function system(): static
    {
        return $this->state(fn (): array => ['is_system' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
