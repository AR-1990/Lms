<?php

namespace Database\Factories;

use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()).' Leave',
            'code' => strtoupper(fake()->unique()->lexify('??')),
            'days_per_year' => 10,
            'is_paid' => true,
            'is_active' => true,
        ];
    }
}
