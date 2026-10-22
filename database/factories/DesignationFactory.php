<?php

namespace Database\Factories;

use App\Models\Designation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Designation>
 */
class DesignationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->jobTitle()),
            'is_teaching' => false,
            'is_active' => true,
        ];
    }

    public function teaching(): static
    {
        return $this->state(fn (): array => ['is_teaching' => true]);
    }
}
