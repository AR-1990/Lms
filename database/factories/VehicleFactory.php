<?php

namespace Database\Factories;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'registration_no' => strtoupper(fake()->unique()->bothify('LE?-####')),
            'make_model' => 'Toyota Coaster',
            'capacity' => 30,
            'insurance_expiry' => now()->addMonths(6)->toDateString(),
            'fitness_expiry' => now()->addMonths(4)->toDateString(),
            'is_active' => true,
        ];
    }
}
