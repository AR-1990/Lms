<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\SaveDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use App\Services\DepartmentService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private DepartmentService $departmentService) {}

    public function index(Request $request): JsonResponse
    {
        return $this->successResponse(DepartmentResource::collection($this->departmentService->query($this->filters($request))->orderBy('name')->get())->resolve(), 'Departments retrieved.');
    }

    public function datatable(Request $request): JsonResponse
    {
        return $this->successResponse($this->departmentService->datatable($this->filters($request)), 'Departments retrieved.');
    }

    public function store(SaveDepartmentRequest $request): JsonResponse
    {
        return $this->successResponse(new DepartmentResource($this->departmentService->create($request->validated())), 'Department created.', 201);
    }

    public function show(Department $department): JsonResponse
    {
        return $this->successResponse(new DepartmentResource($department->load('head:id,first_name,last_name,employee_code')->loadCount(['employees', 'designations'])), 'Department retrieved.');
    }

    public function update(SaveDepartmentRequest $request, Department $department): JsonResponse
    {
        return $this->successResponse(new DepartmentResource($this->departmentService->update($department, $request->validated())), 'Department updated.');
    }

    public function destroy(Department $department): JsonResponse
    {
        return $this->deleteWithApproval($department, 'Department deleted.');
    }

    /**
     * @return array{is_active: string|null}
     */
    private function filters(Request $request): array
    {
        return ['is_active' => is_scalar($request->input('is_active')) ? (string) $request->input('is_active') : null];
    }
}
