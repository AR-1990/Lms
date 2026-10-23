<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\StaffAttendance;
use App\Models\User;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

/**
 * Staff leave: dynamic leave types, yearly balances (with carry forward), applying, approving by HR or the
 * employee's manager, and marking approved days as "leave" in staff attendance. Sundays and calendar
 * holidays are not counted.
 */
class LeaveService
{
    public function __construct(
        private CalendarService $calendarService,
        private NotificationService $notificationService,
        private ActivityLogger $activityLogger,
    ) {}

    /* ---------- Leave types ---------- */

    /**
     * @return array<string, mixed>
     */
    public function typesDatatable(): array
    {
        return DataTables::eloquent(LeaveType::query()->with('gender:id,name'))
            ->addColumn('gender_name', fn (LeaveType $type): ?string => $type->gender?->name)
            ->removeColumn('gender')
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveType(?LeaveType $type, array $data): LeaveType
    {
        return DB::transaction(function () use ($type, $data): LeaveType {
            if ($type === null) {
                return LeaveType::create($data);
            }

            $type->update($data);

            return $type;
        });
    }

    public function deleteType(LeaveType $type): void
    {
        if (LeaveRequest::where('leave_type_id', $type->id)->exists()) {
            throw ValidationException::withMessages(['leave_type' => ['Leave requests use this type; make it inactive instead.']]);
        }

        DB::transaction(fn () => $type->delete());
    }

    /* ---------- Balances ---------- */

    /**
     * The year's balance, created on first use with the yearly allocation and any carried-forward days.
     */
    public function balanceFor(Employee $employee, LeaveType $type, int $year): LeaveBalance
    {
        $balance = LeaveBalance::where('employee_id', $employee->id)->where('leave_type_id', $type->id)->where('year', $year)->first();

        if ($balance !== null) {
            return $balance;
        }

        $previous = LeaveBalance::where('employee_id', $employee->id)->where('leave_type_id', $type->id)->where('year', $year - 1)->first();
        $carried = $type->carry_forward && $previous ? max(0, min($previous->remaining(), $type->max_carry_forward ?? $previous->remaining())) : 0;

        return LeaveBalance::create([
            'employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => $year,
            'allocated' => $type->days_per_year, 'carried_forward' => $carried, 'used' => 0,
        ]);
    }

    /**
     * Every active leave type the employee may take, with this year's numbers.
     *
     * @return array<int, array<string, mixed>>
     */
    public function balances(Employee $employee, ?int $year = null): array
    {
        $year ??= (int) now()->format('Y');

        return $this->typesFor($employee)->map(function (LeaveType $type) use ($employee, $year): array {
            $balance = $type->is_paid ? $this->balanceFor($employee, $type, $year) : null;

            return [
                'leave_type_id' => $type->id,
                'name' => $type->name,
                'is_paid' => $type->is_paid,
                'requires_document' => $type->requires_document,
                'allocated' => $balance?->allocated,
                'carried_forward' => $balance?->carried_forward,
                'used' => $balance?->used ?? (float) LeaveRequest::where('employee_id', $employee->id)->where('leave_type_id', $type->id)->where('status', 'approved')->whereYear('from_date', $year)->sum('days'),
                'remaining' => $balance?->remaining(),
            ];
        })->values()->all();
    }

    /**
     * @return Collection<int, LeaveType>
     */
    public function typesFor(Employee $employee): Collection
    {
        return LeaveType::where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('applicable_gender_id')->orWhere('applicable_gender_id', $employee->gender_id))
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Working dates between two days: no Sundays and no calendar holidays.
     *
     * @return array<int, string>
     */
    public function workingDates(string $from, string $to): array
    {
        return collect(CarbonPeriod::create($from, $to))
            ->reject(fn (CarbonInterface $day): bool => $day->isSunday() || $this->calendarService->holidayOn($day->toDateString()) !== null)
            ->map(fn (CarbonInterface $day): string => $day->toDateString())
            ->values()
            ->all();
    }

    /* ---------- Requests ---------- */

    /**
     * @param  array{scope?: string|null, status?: string|null, employee_id?: int|null}  $filters
     */
    public function requestsQuery(User $user, array $filters = []): Builder
    {
        $managedIds = Employee::where('reports_to_id', Employee::where('user_id', $user->id)->value('id') ?? 0)->pluck('id');

        return LeaveRequest::query()
            ->with(['employee:id,first_name,last_name,employee_code,department_id', 'leaveType:id,name,is_paid', 'approver:id,name'])
            ->when(! $user->hasPermission('manage-leave'), fn (Builder $query) => $query->whereIn('employee_id', $managedIds))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['employee_id'] ?? null, fn (Builder $query, int $employeeId) => $query->where('employee_id', $employeeId));
    }

