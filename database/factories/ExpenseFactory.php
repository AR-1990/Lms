<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_no' => 'EXP-'.fake()->unique()->numerify('#####'),
            'expense_category_id' => ExpenseCategory::factory(),
            'expense_date' => now()->toDateString(),
            'amount' => fake()->numberBetween(1000, 50000) * 100,
            'tax_amount' => 0,
            'description' => fake()->sentence(),
            'status' => 'draft',
        ];
    }
}
