<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeStatusHistory;
use App\Models\Section;
use App\Models\UserDevice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class EmployeeService
{
    /**
     * Statuses that end employment; the portal login is switched off.
     *
     * @var array<int, string>
     */
    private const LEAVING_STATUSES = ['resigned', 'terminated', 'retired'];

    public function __construct(
        private NumberSequenceService $numberSequenceService,
        private UserService $userService,
        private ActivityLogger $activityLogger,
    ) {}

    /**
     * @param  array{status?: string|null, department_id?: int|null, designation_id?: int|null, is_teaching_staff?: string|null, search?: string|null}  $filters
     */
    public function query(array $filters = []): Builder
    {
        return Employee::query()
            ->select([
                'id', 'user_id', 'employee_code', 'first_name', 'middle_name', 'last_name', 'gender_id', 'phone', 'email', 'cnic',
                'department_id', 'designation_id', 'employment_type_id', 'joining_date', 'is_teaching_staff', 'status', 'exit_date', 'created_at', 'updated_at',
            ])
            ->with(['designation:id,name', 'department:id,name', 'employmentType:id,name'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['department_id'] ?? null, fn (Builder $query, int $id) => $query->where('department_id', $id))
            ->when($filters['designation_id'] ?? null, fn (Builder $query, int $id) => $query->where('designation_id', $id))
            ->when(isset($filters['is_teaching_staff']) && $filters['is_teaching_staff'] !== '', fn (Builder $query) => $query->where('is_teaching_staff', filter_var($filters['is_teaching_staff'], FILTER_VALIDATE_BOOLEAN)))
            ->when(filled($filters['search'] ?? null), fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('first_name', 'like', '%'.$filters['search'].'%')
                ->orWhere('last_name', 'like', '%'.$filters['search'].'%')
                ->orWhere('employee_code', 'like', '%'.$filters['search'].'%')
                ->orWhere('phone', 'like', '%'.$filters['search'].'%')
                ->orWhere('cnic', 'like', '%'.$filters['search'].'%')));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query($filters)->orderBy('first_name')->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function datatable(array $filters): array
    {
        return DataTables::eloquent($this->query(collect($filters)->except('search')->all()))
            ->escapeColumns([])
            ->addColumn('full_name', fn (Employee $employee): string => $employee->fullName())
            ->addColumn('designation_name', fn (Employee $employee): ?string => $employee->designation?->name)
            ->addColumn('department_name', fn (Employee $employee): ?string => $employee->department?->name)
            ->addColumn('employment_type_name', fn (Employee $employee): ?string => $employee->employmentType?->name)
            ->editColumn('joining_date', fn (Employee $employee): ?string => $employee->joining_date?->toDateString())
            ->filterColumn('full_name', fn (Builder $query, string $keyword) => $query->where(fn (Builder $inner) => $inner
                ->where('first_name', 'like', "%{$keyword}%")
                ->orWhere('last_name', 'like', "%{$keyword}%")))
            ->orderColumn('full_name', 'first_name $1, last_name $1')
            ->removeColumn('designation', 'department', 'employment_type')
            ->toArray();
    }

    public function find(Employee $employee): Employee
    {
        return $employee->load([
            'designation:id,name', 'department:id,name', 'employmentType:id,name', 'gender:id,name', 'reportsTo:id,first_name,last_name,employee_code',
            'user:id,username,email,is_active', 'statusHistories.changedBy:id,name',
        ]);
    }

    /**
     * Create the employee with the next employee code and, when asked, a portal login.
     *
     * @param  array<string, mixed>  $data  employee fields plus optional portal_role / portal_password
     */
    public function create(array $data): Employee
    {
        return DB::transaction(function () use ($data): Employee {
            $employee = Employee::create([
                ...collect($data)->except(['portal_role', 'portal_password'])->all(),
                'employee_code' => $this->numberSequenceService->next('employee_code'),
                'is_teaching_staff' => $data['is_teaching_staff'] ?? (bool) Designation::whereKey($data['designation_id'])->value('is_teaching'),
                'status' => 'active',
            ]);

            $this->history($employee, 'status', null, 'active', $employee->joining_date->toDateString(), 'Joined');

            if (filled($data['portal_role'] ?? null)) {
                $this->createPortalAccount($employee, $data['portal_role'], $data['portal_password']);
            }

            return $this->find($employee);
        });
    }

    /**
     * Designation and department changes are kept in the service record.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Employee $employee, array $data): Employee
    {
        return DB::transaction(function () use ($employee, $data): Employee {
            $before = ['designation' => $employee->designation_id, 'department' => $employee->department_id];
            $employee->update($data);

            if ((int) $before['designation'] !== (int) $employee->designation_id) {
                $this->history($employee, 'designation', Designation::whereKey($before['designation'])->value('name'), $employee->designation()->value('name'), now()->toDateString());
            }

            if ((int) $before['department'] !== (int) $employee->department_id) {
                $this->history($employee, 'department', Department::whereKey($before['department'])->value('name'), $employee->department()->value('name'), now()->toDateString());
            }

            if ($employee->user_id !== null) {
                $employee->user()->first()?->update(['name' => $employee->fullName()]);
            }

            return $this->find($employee);
        });
    }

    /**
     * Change the employment status. Leaving statuses set the exit date and switch the portal login off.
     *
     * @throws ValidationException when the status does not change
     */
    public function changeStatus(Employee $employee, string $status, string $effectiveOn, ?string $remarks): Employee
    {
        if ($employee->status === $status) {
            throw ValidationException::withMessages(['status' => ['The employee is already '.str_replace('_', ' ', $status).'.']]);
        }

        return DB::transaction(function () use ($employee, $status, $effectiveOn, $remarks): Employee {
            $old = $employee->status;
            $isLeaving = in_array($status, self::LEAVING_STATUSES, true);

            $employee->update(['status' => $status, 'exit_date' => $isLeaving ? $effectiveOn : null]);
            $this->history($employee, 'status', $old, $status, $effectiveOn, $remarks);

            if ($employee->user_id !== null) {
                $user = $employee->user()->first();
                $user?->update(['is_active' => ! $isLeaving]);

                if ($isLeaving && $user !== null) {
                    $user->tokens()->delete();
                    UserDevice::where('user_id', $user->id)->update(['is_active' => false]);
                }
            }

            return $this->find($employee);
        });
    }

    /**
     * @throws ValidationException when the employee still heads a department or teaches a section
     */
    public function delete(Employee $employee): void
    {
        if (Department::where('head_employee_id', $employee->id)->exists()) {
            throw ValidationException::withMessages(['employee' => ['This employee heads a department. Choose another head first.']]);
        }

        if ($employee->teacherAssignments()->exists()) {
            throw ValidationException::withMessages(['employee' => ['This employee has teaching assignments. Remove them first, or mark the employee as resigned.']]);
        }

        DB::transaction(function () use ($employee): void {
            Section::where('class_teacher_id', $employee->id)->update(['class_teacher_id' => null]);
            Employee::where('reports_to_id', $employee->id)->update(['reports_to_id' => null]);

            if ($employee->user_id !== null) {
                $employee->user()->first()?->update(['is_active' => false]);
            }

            $employee->delete();
        });
    }

    /**
     * @throws ValidationException when the employee already has a login or no email
     */
    public function createPortalAccount(Employee $employee, string $role, string $password): Employee
    {
        if ($employee->user_id !== null) {
            throw ValidationException::withMessages(['portal_role' => ['This employee already has a portal login.']]);
        }

        if (blank($employee->email)) {
            throw ValidationException::withMessages(['email' => ['An email address is needed to create a portal login.']]);
        }

        $user = $this->userService->createUser([
            'name' => $employee->fullName(),
            'email' => $employee->email,
            'username' => $employee->employee_code,
            'phone' => null,
            'campus_id' => $employee->campus_id,
            'password' => $password,
        ], [$role]);

        $employee->forceFill(['user_id' => $user->id])->saveQuietly();

        $this->activityLogger->record('employees', 'portal_account_created', (auth()->user()?->name ?? 'System')." created a portal login for {$employee->fullName()}", $employee);

        return $employee;
    }

    private function history(Employee $employee, string $field, ?string $old, ?string $new, string $effectiveOn, ?string $remarks = null): void
    {
        EmployeeStatusHistory::create([
            'employee_id' => $employee->id,
            'field' => $field,
            'old_value' => $old,
            'new_value' => $new,
            'effective_on' => $effectiveOn,
            'remarks' => $remarks,
            'changed_by' => auth()->id(),
        ]);
    }
}