    /**
     * @param  array{status?: string|null, employee_id?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function datatable(User $user, array $filters): array
    {
        return DataTables::eloquent($this->requestsQuery($user, $filters))
            ->addColumn('employee_name', fn (LeaveRequest $request): ?string => $request->employee?->fullName())
            ->addColumn('employee_code', fn (LeaveRequest $request): ?string => $request->employee?->employee_code)
            ->addColumn('leave_type_name', fn (LeaveRequest $request): ?string => $request->leaveType?->name)
            ->addColumn('approved_by_name', fn (LeaveRequest $request): ?string => $request->approver?->name)
            ->editColumn('from_date', fn (LeaveRequest $request): string => $request->from_date->toDateString())
            ->editColumn('to_date', fn (LeaveRequest $request): string => $request->to_date->toDateString())
            ->removeColumn('employee', 'leaveType', 'approver')
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $data  leave_type_id, from_date, to_date, is_half_day, reason, substitute_employee_id
     */
    public function apply(Employee $employee, array $data, User $actor): LeaveRequest
    {
        $type = LeaveType::findOrFail($data['leave_type_id']);

        if (! $this->typesFor($employee)->contains('id', $type->id)) {
            throw ValidationException::withMessages(['leave_type_id' => ['This leave type does not apply to this employee.']]);
        }

        $halfDay = (bool) ($data['is_half_day'] ?? false) && $data['from_date'] === $data['to_date'];
        $dates = $this->workingDates($data['from_date'], $data['to_date']);
        $days = $halfDay ? 0.5 : (float) count($dates);

        if ($days <= 0) {
            throw ValidationException::withMessages(['from_date' => ['These dates are Sundays or holidays; no leave is needed.']]);
        }

        $overlap = LeaveRequest::where('employee_id', $employee->id)->whereIn('status', ['pending', 'approved'])
            ->whereDate('from_date', '<=', $data['to_date'])->whereDate('to_date', '>=', $data['from_date'])->exists();

        if ($overlap) {
            throw ValidationException::withMessages(['from_date' => ['There is already a leave request for some of these days.']]);
        }

        if ($type->is_paid) {
            $remaining = $this->balanceFor($employee, $type, (int) Carbon::parse($data['from_date'])->format('Y'))->remaining();

            if ($days > $remaining) {
                throw ValidationException::withMessages(['leave_type_id' => ["Only {$remaining} day(s) of {$type->name} are left; apply for {$days} or choose unpaid leave."]]);
            }
        }

        $request = DB::transaction(fn (): LeaveRequest => LeaveRequest::create([
            'employee_id' => $employee->id, 'leave_type_id' => $type->id, 'from_date' => $data['from_date'], 'to_date' => $data['to_date'],
            'days' => $days, 'is_half_day' => $halfDay, 'reason' => $data['reason'], 'substitute_employee_id' => $data['substitute_employee_id'] ?? null, 'status' => 'pending',
        ]));

        $this->notificationService->notify($this->approverIds($employee, $actor), 'leave_request', "{$employee->fullName()} applied for {$days} day(s) {$type->name}",
            Carbon::parse($data['from_date'])->format('d M').' – '.Carbon::parse($data['to_date'])->format('d M Y').': '.$data['reason'], route('admin.hr.leave'), $request);

        return $request;
    }

    public function canDecide(User $user, LeaveRequest $request): bool
    {
        $employee = $request->employee;

        if ($employee === null || $employee->user_id === $user->id) {
            return false;
        }

        return $user->hasPermission('manage-leave') || ($employee->reports_to_id !== null && Employee::whereKey($employee->reports_to_id)->value('user_id') === $user->id);
    }

