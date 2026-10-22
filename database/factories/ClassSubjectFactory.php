<?php

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassSubject>
 */
class ClassSubjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_session_id' => AcademicSession::factory(),
            'class_id' => SchoolClass::factory(),
            'subject_id' => Subject::factory(),
            'is_optional' => false,
            'periods_per_week' => 5,
            'theory_max_marks' => 100,
            'pass_marks' => 33,
            'sort_order' => 0,
        ];
    }
}
