<?php

namespace App\Http\Controllers\Portal;

use App\Models\Account;
use App\Models\AppraisalCycle;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\FiscalYear;
use App\Models\JobOpening;
use App\Models\LeaveType;
use App\Models\PaymentMethod;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\SalaryComponent;
use App\Models\Section;
use App\Models\WorkShift;
use App\Services\AcademicSessionService;
use App\Services\LookupService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;

/**
 * Employees and recruitment screens; all data goes through the /api/erp HR endpoints.
 */
class HrPortalController extends PortalController
{
    public function __construct(private LookupService $lookupService) {}

    public function employees(): View
    {
        return $this->screen('employees', ['shifts' => WorkShift::where('is_active', true)->orderBy('name')->get(['id', 'name'])]);
    }

    public function shifts(): View
    {
        return $this->screen('shifts');
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

    public function leave(): View
    {
        return $this->screen('leave', ['staff' => $this->staff(), 'leave_types' => LeaveType::where('is_active', true)->orderBy('sort_order')->get(['id', 'name'])]);
    }

    public function leaveTypes(): View
    {
        return $this->screen('leave-types');
    }

    public function appraisals(): View
    {
        return $this->screen('appraisals', ['cycles' => AppraisalCycle::where('status', '!=', 'draft')->latest('id')->get(['id', 'name', 'status'])]);
    }

    public function appraisalCycles(): View
    {
        return $this->screen('appraisal-cycles');
    }

    public function appraisalCriteria(): View
    {
        return $this->screen('appraisal-criteria');
    }

    public function salarySetup(): View
    {
        return $this->screen('salary-setup', [
            'accounts' => Account::where('is_group', false)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'fiscal_years' => FiscalYear::orderByDesc('starts_on')->get(['id', 'name', 'is_current']),
        ]);
    }

    public function salaries(): View
    {
        return $this->screen('salaries', ['components' => SalaryComponent::where('is_active', true)->whereNotIn('code', SalaryComponent::SYSTEM_CODES)->orderBy('type')->orderBy('sort_order')->get()]);
    }

    public function payroll(): View
    {
        return $this->screen('payroll');
    }

    public function adjustments(): View
    {
        return $this->screen('adjustments', [
            'staff' => $this->staff(),
            'components' => SalaryComponent::where('is_active', true)->whereNotIn('code', SalaryComponent::SYSTEM_CODES)->orderBy('sort_order')->get(['id', 'name', 'type']),
        ]);
    }

    public function payrollRun(PayrollRun $payrollRun): View
    {
        return $this->screen('payroll-run', [
            'run' => $payrollRun->load('approver:id,name'),
            'payment_methods' => PaymentMethod::where('is_active', true)->whereNotNull('account_id')->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function exits(): View
    {
        return $this->screen('exits', [
            'staff' => $this->staff(),
            'exit_reasons' => $this->lookupService->options('exit_reason'),
            'payment_methods' => PaymentMethod::where('is_active', true)->whereNotNull('account_id')->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function loans(): View
    {
        return $this->screen('loans', [
            'staff' => $this->staff(),
            'payment_methods' => PaymentMethod::where('is_active', true)->whereNotNull('account_id')->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    /**
     * @return Collection<int, Employee>
     */
    private function staff(): Collection
    {
        return Employee::whereIn('status', Employee::CURRENT_STATUSES)->orderBy('first_name')->get(['id', 'first_name', 'middle_name', 'last_name', 'employee_code']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function screen(string $screen, array $data = []): View
    {
        $lookups = collect(['loan_type', 'gender', 'employment_type', 'marital_status', 'religion', 'blood_group', 'bank', 'pay_grade', 'qualification_level', 'job_source', 'interview_round_type', 'purchase_category', 'unit_of_measure'])
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
