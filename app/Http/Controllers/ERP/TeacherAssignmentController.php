<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\SaveTeacherAssignmentRequest;
use App\Http\Resources\TeacherAssignmentResource;
use App\Models\TeacherAssignment;
use App\Services\TeacherAssignmentService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherAssignmentController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private TeacherAssignmentService $assignmentService) {}

    public function index(Request $request): JsonResponse
    {
        $assignments = $this->assignmentService->query($this->filters($request))->orderBy('classes.level_order')->orderBy('sections.name')->paginate(min(max($request->integer('per_page', 50), 1), 200));

        return $this->paginatedResponse($assignments, TeacherAssignmentResource::class, 'Teacher assignments retrieved.');
    }

    public function datatable(Request $request): JsonResponse
    {
        return $this->successResponse($this->assignmentService->datatable($this->filters($request)), 'Teacher assignments retrieved.');
    }

    public function store(SaveTeacherAssignmentRequest $request): JsonResponse
    {
        return $this->successResponse(new TeacherAssignmentResource($this->assignmentService->create($request->validated())), 'Teacher assigned.', 201);
    }

    public function show(TeacherAssignment $teacherAssignment): JsonResponse
    {
        return $this->successResponse(new TeacherAssignmentResource($this->assignmentService->find($teacherAssignment)), 'Teacher assignment retrieved.');
    }

    public function update(SaveTeacherAssignmentRequest $request, TeacherAssignment $teacherAssignment): JsonResponse
    {
        return $this->successResponse(new TeacherAssignmentResource($this->assignmentService->update($teacherAssignment, $request->validated())), 'Assignment updated.');
    }

    public function destroy(TeacherAssignment $teacherAssignment): JsonResponse
    {
        return $this->deleteWithApproval($teacherAssignment, 'Assignment removed.');
    }

    /**
     * @return array{academic_session_id: int|null, section_id: int|null, employee_id: int|null}
     */
    private function filters(Request $request): array
    {
        return [
            'academic_session_id' => $request->integer('academic_session_id') ?: null,
            'section_id' => $request->integer('section_id') ?: null,
            'employee_id' => $request->integer('employee_id') ?: null,
        ];
    }
}
