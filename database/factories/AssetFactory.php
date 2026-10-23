<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_tag' => 'AST-'.fake()->unique()->numerify('#####'),
            'asset_category_id' => AssetCategory::factory(),
            'name' => fake()->randomElement(['Dell Latitude 5440', 'Epson Projector', 'Office Chair', 'HP LaserJet']),
            'serial_no' => strtoupper(fake()->bothify('SN-####-????')),
            'purchase_date' => now()->subMonths(6)->toDateString(),
            'purchase_cost' => 15000000,
            'salvage_value' => 1500000,
            'current_book_value' => 15000000,
            'depreciation_start_date' => now()->subMonths(6)->startOfMonth()->toDateString(),
            'status' => 'in_store',
        ];
    }
}
