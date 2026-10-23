<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseCategory>
 */
class ExpenseCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'expense_account_id' => Account::where('code', '5900')->value('id'),
            'is_active' => true,
        ];
    }
}
