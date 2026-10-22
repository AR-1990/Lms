<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\CopyCurriculumRequest;
use App\Http\Requests\Academics\SaveCurriculumRequest;
use App\Http\Resources\CurriculumResource;
use App\Models\ClassSubject;
use App\Services\CurriculumService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurriculumController extends Controller
{
    use ApiResponse;

    public function __construct(private CurriculumService $curriculumService) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedResponse($this->curriculumService->paginate($this->filters($request), $request->integer('per_page', 15)), CurriculumResource::class, 'Curriculum retrieved.');
    }

    public function datatable(Request $request): JsonResponse
    {
        return $this->successResponse($this->curriculumService->datatable($this->filters($request)), 'Curriculum retrieved.');
    }

    public function store(SaveCurriculumRequest $request): JsonResponse
    {
        return $this->successResponse(new CurriculumResource($this->curriculumService->create($request->validated())), 'Subject added to the class.', 201);
    }

    public function show(ClassSubject $curriculum): JsonResponse
    {
        return $this->successResponse(new CurriculumResource($this->curriculumService->find($curriculum)), 'Curriculum row retrieved.');
    }

    public function update(SaveCurriculumRequest $request, ClassSubject $curriculum): JsonResponse
    {
        return $this->successResponse(new CurriculumResource($this->curriculumService->update($curriculum, $request->validated())), 'Curriculum updated.');
    }

    public function destroy(ClassSubject $curriculum): JsonResponse
    {
        $this->curriculumService->delete($curriculum);

        return $this->successResponse(null, 'Subject removed from the class.');
    }

    /**
     * Start a new session with last session's curriculum.
     */
    public function copy(CopyCurriculumRequest $request): JsonResponse
    {
        $copied = $this->curriculumService->copy($request->integer('from_session_id'), $request->integer('to_session_id'));

        return $this->successResponse(['copied' => $copied], $copied === 0 ? 'Nothing new to copy.' : "{$copied} curriculum rows copied.");
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
