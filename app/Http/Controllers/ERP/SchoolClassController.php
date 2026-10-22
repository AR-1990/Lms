<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\SaveSchoolClassRequest;
use App\Http\Resources\SchoolClassResource;
use App\Models\SchoolClass;
use App\Services\SchoolClassService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolClassController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private SchoolClassService $classService) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedResponse($this->classService->paginate($this->filters($request), $request->integer('per_page', 15)), SchoolClassResource::class, 'Classes retrieved.');
    }

    public function datatable(Request $request): JsonResponse
    {
        return $this->successResponse($this->classService->datatable($this->filters($request)), 'Classes retrieved.');
    }

    public function store(SaveSchoolClassRequest $request): JsonResponse
    {
        return $this->successResponse(new SchoolClassResource($this->classService->create($request->validated())), 'Class created.', 201);
    }

    public function show(SchoolClass $class): JsonResponse
    {
        return $this->successResponse(new SchoolClassResource($class->load(['wing:id,name', 'nextClass:id,name'])->loadCount('sections')), 'Class retrieved.');
    }

    public function update(SaveSchoolClassRequest $request, SchoolClass $class): JsonResponse
    {
        return $this->successResponse(new SchoolClassResource($this->classService->update($class, $request->validated())), 'Class updated.');
    }

    public function destroy(SchoolClass $class): JsonResponse
    {
        return $this->deleteWithApproval($class, 'Class deleted.');
    }

    /**
     * @return array{is_active: string|null}
     */
    private function filters(Request $request): array
    {
        return ['is_active' => is_scalar($request->input('is_active')) ? (string) $request->input('is_active') : null];
    }
}
