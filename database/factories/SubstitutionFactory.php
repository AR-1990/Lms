<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Substitution;
use App\Models\TimetableSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Substitution>
 */
class SubstitutionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => now()->toDateString(),
            'timetable_slot_id' => fn () => TimetableSlot::value('id'),
            'absent_teacher_id' => Employee::factory(),
            'substitute_teacher_id' => Employee::factory(),
        ];
    }
}
