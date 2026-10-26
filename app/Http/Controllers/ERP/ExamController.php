<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\SaveExamRequest;
use App\Http\Requests\Exams\SaveExamSetupRequest;
use App\Http\Resources\ExamResource;
use App\Models\AdmitCard;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Student;
use App\Services\ExamService;
use App\Services\ExamSetupService;
use App\Services\ResultService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exam setup lists, exams, date sheets, exam fees, admit cards, seating and results.
 * PDF endpoints (admit cards, report cards) stream files; everything else uses the envelope.
 */
class ExamController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(
        private ExamService $examService,
        private ExamSetupService $setupService,
        private ResultService $resultService,
    ) {}

    /* ---------- Setup: terms, scales, halls ---------- */

    public function setupIndex(Request $request, string $type): JsonResponse
    {
        $this->setupService->modelFor($type);

        return $this->successResponse($this->setupService->datatable($type, $request->integer('academic_session_id') ?: null), 'Retrieved.');
    }

    public function setupStore(SaveExamSetupRequest $request, string $type): JsonResponse
    {
        return $this->successResponse($this->setupService->save($type, null, $request->validated())->toArray(), 'Saved.', 201);
    }

    public function setupUpdate(SaveExamSetupRequest $request, string $type, int $id): JsonResponse
    {
        return $this->successResponse($this->setupService->save($type, $this->setupService->find($type, $id), $request->validated())->toArray(), 'Updated.');
    }

    public function setupDestroy(string $type, int $id): JsonResponse
    {
        return $this->deleteWithApproval($this->setupService->find($type, $id), 'Deleted.');
    }

    /* ---------- Exams ---------- */

    public function index(Request $request): JsonResponse
    {
        $exams = $this->examService->query($this->filters($request))->latest('id')->paginate(min(max($request->integer('per_page', 15), 1), 100));

        return $this->paginatedResponse($exams, ExamResource::class, 'Exams retrieved.');
    }

    public function datatable(Request $request): JsonResponse
    {
        return $this->successResponse($this->examService->datatable($this->filters($request)), 'Exams retrieved.');
    }

    public function show(Exam $exam): JsonResponse
    {
        return $this->successResponse(new ExamResource($this->examService->find($exam)), 'Exam retrieved.');
    }

    public function store(SaveExamRequest $request): JsonResponse
    {
        return $this->successResponse(new ExamResource($this->examService->save(null, $request->validated())), 'Exam created.', 201);
    }

    public function update(SaveExamRequest $request, Exam $exam): JsonResponse
    {
        return $this->successResponse(new ExamResource($this->examService->save($exam, $request->validated())), 'Exam updated.');
    }

    public function destroy(Exam $exam): JsonResponse
    {
        return $this->deleteWithApproval($exam, 'Exam deleted.');
    }

    public function dateSheet(Request $request, Exam $exam): JsonResponse
    {
        $papers = $request->validate([
            'papers' => ['required', 'array', 'min:1', 'max:30'],
            'papers.*.subject_id' => ['required', 'integer', 'distinct', Rule::exists('subjects', 'id')->whereNull('deleted_at')],
            'papers.*.exam_date' => ['nullable', 'date_format:Y-m-d'],
            'papers.*.starts_at' => ['nullable', 'date_format:H:i'],
            'papers.*.duration_minutes' => ['nullable', 'integer', 'min:5', 'max:600'],
            'papers.*.theory_max_marks' => ['required', 'integer', 'min:0', 'max:1000'],
            'papers.*.practical_max_marks' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'papers.*.pass_marks' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'papers.*.syllabus' => ['nullable', 'string', 'max:2000'],
        ])['papers'];

        $papers = array_map(fn (array $paper): array => [...$paper, 'practical_max_marks' => $paper['practical_max_marks'] ?? 0], $papers);

        return $this->successResponse(new ExamResource($this->examService->saveDateSheet($exam, $papers)), 'Date sheet saved.');
    }

    public function changeStatus(Request $request, Exam $exam): JsonResponse
    {
        $status = $request->validate(['status' => ['required', Rule::in(Exam::STATUSES)]])['status'];

        return $this->successResponse(new ExamResource($this->examService->changeStatus($exam, $status)), 'Exam moved to '.str_replace('_', ' ', $status).'.');
    }

    public function feeChallans(Exam $exam): JsonResponse
    {
        $result = $this->examService->generateFeeChallans($exam);

        return $this->successResponse($result, "{$result['issued']} exam fee challan(s) issued".($result['skipped'] ? ", {$result['skipped']} already had one." : '.'));
    }

    public function issueAdmitCards(Exam $exam): JsonResponse
    {
        $counts = $this->examService->issueAdmitCards($exam);

        return $this->successResponse($counts, "{$counts['eligible']} admit card(s) ready".($counts['ineligible'] ? ", {$counts['ineligible']} held back (fees or attendance)." : '.'));
    }

    public function admitCards(Exam $exam): JsonResponse
    {
        return $this->successResponse($this->examService->admitCards($exam)->map(fn (AdmitCard $card): array => [
            'id' => $card->id,
            'exam_roll_no' => $card->exam_roll_no,
            'student' => $card->student?->fullName(),
            'admission_no' => $card->student?->admission_no,
            'section' => $card->enrollment?->section?->name,
            'is_eligible' => $card->is_eligible,
            'ineligible_reason' => $card->ineligible_reason,
            'override_reason' => $card->override_reason,
            'printed_count' => $card->printed_count,
        ])->all(), 'Admit cards retrieved.');
    }

    public function overrideAdmitCard(Request $request, AdmitCard $admitCard): JsonResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']])['reason'];
        $this->examService->overrideAdmitCard($admitCard, $reason, $request->user());

        return $this->successResponse(['id' => $admitCard->id, 'is_eligible' => true], 'Admit card allowed.');
    }

    public function admitCardsPdf(Request $request, Exam $exam): Response
    {
        return $this->examService->admitCardsPdf($exam, $request->integer('section_id') ?: null);
    }

    public function generateSeating(Request $request, ExamSubject $examSubject): JsonResponse
    {
        $hallIds = $request->validate(['hall_ids' => ['required', 'array', 'min:1'], 'hall_ids.*' => ['integer', Rule::exists('exam_halls', 'id')->whereNull('deleted_at')]])['hall_ids'];
        $result = $this->examService->generateSeating($examSubject, $hallIds);

        return $this->successResponse($result, "{$result['seated']} students seated in {$result['halls']} hall(s).");
    }

    public function seating(ExamSubject $examSubject): JsonResponse
    {
        return $this->successResponse($this->examService->seating($examSubject), 'Seating plan retrieved.');
    }

    /* ---------- Results ---------- */

    public function tabulation(Request $request, Exam $exam): JsonResponse
    {
        return $this->successResponse($this->resultService->tabulation($exam, $request->integer('section_id') ?: null), 'Tabulation retrieved.');
    }

    public function publish(Request $request, Exam $exam): JsonResponse
    {
        $exam = $this->resultService->publish($exam, $request->user());

        return $this->successResponse(['id' => $exam->id, 'status' => $exam->status, 'published_at' => $exam->published_at?->toIso8601String()], 'Results published to students and parents.');
    }

    /**
     * Remarks on published result cards (class teacher's; the principal's for the exam cell).
     */
    public function remarks(Request $request, Exam $exam): JsonResponse
    {
        return $this->successResponse($this->resultService->remarks($exam, $request->integer('section_id') ?: null, $request->user()), 'Result cards retrieved.');
    }

    public function saveRemarks(Request $request, Exam $exam): JsonResponse
    {
        $rows = $request->validate([
            'rows' => ['required', 'array', 'max:300'],
            'rows.*.id' => ['required', 'integer'],
            'rows.*.class_teacher_remarks' => ['nullable', 'string', 'max:500'],
            'rows.*.principal_remarks' => ['nullable', 'string', 'max:500'],
        ])['rows'];

        return $this->successResponse(['saved' => $this->resultService->saveRemarks($exam, $rows, $request->user())], 'Remarks saved.');
    }

    public function reportCardsPdf(Request $request, Exam $exam): Response
    {
        return $this->resultService->reportCardsPdf($exam, $request->integer('section_id') ?: null);
    }

    /**
     * One child's admit card (when eligible) or published report card, for exam staff or the child's family.
     */
    public function studentPdf(Request $request, Exam $exam, Student $student, string $document): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermission('manage-exams') || in_array($student->id, Student::ownIdsFor($user), true), 404);
        abort_if($document === 'report-card' && $exam->status !== 'published', 404, 'This result is not published yet.');

        return $document === 'report-card'
            ? $this->resultService->reportCardsPdf($exam, null, $student->id)
            : $this->examService->admitCardsPdf($exam, null, $student->id);
    }

    /**
     * Published results of one student — staff, the student, or their parent.
     */
    public function studentResults(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermission(['manage-exams', 'submit-grades']) || in_array($student->id, Student::ownIdsFor($user), true), 404);

        return $this->successResponse($this->resultService->forStudent($student), 'Results retrieved.');
    }

    /**
     * @return array{exam_term_id: int|null, class_id: int|null, status: string|null}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'exam_term_id' => ['nullable', 'integer'],
            'class_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(Exam::STATUSES)],
        ]);

        return [
            'exam_term_id' => isset($validated['exam_term_id']) ? (int) $validated['exam_term_id'] : null,
            'class_id' => isset($validated['class_id']) ? (int) $validated['class_id'] : null,
            'status' => $validated['status'] ?? null,
        ];
    }
}
