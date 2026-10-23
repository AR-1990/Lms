<?php

namespace Database\Factories;

use App\Models\AnnouncementCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnnouncementCategory>
 */
class AnnouncementCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()),
            'color' => fake()->hexColor(),
            'icon' => 'inbox',
            'sort_order' => fake()->numberBetween(1, 50),
            'is_active' => true,
        ];
    }
}
