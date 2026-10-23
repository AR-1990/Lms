<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\ItemCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sku' => 'ITM-'.fake()->unique()->numerify('#####'),
            'item_category_id' => ItemCategory::factory(),
            'name' => fake()->randomElement(['Whiteboard Marker (Blue)', 'A4 Paper Ream', 'Chalk Box', 'Register 200 pages']),
            'reorder_level' => 10,
            'reorder_quantity' => 50,
            'standard_cost' => 15000,
            'is_active' => true,
        ];
    }
}
