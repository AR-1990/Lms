<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\ClassSubject;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamTerm;
use App\Models\Section;
use App\Models\StudentAttendance;
use App\Models\TeacherAssignment;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * The signed-in teacher's own view: their sections and subjects this session, today's periods and
 * today's attendance. Marking attendance and entering marks live in the Attendance and Exams modules.
 */
class TeacherService
{
    /**
     * @return array{teacher: array<string, mixed>|null, metrics: array<string, int|float|null>, today_schedule: array<int, array<string, mixed>>}
     */
    public function getTeacherDashboard(User $user): array
    {
        $employee = $this->employeeFor($user);
        $assignments = $this->assignments($employee);
        $sectionIds = $assignments->pluck('section_id')->unique()->values();
        $today = StudentAttendance::whereIn('section_id', $sectionIds)->whereDate('date', today())->get(['section_id', 'status']);
        $schedule = $this->todaySchedule($employee);

        return [
            'teacher' => $employee === null ? null : [
                'id' => $employee->id,
                'name' => $employee->fullName(),
                'employee_code' => $employee->employee_code,
                'department' => $employee->department?->name,
                'designation' => $employee->designation?->name,
            ],
            'metrics' => [
                'sections' => $sectionIds->count(),
                'subjects' => $assignments->pluck('subject_id')->filter()->unique()->count(),
                'students' => $sectionIds->isEmpty() ? 0 : Enrollment::whereIn('section_id', $sectionIds)->where('status', 'active')->count(),
                'periods_today' => count($schedule),
                'sections_marked_today' => $today->pluck('section_id')->unique()->count(),
                'today_attendance_percentage' => $today->isEmpty() ? null : round($today->whereIn('status', StudentAttendance::ATTENDED)->count() * 100 / $today->count(), 1),
            ],
            'today_schedule' => $schedule,
        ];
    }

