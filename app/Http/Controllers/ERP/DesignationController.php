<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\SaveDesignationRequest;
use App\Http\Resources\DesignationResource;
use App\Models\Designation;
use App\Services\DesignationService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DesignationController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private DesignationService $designationService) {}

    public function index(Request $request): JsonResponse
    {
        return $this->successResponse(DesignationResource::collection($this->designationService->query($this->filters($request))->orderBy('name')->get())->resolve(), 'Designations retrieved.');
    }

    public function datatable(Request $request): JsonResponse
    {
        return $this->successResponse($this->designationService->datatable($this->filters($request)), 'Designations retrieved.');
    }

    public function store(SaveDesignationRequest $request): JsonResponse
    {
        return $this->successResponse(new DesignationResource($this->designationService->create($request->validated())), 'Designation created.', 201);
    }

    public function show(Designation $designation): JsonResponse
    {
        return $this->successResponse(new DesignationResource($designation->load(['department:id,name', 'grade:id,name'])->loadCount('employees')), 'Designation retrieved.');
    }

    public function update(SaveDesignationRequest $request, Designation $designation): JsonResponse
    {
        return $this->successResponse(new DesignationResource($this->designationService->update($designation, $request->validated())), 'Designation updated.');
    }

    public function destroy(Designation $designation): JsonResponse
    {
        return $this->deleteWithApproval($designation, 'Designation deleted.');
    }

    /**
     * @return array{is_active: string|null, department_id: int|null}
     */
    private function filters(Request $request): array
    {
        return [
            'is_active' => is_scalar($request->input('is_active')) ? (string) $request->input('is_active') : null,
            'department_id' => $request->integer('department_id') ?: null,
        ];
    }
}
