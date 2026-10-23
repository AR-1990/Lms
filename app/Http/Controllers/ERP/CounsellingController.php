<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Counselling\SaveCounsellingNoteRequest;
use App\Models\CounsellingNote;
use App\Services\AssetService;
use App\Services\CounsellingService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Counselling API (manage-counselling): sessions, follow-ups and a student's history. Parents and students
 * read the non-confidential sessions through the performance tracker.
 */
class CounsellingController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private CounsellingService $counsellingService) {}

    public function datatable(Request $request): JsonResponse
    {
        $filters = $request->validate(['concern_id' => ['nullable', 'integer'], 'student_id' => ['nullable', 'integer'], 'follow_up' => ['nullable', Rule::in(['due', 'upcoming'])]]);

        return $this->successResponse($this->counsellingService->datatable($filters), 'Sessions retrieved.');
    }

    public function show(CounsellingNote $counsellingNote): JsonResponse
    {
        return $this->successResponse($this->counsellingService->details($counsellingNote), 'Session retrieved.');
    }

    public function store(SaveCounsellingNoteRequest $request): JsonResponse
    {
        $note = $this->counsellingService->save(null, $request->validated(), $request->user());

        return $this->successResponse($this->counsellingService->details($note), 'Session recorded.', 201);
    }

    public function update(SaveCounsellingNoteRequest $request, CounsellingNote $counsellingNote): JsonResponse
    {
        $note = $this->counsellingService->save($counsellingNote, $request->validated(), $request->user());

        return $this->successResponse($this->counsellingService->details($note), 'Session saved.');
    }

    public function destroy(CounsellingNote $counsellingNote): JsonResponse
    {
        return $this->deleteWithApproval($counsellingNote, 'Session deleted.');
    }

    public function completeFollowUp(Request $request, CounsellingNote $counsellingNote): JsonResponse
    {
        $note = $this->counsellingService->completeFollowUp($counsellingNote, $request->user());

        return $this->successResponse($this->counsellingService->details($note), 'Follow-up marked done.');
    }

    /**
     * Counsellors for the picker and the student search.
     */
    public function counsellors(Request $request): JsonResponse
    {
        return $this->successResponse($this->counsellingService->counsellors($request->user()), 'Counsellors retrieved.');
    }

    public function students(Request $request, AssetService $assetService): JsonResponse
    {
        $term = (string) ($request->validate(['term' => ['nullable', 'string', 'max:100']])['term'] ?? '');

        return $this->successResponse($assetService->holders('student', $term), 'Students found.');
    }
}
