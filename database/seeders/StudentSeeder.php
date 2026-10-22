<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Lookup;
use App\Models\NumberSequence;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    /**
     * Demo students: the seeded student and parent logins get real records, plus a few
     * students in Grade 1 and Grade 5 so lists and fee screens have data.
     */
    public function run(): void
    {
        $session = AcademicSession::where('is_current', true)->first();

        if ($session === null || Student::exists()) {
            return;
        }

        $genders = Lookup::where('type', 'gender')->pluck('id', 'name');
        $relations = Lookup::where('type', 'guardian_relation')->pluck('id', 'name');
        $father = $relations['Father'] ?? $relations->first();

        $parentUser = User::where('email', 'parent@lms.test')->first();
        $parent = Guardian::create([
            'user_id' => $parentUser?->id,
            'name' => $parentUser?->name ?? 'Farhan Malik',
            'phone' => $parentUser?->phone ?? '03001234567',
            'cnic' => '35202-1111111-1',
            'city' => 'Lahore',
        ]);

        $grade5 = Section::whereHas('schoolClass', fn ($query) => $query->where('name', 'Grade 5'))->where('academic_session_id', $session->id)->where('name', 'A')->first();
        $grade1 = Section::whereHas('schoolClass', fn ($query) => $query->where('name', 'Grade 1'))->where('academic_session_id', $session->id)->where('name', 'A')->first();

        $studentUser = User::where('email', 'student@lms.test')->first();
        $this->enrol(
            Student::factory()->create(['user_id' => $studentUser?->id, 'admission_no' => $this->nextAdmissionNo(), 'first_name' => 'John', 'last_name' => 'Doe', 'gender_id' => $genders['Male'] ?? $genders->first()]),
            $grade5,
            $parent,
            $father,
            1,
        );

        foreach ([[$grade5, 2], [$grade5, 3], [$grade1, 1], [$grade1, 2]] as [$section, $roll]) {
            $child = Student::factory()->create(['admission_no' => $this->nextAdmissionNo(), 'gender_id' => $genders->random()]);
            $guardian = Guardian::create(['name' => fake()->name('male'), 'phone' => fake()->numerify('03#########'), 'city' => 'Lahore']);
            $this->enrol($child, $section, $guardian, $father, $roll);
        }
    }

    private function enrol(Student $student, ?Section $section, Guardian $guardian, int $relationId, int $roll): void
    {
        if ($section === null) {
            return;
        }

        Enrollment::create([
            'student_id' => $student->id,
            'academic_session_id' => $section->academic_session_id,
            'class_id' => $section->class_id,
            'section_id' => $section->id,
            'roll_no' => (string) $roll,
            'enrolled_on' => $student->admission_date,
            'status' => 'active',
        ]);

        $student->guardians()->attach($guardian->id, ['relation_id' => $relationId, 'is_primary_contact' => true, 'is_fee_payer' => true]);
    }

    /**
     * Uses the same counter as the app so seeded and real admission numbers never clash.
     */
    private function nextAdmissionNo(): string
    {
        $sequence = NumberSequence::where('type', 'admission_no')->first();
        $number = $sequence->next_number;
        $sequence->forceFill(['next_number' => $number + 1])->saveQuietly();

        return $sequence->format($number);
    }
}
