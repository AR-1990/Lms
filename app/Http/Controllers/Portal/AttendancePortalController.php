<?php

namespace App\Http\Controllers\Portal;

use App\Models\Campus;
use App\Models\ClassSubject;
use App\Models\Employee;
use App\Models\Period;
use App\Models\Section;
use App\Models\TeacherAssignment;
use App\Services\AcademicSessionService;
use App\Services\LookupService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Attendance (students, staff, leave), timetable and calendar screens. Teachers open the same
 * attendance screens from the teacher portal and only see sections they teach.
 */
class AttendancePortalController extends PortalController
{
    public function __construct(private AcademicSessionService $sessionService) {}

    public function register(): View
    {
        return $this->attendancePage('register');
    }

    public function monthly(): View
    {
        return $this->attendancePage('monthly');
    }

    public function leaves(): View
    {
        return $this->attendancePage('leaves');
    }

    public function staff(): View
    {
        return $this->attendancePage('staff');
    }

    public function scan(): View
    {
        return $this->attendancePage('scan');
    }

    public function kiosks(): View
    {
        return $this->page('dashboards.attendance.kiosks', 'admin', 'attendance', ['campuses' => Campus::where('is_active', true)->orderBy('name')->get(['id', 'name'])]);
    }

    public function diary(LookupService $lookupService): View
    {
        return $this->classroomPage('dashboards.diary.diary', 'diary', [
            'entry_types' => $lookupService->options('diary_entry_type'),
            'remark_types' => $lookupService->options('diary_remark_type'),
        ]);
    }

    public function homework(): View
    {
        return $this->classroomPage('dashboards.diary.homework', 'homework');
    }

    public function timetable(): View
    {
        return $this->page('dashboards.admin.academics.timetable', 'admin', 'academics', [
            'sections' => $this->sections(false),
            'periods' => Period::orderBy('sort_order')->get(['id', 'name', 'starts_at', 'ends_at', 'is_break']),
            'curriculum' => $this->curriculum(),
            'teachers' => Employee::where('is_teaching_staff', true)->whereIn('status', Employee::CURRENT_STATUSES)->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'employee_code']),
        ]);
    }

    public function calendar(LookupService $lookupService): View
    {
        return $this->page('dashboards.admin.academics.calendar', 'admin', 'academics', [
            'event_types' => $lookupService->options('calendar_event_type'),
        ]);
    }

    /**
     * Diary / homework screens: the teacher's sections and each class's curriculum subjects.
     *
     * @param  array<string, mixed>  $data
     */
    private function classroomPage(string $view, string $active, array $data = []): View
    {
        $user = $this->portalUser();
        $ownOnly = ! $user->hasPermission('manage-students');

        return $this->page($view, $ownOnly ? 'teacher' : 'admin', $active, [
            'sections' => $this->sections($ownOnly),
            'curriculum' => $this->curriculum(),
            ...$data,
        ]);
    }

    private function curriculum(): Collection
    {
        return ClassSubject::query()
            ->join('subjects', 'subjects.id', '=', 'class_subject.subject_id')
            ->where('class_subject.academic_session_id', $this->sessionService->current()?->id)
            ->orderBy('subjects.name')
            ->get(['class_subject.class_id', 'subjects.id', 'subjects.name']);
    }

    private function attendancePage(string $screen): View
    {
        $user = $this->portalUser();
        $portal = $user->hasPermission('manage-students') ? 'admin' : 'teacher';

        return $this->page("dashboards.attendance.{$screen}", $portal, 'attendance', [
            'sections' => $this->sections(! $user->hasPermission('manage-students')),
        ]);
    }

    /**
     * Current-session sections; for teachers only those they teach or are class teacher of.
     *
     * @return Collection<int, Section>
     */
    private function sections(bool $ownOnly)
    {
        $employeeId = $ownOnly ? Employee::where('user_id', auth()->id())->value('id') : null;

        return Section::query()
            ->join('classes', 'classes.id', '=', 'sections.class_id')
            ->where('sections.academic_session_id', $this->sessionService->current()?->id)
            ->when($ownOnly, fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('sections.class_teacher_id', $employeeId ?? 0)
                ->orWhereIn('sections.id', TeacherAssignment::where('employee_id', $employeeId ?? 0)->select('section_id'))))
            ->orderBy('classes.level_order')
            ->orderBy('sections.name')
            ->get(['sections.id', 'sections.name', 'sections.class_id', 'classes.name as class_name']);
    }
}