    /**
     * Approve or reject a pending request. Approval uses the balance and marks attendance as leave.
     */
    public function decide(User $user, LeaveRequest $request, bool $approve, ?string $remarks): LeaveRequest
    {
        if (! $this->canDecide($user, $request)) {
            throw ValidationException::withMessages(['status' => ['Only HR or the employee\'s manager can decide this request (not the applicant).']]);
        }

        if ($request->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ["This request is already {$request->status}."]]);
        }

        DB::transaction(function () use ($user, $request, $approve, $remarks): void {
            $request->update(['status' => $approve ? 'approved' : 'rejected', 'approved_by' => $user->id, 'approved_at' => now(), 'review_remarks' => $remarks]);

            if ($approve) {
                $this->useBalance($request, $request->days);

                foreach ($this->workingDates($request->from_date->toDateString(), $request->to_date->toDateString()) as $date) {
                    $attendance = StaffAttendance::where('employee_id', $request->employee_id)->whereDate('date', $date)->first()
                        ?? new StaffAttendance(['employee_id' => $request->employee_id, 'date' => $date]);
                    $attendance->fill(['status' => $request->is_half_day ? 'half_day' : 'leave', 'source' => 'manual', 'remarks' => $request->leaveType->name, 'marked_by' => $user->id])->save();
                }
            }

            $this->activityLogger->record('leave', $approve ? 'approved' : 'rejected', "{$user->name} ".($approve ? 'approved' : 'rejected')." {$request->days} day(s) {$request->leaveType->name} of {$request->employee->fullName()}", $request);
        });

        if ($request->employee->user_id) {
            $this->notificationService->notify([$request->employee->user_id], 'leave_decided', 'Your leave was '.($approve ? 'approved ✅' : 'not approved'),
                $request->from_date->format('d M').' – '.$request->to_date->format('d M Y').($remarks ? ': '.$remarks : ''), route('my-hr'), $request);
        }

        return $request;
    }

    /**
     * The employee (or HR) cancels a pending request, or approved leave that has not started; days go back to the balance.
     */
    public function cancel(User $user, LeaveRequest $request): LeaveRequest
    {
        $own = $request->employee?->user_id === $user->id;

        if (! $own && ! $user->hasPermission('manage-leave')) {
            throw ValidationException::withMessages(['status' => ['You can only cancel your own leave.']]);
        }

        if (! in_array($request->status, ['pending', 'approved'], true) || ($request->status === 'approved' && $request->from_date->isPast() && ! $request->from_date->isToday())) {
            throw ValidationException::withMessages(['status' => ['Only pending leave, or approved leave that has not started, can be cancelled.']]);
        }

        DB::transaction(function () use ($user, $request): void {
            if ($request->status === 'approved') {
                $this->useBalance($request, -$request->days);
                StaffAttendance::where('employee_id', $request->employee_id)->whereDate('date', '>=', $request->from_date->toDateString())->whereDate('date', '<=', $request->to_date->toDateString())
                    ->whereIn('status', ['leave', 'half_day'])->get()->each(fn (StaffAttendance $attendance) => $attendance->update(['status' => 'present', 'remarks' => 'Leave cancelled']));
            }

            $request->update(['status' => 'cancelled']);
            $this->activityLogger->record('leave', 'cancelled', "{$user->name} cancelled leave of {$request->employee->fullName()}", $request);
        });

        return $request;
    }

    private function useBalance(LeaveRequest $request, float $days): void
    {
        if (! $request->leaveType->is_paid) {
            return;
        }

        $balance = $this->balanceFor($request->employee, $request->leaveType, (int) $request->from_date->format('Y'));
        $balance->update(['used' => max(0, $balance->used + $days)]);
    }

    /**
     * Who is told about a new request: the manager, else everyone with manage-leave.
     *
     * @return array<int, int>
     */
    private function approverIds(Employee $employee, User $actor): array
    {
        $managerUserId = $employee->reports_to_id ? Employee::whereKey($employee->reports_to_id)->value('user_id') : null;

        $ids = $managerUserId ? [$managerUserId] : User::where('is_active', true)
            ->whereHas('roles.permissions', fn (Builder $query) => $query->where('slug', 'manage-leave'))->pluck('id')->all();

        return array_values(array_diff($ids, [$actor->id]));
    }
}
