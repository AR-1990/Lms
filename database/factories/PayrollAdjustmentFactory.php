<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Models\SalaryComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollAdjustment>
 */
class PayrollAdjustmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'month' => now()->startOfMonth()->toDateString(),
            'salary_component_id' => fn (): ?int => SalaryComponent::where('code', 'BONUS')->value('id'),
            'amount' => fake()->numberBetween(1000, 20000) * 100,
        ];
    }
}
