<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeLoan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeLoan>
 */
class EmployeeLoanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => fn (): int => Employee::query()->value('id'),
            'amount' => 3000000,
            'instalments' => 3,
            'instalment_amount' => 1000000,
            'start_month' => now()->startOfMonth()->addMonth()->toDateString(),
            'status' => 'pending',
        ];
    }
}
