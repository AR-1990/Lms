<?php

namespace Database\Factories;

use App\Models\TransportRoute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransportRoute>
 */
class TransportRouteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Route '.fake()->unique()->numberBetween(1, 99),
            'monthly_fee' => 350000,
            'is_active' => true,
        ];
    }
}
