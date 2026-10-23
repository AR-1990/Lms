<?php

namespace Database\Factories;

use App\Models\CounsellingNote;
use App\Models\Employee;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CounsellingNote>
 */
class CounsellingNoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'session_date' => now()->toDateString(),
            'counsellor_id' => Employee::factory(),
            'notes' => fake()->paragraph(),
            'parents_met' => false,
            'is_confidential' => true,
        ];
    }
}
