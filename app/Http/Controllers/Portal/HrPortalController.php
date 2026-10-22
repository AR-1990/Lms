<?php

namespace App\Http\Controllers\Portal;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\JobOpening;
use App\Models\Role;
use App\Models\Section;
use App\Services\AcademicSessionService;
use App\Services\LookupService;
use Illuminate\View\View;

/**
 * Employees and recruitment screens; all data goes through the /api/erp HR endpoints.
 */
class HrPortalController extends PortalController
{
    public function __construct(private LookupService $lookupService) {}

    public function employees(): View
    {
        return $this->screen('employees');
    }

    public function assignments(AcademicSessionService $sessionService): View
    {
        $session = $sessionService->current();

        return $this->screen('assignments', [
            ...$sessionService->options(),
            'teachers' => Employee::where('is_teaching_staff', true)->whereIn('status', Employee::CURRENT_STATUSES)->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'employee_code']),
            'sections' => Section::query()
                ->join('classes', 'classes.id', '=', 'sections.class_id')
                ->where('sections.academic_session_id', $session?->id)
                ->orderBy('classes.level_order')
                ->orderBy('sections.name')
                ->get(['sections.id', 'sections.class_id', 'sections.name', 'classes.name as class_name']),
        ]);
    }

    public function candidates(): View
    {
        return $this->screen('candidates', [
            'openings' => JobOpening::whereIn('status', ['open', 'on_hold'])->orderBy('title')->get(['id', 'title as name']),
            'all_openings' => JobOpening::orderBy('title')->get(['id', 'title as name']),
        ]);
    }

    public function openings(): View
    {
        return $this->screen('openings');
    }

    public function departments(): View
    {
        return $this->screen('departments');
    }

    public function designations(): View
    {
        return $this->screen('designations');
    }

    public function purchases(): View
    {
        return $this->screen('purchases');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function screen(string $screen, array $data = []): View
    {
        $lookups = collect(['gender', 'employment_type', 'marital_status', 'religion', 'blood_group', 'bank', 'pay_grade', 'qualification_level', 'job_source', 'interview_round_type', 'purchase_category', 'unit_of_measure'])
            ->mapWithKeys(fn (string $type): array => [$type => $this->lookupService->options($type)]);

        return $this->page("dashboards.hr.{$screen}", 'admin', 'hr', [
            'lookups' => $lookups,
            'departments' => Department::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'designations' => Designation::where('is_active', true)->orderBy('name')->get(['id', 'name', 'department_id', 'is_teaching']),
            'staff_roles' => Role::whereNotIn('slug', ['admin', 'student', 'parent'])->orderBy('name')->get(['id', 'name', 'slug']),
            ...$data,
        ]);
    }
}
