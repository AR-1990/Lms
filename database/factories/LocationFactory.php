<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Room '.fake()->unique()->numberBetween(1, 500),
            'building' => 'Main Block',
            'is_store' => false,
            'is_active' => true,
        ];
    }
}
