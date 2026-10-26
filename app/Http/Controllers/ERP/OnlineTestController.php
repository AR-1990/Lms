<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\SaveOnlineTestRequest;
use App\Models\OnlineTest;
use App\Models\Student;
use App\Services\OnlineTestService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Online MCQ tests: teachers build, publish and read results; students list, attempt and review
 * their own (the same endpoints serve the web portal and the mobile app).
 */
class OnlineTestController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private OnlineTestService $onlineTestService) {}

    public function datatable(Request $request): JsonResponse
    {
        return $this->successResponse($this->onlineTestService->datatable($request->user()), 'Online tests retrieved.');
    }

    public function show(Request $request, OnlineTest $onlineTest): JsonResponse
    {
        return $this->successResponse($this->onlineTestService->find($onlineTest, $request->user()), 'Online test retrieved.');
    }

    public function store(SaveOnlineTestRequest $request): JsonResponse
    {
        return $this->successResponse($this->onlineTestService->save(null, $request->validated(), $request->user()), 'Online test saved as a draft.', 201);
    }

    public function update(SaveOnlineTestRequest $request, OnlineTest $onlineTest): JsonResponse
    {
        return $this->successResponse($this->onlineTestService->save($onlineTest, $request->validated(), $request->user()), 'Online test saved.');
    }

    public function changeStatus(Request $request, OnlineTest $onlineTest): JsonResponse
    {
        $status = $request->validate(['status' => ['required', Rule::in(OnlineTest::STATUSES)]])['status'];
        $test = $this->onlineTestService->changeStatus($onlineTest, $status, $request->user());

        return $this->successResponse($test, ['published' => 'Test published; students have been told.', 'closed' => 'Test closed.', 'draft' => 'Test moved back to draft.'][$status]);
    }

    public function results(Request $request, OnlineTest $onlineTest): JsonResponse
    {
        return $this->successResponse($this->onlineTestService->results($onlineTest, $request->user()), 'Results retrieved.');
    }

    public function destroy(Request $request, OnlineTest $onlineTest): JsonResponse
    {
        $this->onlineTestService->find($onlineTest, $request->user());

        return $this->deleteWithApproval($onlineTest, 'Online test deleted.');
    }

    /* ---------- Students ---------- */

    /**
     * The student's own tests, or with ?student_id= a parent's view of one child (scores only).
     */
    public function mine(Request $request): JsonResponse
    {
        $studentId = $request->validate(['student_id' => ['nullable', 'integer']])['student_id'] ?? null;

        return $this->successResponse($this->onlineTestService->forStudent($request->user(), $studentId ? Student::findOrFail($studentId) : null), 'Online tests.');
    }

    /**
     * Answers saved while the test runs (the app and the web page send each choice).
     */
    public function saveAnswers(Request $request, OnlineTest $onlineTest): JsonResponse
    {
        $answers = $request->validate([
            'answers' => ['required', 'array', 'max:200'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.option_id' => ['nullable', 'integer'],
        ])['answers'];

        return $this->successResponse(['saved' => $this->onlineTestService->saveAnswers($onlineTest, $request->user(), $answers)], 'Saved.');
    }

    public function start(Request $request, OnlineTest $onlineTest): JsonResponse
    {
        return $this->successResponse($this->onlineTestService->start($onlineTest, $request->user()), 'Test started. Good luck!');
    }

    public function submit(Request $request, OnlineTest $onlineTest): JsonResponse
    {
        $answers = $request->validate([
            'answers' => ['present', 'array', 'max:200'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.option_id' => ['nullable', 'integer'],
        ])['answers'];
        $result = $this->onlineTestService->submit($onlineTest, $request->user(), $answers);

        return $this->successResponse($result, $result['late'] ? 'Time was over, so answers sent after the limit were not counted.' : "Submitted. You scored {$result['score']} of {$result['total_marks']}.");
    }

    public function review(Request $request, OnlineTest $onlineTest): JsonResponse
    {
        return $this->successResponse($this->onlineTestService->review($onlineTest, null, $request->user()), 'Your answers.');
    }
}
