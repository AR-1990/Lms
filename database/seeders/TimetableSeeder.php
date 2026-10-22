<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\ClassSubject;
use App\Models\Employee;
use App\Models\Period;
use App\Models\Section;
use App\Models\TeacherAssignment;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Services\TeacherAssignmentService;
use Illuminate\Database\Seeder;

/**
 * Demo: teacher@lms.test is class teacher of Grade 5 A (so the teacher login can mark its attendance)
 * and Grade 5 A gets a Monday–Friday timetable built from its curriculum.
 */
class TimetableSeeder extends Seeder
{
    public function run(TeacherAssignmentService $assignmentService): void
    {
        $session = AcademicSession::where('is_current', true)->first();
        $section = Section::query()->join('classes', 'classes.id', '=', 'sections.class_id')
            ->where('sections.academic_session_id', $session?->id)->where('classes.name', 'Grade 5')->where('sections.name', 'A')
            ->select('sections.*')->first();
        $teacher = Employee::where('user_id', User::where('email', 'teacher@lms.test')->value('id'))->first();

        if ($section === null || $teacher === null || TimetableSlot::where('section_id', $section->id)->exists()) {
            return;
        }

        $subjects = ClassSubject::where('academic_session_id', $session->id)->where('class_id', $section->class_id)->orderBy('id')->pluck('subject_id')->values();
        $periods = Period::where('is_break', false)->orderBy('sort_order')->pluck('id')->values();

        if ($subjects->isEmpty() || $periods->isEmpty()) {
            return;
        }

        if (! TeacherAssignment::where('section_id', $section->id)->where('subject_id', $subjects[0])->exists()) {
            $assignmentService->create([
                'employee_id' => $teacher->id,
                'academic_session_id' => $session->id,
                'section_id' => $section->id,
                'subject_id' => $subjects[0],
                'is_class_teacher' => true,
                'periods_per_week' => 5,
            ]);
        }

        foreach (range(1, 5) as $day) {
            foreach ($periods as $position => $periodId) {
                $subjectId = $subjects[($position + $day - 1) % $subjects->count()];

                TimetableSlot::create([
                    'section_id' => $section->id,
                    'period_id' => $periodId,
                    'day_of_week' => $day,
                    'subject_id' => $subjectId,
                    'teacher_id' => $subjectId === $subjects[0] ? $teacher->id : null,
                    'room' => 'Room 5A',
                ]);
            }
        }
    }
}
