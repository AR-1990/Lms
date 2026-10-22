<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\ClassSubject;
use App\Models\Lookup;
use App\Models\Period;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class AcademicSeeder extends Seeder
{
    /**
     * Grade ladder in promotion order: [name, code, wing].
     *
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    private const CLASSES = [
        ['Playgroup', 'PG', 'Early Years'],
        ['Nursery', 'NUR', 'Early Years'],
        ['Prep', 'PREP', 'Early Years'],
        ['Grade 1', 'G1', 'Junior School'],
        ['Grade 2', 'G2', 'Junior School'],
        ['Grade 3', 'G3', 'Junior School'],
        ['Grade 4', 'G4', 'Junior School'],
        ['Grade 5', 'G5', 'Junior School'],
        ['Grade 6', 'G6', 'Middle School'],
        ['Grade 7', 'G7', 'Middle School'],
        ['Grade 8', 'G8', 'Middle School'],
        ['Grade 9', 'G9', 'Senior School'],
        ['Grade 10', 'G10', 'Senior School'],
    ];

    /**
     * [name, code, type, has practical].
     *
     * @var array<int, array{0: string, 1: string, 2: string, 3: bool}>
     */
    private const SUBJECTS = [
        ['English', 'ENG', 'Core', false],
        ['Urdu', 'URD', 'Core', false],
        ['Mathematics', 'MATH', 'Core', false],
        ['General Science', 'SCI', 'Core', false],
        ['Islamiat', 'ISL', 'Core', false],
        ['Social Studies', 'SST', 'Core', false],
        ['Computer Science', 'CS', 'Core', true],
        ['Physics', 'PHY', 'Elective', true],
        ['Chemistry', 'CHEM', 'Elective', true],
        ['Biology', 'BIO', 'Elective', true],
        ['Pakistan Studies', 'PST', 'Core', false],
        ['Art & Craft', 'ART', 'Co-curricular', false],
        ['Physical Education', 'PE', 'Co-curricular', false],
    ];

    /**
     * Subjects taught per wing (by subject code).
     *
     * @var array<string, array<int, string>>
     */
    private const CURRICULUM = [
        'Early Years' => ['ENG', 'URD', 'MATH', 'ART'],
        'Junior School' => ['ENG', 'URD', 'MATH', 'SCI', 'ISL', 'SST', 'CS', 'ART', 'PE'],
        'Middle School' => ['ENG', 'URD', 'MATH', 'SCI', 'ISL', 'SST', 'CS', 'PE'],
        'Senior School' => ['ENG', 'URD', 'MATH', 'ISL', 'PST', 'PHY', 'CHEM', 'BIO', 'CS'],
    ];

    /**
     * Default bell schedule: [name, starts, ends, is break].
     *
     * @var array<int, array{0: string, 1: string, 2: string, 3: bool}>
     */
    private const PERIODS = [
        ['Assembly', '07:50', '08:00', true],
        ['Period 1', '08:00', '08:40', false],
        ['Period 2', '08:40', '09:20', false],
        ['Period 3', '09:20', '10:00', false],
        ['Period 4', '10:00', '10:40', false],
        ['Break', '10:40', '11:00', true],
        ['Period 5', '11:00', '11:40', false],
        ['Period 6', '11:40', '12:20', false],
        ['Period 7', '12:20', '13:00', false],
        ['Period 8', '13:00', '13:40', false],
    ];

    /**
     * Master academic setup plus one current session with demo sections and curriculum.
     * Safe to run again: existing rows are matched, not duplicated.
     */
    public function run(): void
    {
        $wings = Lookup::where('type', 'wing')->pluck('id', 'name');
        $subjectTypes = Lookup::where('type', 'subject_type')->pluck('id', 'name');

        $session = AcademicSession::firstOrCreate(
            ['name' => '2026-27'],
            ['starts_on' => '2026-08-01', 'ends_on' => '2027-07-31', 'is_current' => true, 'admissions_open' => true],
        );

        $classes = collect(self::CLASSES)->values()->map(fn (array $class, int $index): SchoolClass => SchoolClass::firstOrCreate(
            ['name' => $class[0]],
            ['code' => $class[1], 'level_order' => ($index + 1) * 10, 'wing_id' => $wings[$class[2]] ?? null, 'is_active' => true],
        ));

        $classes->each(function (SchoolClass $class, int $index) use ($classes): void {
            if ($class->next_class_id === null && $classes->has($index + 1)) {
                $class->update(['next_class_id' => $classes[$index + 1]->id]);
            }
        });

        $subjects = collect(self::SUBJECTS)->mapWithKeys(fn (array $subject): array => [$subject[1] => Subject::firstOrCreate(
            ['code' => $subject[1]],
            ['name' => $subject[0], 'subject_type_id' => $subjectTypes[$subject[2]] ?? null, 'has_practical' => $subject[3], 'is_active' => true],
        )]);

        foreach (self::CLASSES as $index => [$className, , $wing]) {
            $class = $classes[$index];

            foreach (['A', 'B'] as $sectionName) {
                Section::firstOrCreate(
                    ['class_id' => $class->id, 'academic_session_id' => $session->id, 'name' => $sectionName],
                    ['room' => $class->code.'-'.$sectionName, 'capacity' => 30],
                );
            }

            foreach (self::CURRICULUM[$wing] as $position => $code) {
                $subject = $subjects[$code];

                ClassSubject::firstOrCreate(
                    ['academic_session_id' => $session->id, 'class_id' => $class->id, 'subject_id' => $subject->id],
                    [
                        'is_optional' => in_array($code, ['PHY', 'CHEM', 'BIO'], true),
                        'periods_per_week' => in_array($code, ['ART', 'PE'], true) ? 2 : 5,
                        'theory_max_marks' => $subject->has_practical ? 75 : 100,
                        'practical_max_marks' => $subject->has_practical ? 25 : null,
                        'pass_marks' => 33,
                        'sort_order' => ($position + 1) * 10,
                    ],
                );
            }
        }

        foreach (self::PERIODS as $position => [$name, $startsAt, $endsAt, $isBreak]) {
            Period::firstOrCreate(
                ['name' => $name],
                ['starts_at' => $startsAt, 'ends_at' => $endsAt, 'is_break' => $isBreak, 'sort_order' => ($position + 1) * 10],
            );
        }
    }
}