    /**
     * The teacher's subject-in-section assignments this session, with their timetable periods.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAssignedClasses(User $user): array
    {
        $employee = $this->employeeFor($user);
        $assignments = $this->assignments($employee);
        $students = Enrollment::whereIn('section_id', $assignments->pluck('section_id')->unique())->where('status', 'active')
            ->selectRaw('section_id, count(*) as total')->groupBy('section_id')->pluck('total', 'section_id');
        $slots = $employee === null ? collect() : TimetableSlot::where('teacher_id', $employee->id)->with('period:id,name')->get(['id', 'section_id', 'subject_id', 'period_id', 'day_of_week']);
        $days = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

        return $assignments->map(fn (TeacherAssignment $assignment): array => [
            'id' => $assignment->id,
            'course_code' => $assignment->subject?->code,
            'title' => $assignment->subject?->name ?? 'Class teacher',
            'class' => $assignment->section?->schoolClass?->name,
            'section' => trim(($assignment->section?->schoolClass?->name ?? '').' '.($assignment->section?->name ?? '')),
            'section_id' => $assignment->section_id,
            'room' => $assignment->section?->room,
            'is_class_teacher' => (bool) $assignment->is_class_teacher,
            'periods_per_week' => $assignment->periods_per_week,
            'students' => (int) ($students[$assignment->section_id] ?? 0),
            'schedule' => $slots->where('section_id', $assignment->section_id)->where('subject_id', $assignment->subject_id)->sortBy('day_of_week')
                ->map(fn (TimetableSlot $slot): string => ($days[$slot->day_of_week] ?? '').' '.$slot->period?->name)->values()->implode(', '),
        ])->values()->all();
    }

    /**
     * Picker data for the teacher's screens (attendance, diary, homework, marks): the sections they teach or
     * class-teach this session, each with its curriculum subjects (is_mine marks the ones they teach), and the
     * exams of those classes that are open for marks.
     *
     * @return array{sections: array<int, array<string, mixed>>, exams: array<int, array<string, mixed>>}
     */
    public function options(User $user): array
    {
        $employeeId = $this->employeeFor($user)?->id ?? 0;
        $sessionId = AcademicSession::where('is_current', true)->value('id');
        $assignments = TeacherAssignment::where('employee_id', $employeeId)->where('academic_session_id', $sessionId)->get(['section_id', 'subject_id']);
        $sections = Section::query()
            ->join('classes', 'classes.id', '=', 'sections.class_id')
            ->where('sections.academic_session_id', $sessionId)
            ->where(fn ($query) => $query->where('sections.class_teacher_id', $employeeId)->orWhereIn('sections.id', $assignments->pluck('section_id')))
            ->orderBy('classes.level_order')->orderBy('sections.name')
            ->get(['sections.id', 'sections.name', 'sections.class_id', 'sections.class_teacher_id', 'classes.name as class_name']);
        $subjects = ClassSubject::query()->join('subjects', 'subjects.id', '=', 'class_subject.subject_id')
            ->where('class_subject.academic_session_id', $sessionId)->whereIn('class_subject.class_id', $sections->pluck('class_id')->unique())
            ->orderBy('class_subject.sort_order')->orderBy('subjects.name')
            ->get(['class_subject.class_id', 'subjects.id', 'subjects.name']);
        $exams = Exam::whereIn('class_id', $sections->pluck('class_id')->unique())->whereIn('status', ['scheduled', 'admit_cards_issued', 'marks_entry', 'review', 'published'])
            ->whereIn('exam_term_id', ExamTerm::where('academic_session_id', $sessionId)->select('id'))
            ->with('subjects.subject:id,name')->latest('id')->get(['id', 'name', 'class_id', 'status']);

        return [
            'sections' => $sections->map(fn (Section $section): array => [
                'id' => $section->id,
                'name' => trim($section->class_name.' '.$section->name),
                'class_id' => $section->class_id,
                'is_class_teacher' => $section->class_teacher_id === $employeeId,
                'subjects' => $subjects->where('class_id', $section->class_id)->map(fn ($subject): array => [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'is_mine' => $assignments->contains(fn ($assignment): bool => $assignment->section_id === $section->id && $assignment->subject_id === $subject->id),
                ])->values()->all(),
            ])->values()->all(),
            'exams' => $exams->map(fn (Exam $exam): array => [
                'id' => $exam->id,
                'name' => $exam->name,
                'class_id' => $exam->class_id,
                'status' => $exam->status,
                'papers' => $exam->subjects->map(fn ($paper): array => [
                    'id' => $paper->id,
                    'subject_id' => $paper->subject_id,
                    'subject' => $paper->subject?->name,
                    'theory_max_marks' => $paper->theory_max_marks,
                    'practical_max_marks' => $paper->practical_max_marks,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    public function employeeFor(User $user): ?Employee
    {
        return Employee::where('user_id', $user->id)->with(['department:id,name', 'designation:id,name'])->first();
    }

    /**
     * @return Collection<int, TeacherAssignment>
     */
    private function assignments(?Employee $employee): Collection
    {
        if ($employee === null) {
            return new Collection;
        }

        return TeacherAssignment::where('employee_id', $employee->id)
            ->whereIn('academic_session_id', AcademicSession::where('is_current', true)->select('id'))
            ->with(['subject:id,name,code', 'section:id,name,room,class_id', 'section.schoolClass:id,name'])
            ->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function todaySchedule(?Employee $employee): array
    {
        if ($employee === null) {
            return [];
        }

        $own = TimetableSlot::where('teacher_id', $employee->id)
            ->where('day_of_week', today()->dayOfWeekIso)
            ->with(['subject:id,name', 'section:id,name,class_id', 'section.schoolClass:id,name', 'period:id,name,starts_at,ends_at,sort_order'])
            ->get();
        $covers = app(SubstitutionService::class)->coversFor($employee, today()->toDateString());

        return $own->map(fn (TimetableSlot $slot): array => [$slot, false])
            ->concat($covers->filter(fn ($cover): bool => $cover->slot !== null)->map(fn ($cover): array => [$cover->slot, true]))
            ->sortBy(fn (array $entry): int => (int) $entry[0]->period?->sort_order)
            ->map(fn (array $entry): array => [
                'period' => $entry[0]->period?->name,
                'starts_at' => $entry[0]->period?->starts_at ? substr((string) $entry[0]->period->starts_at, 0, 5) : null,
                'ends_at' => $entry[0]->period?->ends_at ? substr((string) $entry[0]->period->ends_at, 0, 5) : null,
                'subject' => $entry[0]->subject?->name,
                'section' => trim(($entry[0]->section?->schoolClass?->name ?? '').' '.($entry[0]->section?->name ?? '')),
                'room' => $entry[0]->room,
                'is_cover' => $entry[1],
            ])->values()->all();
    }
}
