<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\CopyCurriculumRequest;
use App\Http\Requests\Students\PromoteStudentsRequest;
use App\Http\Requests\Students\PromotionClassRequest;
use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Services\PromotionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Year-end promotion wizard: summary, one class's students, preparing the new session and moving students.
 */
class PromotionController extends Controller
{
    use ApiResponse;

    public function __construct(private PromotionService $promotionService) {}

    public function index(Request $request): JsonResponse
    {
        [$from, $to] = $this->sessions($request);

        return $this->successResponse($this->promotionService->summary($from, $to), 'Promotion summary retrieved.');
    }

    public function students(Request $request): JsonResponse
    {
        [$from, $to] = $this->sessions($request);
        $request->validate(['class_id' => ['required', 'integer', Rule::exists('classes', 'id')->whereNull('deleted_at')]]);

        return $this->successResponse($this->promotionService->students($from, $to, SchoolClass::findOrFail($request->integer('class_id'))), 'Students retrieved.');
    }

    public function prepare(CopyCurriculumRequest $request): JsonResponse
    {
        $result = $this->promotionService->prepare(
            AcademicSession::findOrFail($request->integer('from_session_id')),
            AcademicSession::findOrFail($request->integer('to_session_id')),
            $request->user(),
        );

        return $this->successResponse($result, 'New session prepared.');
    }

    public function store(PromoteStudentsRequest $request): JsonResponse
    {
        $result = $this->promotionService->promote(
            AcademicSession::findOrFail($request->integer('from_session_id')),
            AcademicSession::findOrFail($request->integer('to_session_id')),
            SchoolClass::findOrFail($request->integer('class_id')),
            $request->validated('students'),
            $request->boolean('carry_concessions', true),
            $request->boolean('carry_transport', true),
        );

        return $this->successResponse($result, 'Students moved to the new session.');
    }

    public function undo(PromotionClassRequest $request): JsonResponse
    {
        $count = $this->promotionService->undo(
            AcademicSession::findOrFail($request->integer('from_session_id')),
            AcademicSession::findOrFail($request->integer('to_session_id')),
            SchoolClass::findOrFail($request->integer('class_id')),
        );

        return $this->successResponse(['students' => $count], $count.' student(s) are back in their old class.');
    }

    public function rollNumbers(PromotionClassRequest $request): JsonResponse
    {
        $count = $this->promotionService->assignRollNumbers(
            AcademicSession::findOrFail($request->integer('to_session_id')),
            SchoolClass::findOrFail($request->integer('class_id')),
            $request->string('order')->toString(),
        );

        return $this->successResponse(['students' => $count], 'Roll numbers given to '.$count.' student(s).');
    }

    /**
     * @return array{0: AcademicSession, 1: AcademicSession}
     */
    private function sessions(Request $request): array
    {
        $rule = ['required', 'integer', Rule::exists('academic_sessions', 'id')->whereNull('deleted_at')];
        $request->validate(['from_session_id' => $rule, 'to_session_id' => $rule]);

        return [AcademicSession::findOrFail($request->integer('from_session_id')), AcademicSession::findOrFail($request->integer('to_session_id'))];
    }
}
