<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\SaveSubjectRequest;
use App\Http\Resources\SubjectResource;
use App\Models\Subject;
use App\Services\SubjectService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private SubjectService $subjectService) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedResponse($this->subjectService->paginate($this->filters($request), $request->integer('per_page', 15)), SubjectResource::class, 'Subjects retrieved.');
    }

    public function datatable(Request $request): JsonResponse
    {
        return $this->successResponse($this->subjectService->datatable($this->filters($request)), 'Subjects retrieved.');
    }

    public function store(SaveSubjectRequest $request): JsonResponse
    {
        return $this->successResponse(new SubjectResource($this->subjectService->create($request->validated())), 'Subject created.', 201);
    }

    public function show(Subject $subject): JsonResponse
    {
        return $this->successResponse(new SubjectResource($subject->load('subjectType:id,name')->loadCount('curriculum')), 'Subject retrieved.');
    }

    public function update(SaveSubjectRequest $request, Subject $subject): JsonResponse
    {
        return $this->successResponse(new SubjectResource($this->subjectService->update($subject, $request->validated())), 'Subject updated.');
    }

    public function destroy(Subject $subject): JsonResponse
    {
        return $this->deleteWithApproval($subject, 'Subject deleted.');
    }

    /**
     * @return array{is_active: string|null}
     */
    private function filters(Request $request): array
    {
        return ['is_active' => is_scalar($request->input('is_active')) ? (string) $request->input('is_active') : null];
    }
}
