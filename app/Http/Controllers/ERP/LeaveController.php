<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\ApplyLeaveRequest;
use App\Http\Requests\Hr\SaveLeaveTypeRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\LeaveService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Leave types (manage-leave), the approval queue (HR or the employee's manager) and HR applying on someone's behalf.
 */
class LeaveController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private LeaveService $leaveService) {}

    public function typesDatatable(): JsonResponse
    {
        return $this->successResponse($this->leaveService->typesDatatable(), 'Leave types retrieved.');
    }

    public function showType(LeaveType $leaveType): JsonResponse
    {
        return $this->successResponse($leaveType, 'Leave type retrieved.');
    }

    public function storeType(SaveLeaveTypeRequest $request): JsonResponse
    {
        return $this->successResponse($this->leaveService->saveType(null, $request->validated()), 'Leave type added.', 201);
    }

    public function updateType(SaveLeaveTypeRequest $request, LeaveType $leaveType): JsonResponse
    {
        return $this->successResponse($this->leaveService->saveType($leaveType, $request->validated()), 'Leave type updated.');
    }

    public function destroyType(LeaveType $leaveType): JsonResponse
    {
        return $this->deleteWithApproval($leaveType, 'Leave type deleted.');
    }

    public function datatable(Request $request): JsonResponse
    {
        $filters = $request->validate(['status' => ['nullable', Rule::in(LeaveRequest::STATUSES)], 'employee_id' => ['nullable', 'integer']]);

        return $this->successResponse($this->leaveService->datatable($request->user(), $filters), 'Leave requests retrieved.');
    }

    /**
     * HR applies for an employee (e.g. a phoned-in sick day).
     */
    public function store(ApplyLeaveRequest $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('manage-leave'), 403, 'Only HR can apply for someone else.');
        $employee = Employee::findOrFail($request->validated('employee_id'));

        return $this->successResponse($this->leaveService->apply($employee, $request->validated(), $request->user()), 'Leave request added.', 201);
    }

    public function decide(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $validated = $request->validate(['approve' => ['required', 'boolean'], 'remarks' => ['nullable', 'required_if:approve,false', 'string', 'max:500']]);
        $record = $this->leaveService->decide($request->user(), $leaveRequest->load('employee', 'leaveType'), $validated['approve'], $validated['remarks'] ?? null);

        return $this->successResponse($record, $validated['approve'] ? 'Leave approved.' : 'Leave rejected.');
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        return $this->successResponse($this->leaveService->cancel($request->user(), $leaveRequest->load('employee', 'leaveType')), 'Leave cancelled.');
    }

    public function balances(Employee $employee): JsonResponse
    {
        return $this->successResponse($this->leaveService->balances($employee), 'Leave balances retrieved.');
    }
}
