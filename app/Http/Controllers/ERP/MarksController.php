<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\SaveMarksRequest;
use App\Models\ExamSubject;
use App\Models\Section;
use App\Services\MarksService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarksController extends Controller
{
    use ApiResponse;

    public function __construct(private MarksService $marksService) {}

    public function sheet(Request $request): JsonResponse
    {
        [$paper, $section] = $this->paperAndSection($request);
        $this->marksService->ensureCanEnter($request->user(), $paper, $section);

        return $this->successResponse($this->marksService->sheet($paper, $section), 'Marks retrieved.');
    }

    public function store(SaveMarksRequest $request): JsonResponse
    {
        $sheet = $this->marksService->save(
            ExamSubject::findOrFail($request->validated('exam_subject_id')),
            Section::findOrFail($request->validated('section_id')),
            $request->validated('rows'),
            (bool) $request->validated('submit', false),
            $request->validated('reason'),
            $request->user(),
        );

        return $this->successResponse($sheet, $request->boolean('submit') ? 'Marks submitted for approval.' : 'Marks saved.');
    }

    public function approve(Request $request): JsonResponse
    {
        [$paper, $section] = $this->paperAndSection($request);
        $result = $this->marksService->approve($paper, $section, $request->user());

        return $this->successResponse($result, "{$result['approved']} mark(s) approved.");
    }

    /**
     * @return array{0: ExamSubject, 1: Section}
     */
    private function paperAndSection(Request $request): array
    {
        $validated = $request->validate([
            'exam_subject_id' => ['required', 'integer', Rule::exists('exam_subjects', 'id')->whereNull('deleted_at')],
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')->whereNull('deleted_at')],
        ]);

        return [ExamSubject::findOrFail($validated['exam_subject_id']), Section::findOrFail($validated['section_id'])];
    }
}
