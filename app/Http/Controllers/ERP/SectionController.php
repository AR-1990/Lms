<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\SaveSectionRequest;
use App\Http\Resources\SectionResource;
use App\Models\Section;
use App\Services\SectionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    use ApiResponse;

    public function __construct(private SectionService $sectionService) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedResponse($this->sectionService->paginate($this->filters($request), $request->integer('per_page', 15)), SectionResource::class, 'Sections retrieved.');
    }

    public function datatable(Request $request): JsonResponse
    {
        return $this->successResponse($this->sectionService->datatable($this->filters($request)), 'Sections retrieved.');
    }

    public function store(SaveSectionRequest $request): JsonResponse
    {
        return $this->successResponse(new SectionResource($this->sectionService->create($request->validated())), 'Section created.', 201);
    }

    public function show(Section $section): JsonResponse
    {
        return $this->successResponse(new SectionResource($this->sectionService->find($section)), 'Section retrieved.');
    }

    public function update(SaveSectionRequest $request, Section $section): JsonResponse
    {
        return $this->successResponse(new SectionResource($this->sectionService->update($section, $request->validated())), 'Section updated.');
    }

    public function destroy(Section $section): JsonResponse
    {
        $this->sectionService->delete($section);

        return $this->successResponse(null, 'Section deleted.');
    }

    /**
     * @return array{academic_session_id: int|null, class_id: int|null}
     */
    private function filters(Request $request): array
    {
        return [
            'academic_session_id' => $request->integer('academic_session_id') ?: null,
            'class_id' => $request->integer('class_id') ?: null,
        ];
    }
}
