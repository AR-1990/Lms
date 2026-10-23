<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => fn (): int => Employee::query()->value('id'),
            'leave_type_id' => fn (): int => LeaveType::query()->value('id'),
            'from_date' => now()->addDays(3)->toDateString(),
            'to_date' => now()->addDays(4)->toDateString(),
            'days' => 2,
            'reason' => fake()->sentence(),
            'status' => 'pending',
        ];
    }
}
