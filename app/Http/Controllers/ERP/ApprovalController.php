<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApprovalRequestResource;
use App\Models\ApprovalRequest;
use App\Services\ApprovalService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Deletion and purchase approvals. Anyone signed in sees their own requests;
 * approvers see and decide all of them.
 */
class ApprovalController extends Controller
{
    use ApiResponse;

    public function __construct(private ApprovalService $approvalService) {}

    public function index(Request $request): JsonResponse
    {
        $approvals = $this->approvalService->query($request->user(), $this->filters($request))->latest('id')->paginate(min(max($request->integer('per_page', 15), 1), 100));

        return $this->paginatedResponse($approvals, ApprovalRequestResource::class, 'Requests retrieved.');
    }

    public function datatable(Request $request): JsonResponse
    {
        return $this->successResponse($this->approvalService->datatable($request->user(), $this->filters($request)), 'Requests retrieved.');
    }

    public function approve(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        $note = $request->validate(['decision_note' => ['nullable', 'string', 'max:1000']])['decision_note'] ?? null;

        return $this->successResponse(new ApprovalRequestResource($this->approvalService->decide($approvalRequest, true, $note, $request->user())), 'Request approved.');
    }

    public function reject(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        $note = $request->validate(['decision_note' => ['required', 'string', 'max:1000']])['decision_note'];

        return $this->successResponse(new ApprovalRequestResource($this->approvalService->decide($approvalRequest, false, $note, $request->user())), 'Request rejected.');
    }

    /**
     * @return array{status: string|null, request_type: string|null, module: string|null}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(ApprovalRequest::STATUSES)],
            'request_type' => ['nullable', Rule::in(ApprovalRequest::TYPES)],
            'module' => ['nullable', 'string', 'max:50'],
        ]);

        return [
            'status' => $validated['status'] ?? null,
            'request_type' => $validated['request_type'] ?? null,
            'module' => $validated['module'] ?? null,
        ];
    }
}
