<?php

namespace Database\Factories;

use App\Models\SalaryComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalaryComponent>
 */
class SalaryComponentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()).' Allowance',
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'type' => 'earning',
            'calculation' => 'fixed',
            'default_value' => 500000,
            'is_active' => true,
        ];
    }
}
