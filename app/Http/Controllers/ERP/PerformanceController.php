<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Models\ExamTerm;
use App\Models\PerformanceAlert;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentGoal;
use App\Services\PerformanceService;
use App\Services\TeacherAssignmentService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Performance tracker: section overview, one student's profile, skill ratings, goals and alerts.
 */
class PerformanceController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(
        private PerformanceService $performanceService,
        private TeacherAssignmentService $assignmentService,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        [$section, $term] = $this->sectionAndTerm($request);

        return $this->successResponse($this->performanceService->overview($section, $term), 'Performance retrieved.');
    }

    /**
     * Rebuild a term's snapshots now (also done after results are published and every night).
     */
    public function refresh(Request $request): JsonResponse
    {
        [$section, $term] = $this->sectionAndTerm($request);
        $count = $this->performanceService->compute($term, $section->id);

        return $this->successResponse(['students' => $count], "Performance updated for {$count} students.");
    }

    /**
     * One student's tracker — staff, the student, or their parent.
     */
    public function student(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermission('view-performance') || in_array($student->id, Student::ownIdsFor($user), true), 404);

        return $this->successResponse($this->performanceService->forStudent($student), 'Performance retrieved.');
    }

    public function skillSheet(Request $request): JsonResponse
    {
        [$section, $term] = $this->sectionAndTerm($request);

        return $this->successResponse($this->performanceService->skillSheet($section, $term), 'Skills retrieved.');
    }

    public function saveSkills(Request $request): JsonResponse
    {
        [$section, $term] = $this->sectionAndTerm($request);
        $rows = $request->validate([
            'rows' => ['required', 'array', 'max:2000'],
            'rows.*.enrollment_id' => ['required', 'integer'],
            'rows.*.assessment_skill_id' => ['required', 'integer', Rule::exists('assessment_skills', 'id')->whereNull('deleted_at')],
            'rows.*.rating_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'skill_rating')->whereNull('deleted_at')],
            'rows.*.comment' => ['nullable', 'string', 'max:255'],
        ])['rows'];

        $saved = $this->performanceService->saveSkills($section, $term, $rows, $request->user());

        return $this->successResponse(['saved' => $saved], 'Skill ratings saved.');
    }

    public function storeGoal(Request $request): JsonResponse
    {
        return $this->successResponse($this->performanceService->saveGoal(null, $this->validateGoal($request, true), $request->user()), 'Goal set.', 201);
    }

    public function updateGoal(Request $request, StudentGoal $studentGoal): JsonResponse
    {
        return $this->successResponse($this->performanceService->saveGoal($studentGoal, $this->validateGoal($request, false), $request->user()), 'Goal updated.');
    }

    public function destroyGoal(StudentGoal $studentGoal): JsonResponse
    {
        return $this->deleteWithApproval($studentGoal, 'Goal deleted.');
    }

    public function alerts(Request $request): JsonResponse
    {
        $status = $request->validate(['status' => ['nullable', Rule::in(PerformanceAlert::STATUSES)]])['status'] ?? null;
        $alerts = PerformanceAlert::query()
            ->with(['student:id,first_name,last_name,admission_no', 'rule:id,name,metric,operator,threshold'])
            ->when($status, fn ($query, string $value) => $query->where('status', $value), fn ($query) => $query->where('status', '!=', 'resolved'))
            ->latest('id')
            ->paginate(min(max($request->integer('per_page', 15), 1), 100));

        return $this->successResponse([
            'items' => $alerts->getCollection()->map(fn (PerformanceAlert $alert): array => [
                'id' => $alert->id,
                'student_id' => $alert->student_id,
                'student' => $alert->student?->fullName(),
                'admission_no' => $alert->student?->admission_no,
                'rule' => $alert->rule?->name,
                'condition' => $alert->rule ? "{$alert->rule->metric} {$alert->rule->operator} {$alert->rule->threshold}" : null,
                'metric_value' => $alert->metric_value,
                'status' => $alert->status,
                'action_taken' => $alert->action_taken,
            ])->all(),
            'meta' => ['current_page' => $alerts->currentPage(), 'per_page' => $alerts->perPage(), 'total' => $alerts->total(), 'last_page' => $alerts->lastPage()],
        ], 'Alerts retrieved.');
    }

    public function updateAlert(Request $request, PerformanceAlert $performanceAlert): JsonResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::in(['acknowledged', 'resolved'])], 'action_taken' => ['nullable', 'string', 'max:2000']]);
        $alert = $this->performanceService->updateAlert($performanceAlert, $validated['status'], $validated['action_taken'] ?? null, $request->user());

        return $this->successResponse(['id' => $alert->id, 'status' => $alert->status], 'Alert '.$alert->status.'.');
    }

    /**
     * @return array{0: Section, 1: ExamTerm}
     *
     * @throws ValidationException when a teacher does not teach the section
     */
    private function sectionAndTerm(Request $request): array
    {
        $validated = $request->validate([
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')->whereNull('deleted_at')],
            'exam_term_id' => ['required', 'integer', Rule::exists('exam_terms', 'id')->whereNull('deleted_at')],
        ]);
        $section = Section::findOrFail($validated['section_id']);

        if (! $request->user()->hasPermission(['manage-exams', 'manage-students']) && ! $this->assignmentService->teaches($request->user(), $section)) {
            throw ValidationException::withMessages(['section_id' => ['You can see performance only for sections you teach.']]);
        }

        return [$section, ExamTerm::findOrFail($validated['exam_term_id'])];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateGoal(Request $request, bool $creating): array
    {
        return $request->validate([
            'student_id' => [$creating ? 'required' : 'prohibited', 'integer', Rule::exists('students', 'id')->whereNull('deleted_at')],
            'subject_id' => ['nullable', 'integer', Rule::exists('subjects', 'id')->whereNull('deleted_at')],
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'target_value' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'target_date' => ['nullable', 'date_format:Y-m-d'],
            'status' => [$creating ? 'prohibited' : 'sometimes', Rule::in(StudentGoal::STATUSES)],
            'achieved_value' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
