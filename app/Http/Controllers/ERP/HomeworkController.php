<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diary\SaveHomeworkRequest;
use App\Http\Resources\HomeworkResource;
use App\Models\Enrollment;
use App\Models\Homework;
use App\Models\Student;
use App\Services\HomeworkService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HomeworkController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private HomeworkService $homeworkService) {}

    /**
     * Staff see all homework; students and parents see their own class's homework.
     */
    public function index(Request $request): JsonResponse
    {
        $homework = $this->homeworkService->query($this->filters($request))->latest('assigned_on')->paginate(min(max($request->integer('per_page', 15), 1), 100));

        return $this->paginatedResponse($homework, HomeworkResource::class, 'Homework retrieved.');
    }

    public function datatable(Request $request): JsonResponse
    {
        return $this->successResponse($this->homeworkService->datatable($this->filters($request)), 'Homework retrieved.');
    }

    public function store(SaveHomeworkRequest $request): JsonResponse
    {
        return $this->successResponse(new HomeworkResource($this->homeworkService->save(null, $request->validated(), $request->user())), 'Homework set.', 201);
    }

    public function update(SaveHomeworkRequest $request, Homework $homework): JsonResponse
    {
        return $this->successResponse(new HomeworkResource($this->homeworkService->save($homework, $request->validated(), $request->user())), 'Homework updated.');
    }

    public function destroy(Homework $homework): JsonResponse
    {
        return $this->deleteWithApproval($homework, 'Homework deleted.');
    }

    public function submissions(Homework $homework): JsonResponse
    {
        return $this->successResponse($this->homeworkService->submissions($homework), 'Submissions retrieved.');
    }

    public function submit(Request $request, Homework $homework): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'integer', Rule::exists('students', 'id')->whereNull('deleted_at')],
            'content' => ['nullable', 'string', 'max:10000'],
        ]);
        $submission = $this->homeworkService->submit($homework, Student::findOrFail($validated['student_id']), $validated['content'] ?? null, $request->user());

        return $this->successResponse(['id' => $submission->id, 'status' => $submission->status], $submission->status === 'late' ? 'Submitted after the due date.' : 'Homework submitted.', 201);
    }

    public function grade(Request $request, Homework $homework): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'integer', Rule::exists('students', 'id')->whereNull('deleted_at')],
            'marks' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ]);
        $submission = $this->homeworkService->grade($homework, $validated, $request->user());

        return $this->successResponse(['id' => $submission->id, 'status' => $submission->status, 'marks' => $submission->marks], 'Graded.');
    }

    /**
     * @return array{section_id?: int|null, subject_id?: int|null, section_ids?: array<int, int>}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate(['section_id' => ['nullable', 'integer'], 'subject_id' => ['nullable', 'integer']]);
        $filters = ['section_id' => isset($validated['section_id']) ? (int) $validated['section_id'] : null, 'subject_id' => isset($validated['subject_id']) ? (int) $validated['subject_id'] : null];

        if (! $request->user()->hasPermission('manage-diary')) {
            $filters['section_ids'] = Enrollment::whereIn('student_id', Student::ownIdsFor($request->user()))->where('status', 'active')->pluck('section_id')->filter()->all();
        }

        return $filters;
    }
}
