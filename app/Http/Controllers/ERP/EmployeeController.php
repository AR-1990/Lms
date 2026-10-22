<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\ChangeEmployeeStatusRequest;
use App\Http\Requests\Hr\SaveEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Models\Role;
use App\Services\EmployeeService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private EmployeeService $employeeService) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedResponse($this->employeeService->paginate($this->filters($request), $request->integer('per_page', 15)), EmployeeResource::class, 'Employees retrieved.');
    }

    public function datatable(Request $request): JsonResponse
    {
        return $this->successResponse($this->employeeService->datatable($this->filters($request)), 'Employees retrieved.');
    }

    public function store(SaveEmployeeRequest $request): JsonResponse
    {
        return $this->successResponse(new EmployeeResource($this->employeeService->create($request->validated())), 'Employee added.', 201);
    }

    public function show(Employee $employee): JsonResponse
    {
        return $this->successResponse(new EmployeeResource($this->employeeService->find($employee)), 'Employee retrieved.');
    }

    public function update(SaveEmployeeRequest $request, Employee $employee): JsonResponse
    {
        return $this->successResponse(new EmployeeResource($this->employeeService->update($employee, $request->validated())), 'Employee updated.');
    }

    public function destroy(Employee $employee): JsonResponse
    {
        return $this->deleteWithApproval($employee, 'Employee deleted.');
    }

    public function changeStatus(ChangeEmployeeStatusRequest $request, Employee $employee): JsonResponse
    {
        $updated = $this->employeeService->changeStatus($employee, $request->validated('status'), $request->validated('effective_on'), $request->validated('remarks'));

        return $this->successResponse(new EmployeeResource($updated), 'Status changed to '.str_replace('_', ' ', $updated->status).'.');
    }

    public function createPortalAccount(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'portal_role' => ['required', 'string', Rule::in(Role::whereNotIn('slug', ['admin', 'student', 'parent'])->pluck('slug')->all())],
            'portal_password' => ['required', 'string', 'min:8'],
        ]);

        $this->employeeService->createPortalAccount($employee, $validated['portal_role'], $validated['portal_password']);

        return $this->successResponse(new EmployeeResource($this->employeeService->find($employee)), 'Portal login created. The username is '.$employee->employee_code.'.');
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $search = $request->input('search');

        return [
            'status' => is_string($request->input('status')) ? ($request->input('status') ?: null) : null,
            'department_id' => $request->integer('department_id') ?: null,
            'designation_id' => $request->integer('designation_id') ?: null,
            'is_teaching_staff' => is_scalar($request->input('is_teaching_staff')) ? (string) $request->input('is_teaching_staff') : null,
            'search' => is_string($search) ? (trim($search) ?: null) : null,
        ];
    }
}
