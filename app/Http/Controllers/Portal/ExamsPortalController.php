<?php

namespace App\Http\Controllers\Portal;

use App\Models\AcademicSession;
use App\Models\ClassSubject;
use App\Models\Employee;
use App\Models\Exam;
use App\Models\ExamHall;
use App\Models\ExamTerm;
use App\Models\FeeHead;
use App\Models\GradingScale;
use App\Models\PerformanceAlertRule;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Services\AcademicSessionService;
use App\Services\LookupService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Exam screens: exams (date sheet, admit cards, seating, results), marks entry and setup lists.
 */
class ExamsPortalController extends PortalController
{
    public function __construct(private AcademicSessionService $sessionService) {}

    public function exams(LookupService $lookupService): View
    {
        $sessionId = $this->sessionService->current()?->id;

        return $this->page('dashboards.exams.exams', 'admin', 'exams', [
            ...$this->sessionService->options(),
            'terms' => ExamTerm::where('academic_session_id', $sessionId)->orderBy('sort_order')->get(['id', 'name']),
            'exam_types' => $lookupService->options('exam_type'),
            'scales' => GradingScale::orderBy('name')->get(['id', 'name']),
            'fee_heads' => FeeHead::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'halls' => ExamHall::where('is_active', true)->orderBy('name')->get(['id', 'name', 'capacity']),
            'sections' => $this->sections(null),
            'curriculum' => ClassSubject::query()
                ->join('subjects', 'subjects.id', '=', 'class_subject.subject_id')
                ->where('class_subject.academic_session_id', $sessionId)
                ->orderBy('subjects.name')
                ->get(['class_subject.class_id', 'subjects.id', 'subjects.name']),
        ]);
    }

    /**
     * Marks entry: teachers see only the sections where they teach a subject.
     */
    public function marks(): View
    {
        $user = $this->portalUser();
        $manager = $user->hasPermission('manage-exams');
        $employeeId = $manager ? null : Employee::where('user_id', $user->id)->value('id');

        return $this->page('dashboards.exams.marks', $manager ? 'admin' : 'teacher', 'marks', [
            'exams' => Exam::whereIn('status', ['scheduled', 'admit_cards_issued', 'marks_entry', 'review', 'published'])
                ->with(['subjects.subject:id,name', 'schoolClass:id,name'])->latest('id')->get(['id', 'name', 'class_id', 'status']),
            'sections' => $this->sections($manager ? null : ($employeeId ?? 0)),
            'assignments' => $manager ? collect() : TeacherAssignment::where('employee_id', $employeeId ?? 0)->get(['section_id', 'subject_id']),
            'is_manager' => $manager,
        ]);
    }

    /**
     * Performance tracker screens: overview, skill ratings, alerts.
     */
    public function performance(string $screen, LookupService $lookupService): View
    {
        $user = $this->portalUser();
        $manager = $user->hasPermission(['manage-exams', 'manage-students']);
        $employeeId = $manager ? null : Employee::where('user_id', $user->id)->value('id');

        return $this->page('dashboards.performance.'.$screen, $manager ? 'admin' : 'teacher', 'performance', [
            'terms' => ExamTerm::where('academic_session_id', $this->sessionService->current()?->id)->orderBy('sort_order')->get(['id', 'name']),
            'sections' => $this->sections($manager ? null : ($employeeId ?? 0), true),
            'ratings' => $lookupService->options('skill_rating'),
            'subjects' => Subject::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function setup(string $type, LookupService $lookupService): View
    {
        return $this->page('dashboards.exams.setup', 'admin', in_array($type, ['skills', 'rules'], true) ? 'performance' : 'exams', [
            'type' => $type,
            'skill_groups' => $lookupService->options('skill_group'),
            'metrics' => PerformanceAlertRule::METRICS,
            'operators' => PerformanceAlertRule::OPERATORS,
            'sessions' => AcademicSession::orderByDesc('starts_on')->get(['id', 'name', 'is_current']),
            'current_session_id' => $this->sessionService->current()?->id,
        ]);
    }

    /**
     * Current-session sections; for a teacher only those with a teaching assignment
     * (and, when asked, those they are class teacher of).
     *
     * @return Collection<int, Section>
     */
    private function sections(?int $employeeId, bool $withClassTeacher = false)
    {
        return Section::query()
            ->join('classes', 'classes.id', '=', 'sections.class_id')
            ->where('sections.academic_session_id', $this->sessionService->current()?->id)
            ->when($employeeId !== null, fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->whereIn('sections.id', TeacherAssignment::where('employee_id', $employeeId)->select('section_id'))
                ->when($withClassTeacher, fn (Builder $own) => $own->orWhere('sections.class_teacher_id', $employeeId))))
            ->orderBy('classes.level_order')
            ->orderBy('sections.name')
            ->get(['sections.id', 'sections.name', 'sections.class_id', 'classes.name as class_name']);
    }
}
