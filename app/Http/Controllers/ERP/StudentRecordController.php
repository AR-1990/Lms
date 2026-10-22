<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Students\SaveGuardianRequest;
use App\Http\Requests\Students\SaveStudentRequest;
use App\Http\Resources\StudentResource;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Student;
use App\Services\StudentRecordService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Student records for staff (the student portal API lives in StudentController).
 */
class StudentRecordController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private StudentRecordService $studentService) {}

    public function index(Request $request): JsonResponse
    {
        $students = $this->studentService->query($this->filters($request))->orderBy('classes.level_order')->orderBy('sections.name')->orderBy('students.first_name')->paginate(min(max($request->integer('per_page', 15), 1), 100));

        return $this->paginatedResponse($students, StudentResource::class, 'Students retrieved.');
    }

    public function datatable(Request $request): JsonResponse
    {
        return $this->successResponse($this->studentService->datatable($this->filters($request)), 'Students retrieved.');
    }

    public function store(SaveStudentRequest $request): JsonResponse
    {
        $student = $this->studentService->create($request->validated());

        return $this->successResponse(new StudentResource($student), "{$student->fullName()} added as {$student->admission_no}.", 201);
    }

    public function show(Student $student): JsonResponse
    {
        return $this->successResponse(new StudentResource($this->studentService->find($student)), 'Student retrieved.');
    }

    public function update(SaveStudentRequest $request, Student $student): JsonResponse
    {
        return $this->successResponse(new StudentResource($this->studentService->update($student, $request->validated())), 'Student updated.');
    }

    public function destroy(Student $student): JsonResponse
    {
        return $this->deleteWithApproval($student, 'Student deleted.');
    }

    public function changeStatus(Request $request, Student $student): JsonResponse
    {
        abort_unless($request->user()->hasPermission('manage-students'), 403);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(Student::STATUSES)],
            'effective_on' => ['required', 'date_format:Y-m-d'],
            'reason_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'withdrawal_reason')->whereNull('deleted_at')],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $updated = $this->studentService->changeStatus($student, $validated['status'], $validated['effective_on'], $validated['reason_id'] ?? null, $validated['remarks'] ?? null);

        return $this->successResponse(new StudentResource($updated), 'Status changed to '.$updated->current_status.'.');
    }

    public function updateEnrollment(Request $request, Enrollment $enrollment): JsonResponse
    {
        $validated = $request->validate([
            'class_id' => ['required', 'integer', Rule::exists('classes', 'id')->whereNull('deleted_at')],
            'section_id' => ['nullable', 'integer', Rule::exists('sections', 'id')->whereNull('deleted_at')],
            'roll_no' => ['nullable', 'string', 'max:20'],
        ]);

        $this->studentService->updateEnrollment($enrollment, $validated);

        return $this->successResponse(new StudentResource($this->studentService->find($enrollment->student)), 'Class and section updated.');
    }

    /* ---------- Guardians ---------- */

    public function guardians(Request $request): JsonResponse
    {
        $search = is_string($request->input('search')) ? trim($request->input('search')) : '';

        $guardians = Guardian::query()
            ->withCount('students')
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")->orWhere('cnic', 'like', "%{$search}%")))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'cnic', 'phone', 'email']);

        return $this->successResponse($guardians, 'Guardians retrieved.');
    }

    public function attachGuardian(SaveGuardianRequest $request, Student $student): JsonResponse
    {
        $this->studentService->attachGuardian($student, $request->validated());

        return $this->successResponse(new StudentResource($this->studentService->find($student)), 'Guardian linked.', 201);
    }

    public function updateGuardian(SaveGuardianRequest $request, Guardian $guardian): JsonResponse
    {
        $this->studentService->updateGuardian($guardian, $request->validated());

        return $this->successResponse($guardian->refresh()->only(['id', 'name', 'cnic', 'phone', 'email']), 'Guardian updated.');
    }

    public function detachGuardian(Student $student, Guardian $guardian): JsonResponse
    {
        $this->studentService->detachGuardian($student, $guardian);

        return $this->successResponse(new StudentResource($this->studentService->find($student)), 'Guardian unlinked.');
    }

    public function parentLogin(Request $request, Guardian $guardian): JsonResponse
    {
        $validated = $request->validate(['password' => ['required', 'string', 'min:8']]);

        $this->studentService->createParentLogin($guardian, $validated['password']);

        return $this->successResponse(['guardian_id' => $guardian->id, 'login' => $guardian->user()->value('phone')], 'Parent login created. They sign in with their phone number.', 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $search = $request->input('search');

        return [
            'academic_session_id' => $request->integer('academic_session_id') ?: null,
            'class_id' => $request->integer('class_id') ?: null,
            'section_id' => $request->integer('section_id') ?: null,
            'status' => is_string($request->input('status')) ? ($request->input('status') ?: null) : null,
            'search' => is_string($search) ? (trim($search) ?: null) : null,
        ];
    }
}
