<?php

namespace App\Traits;

use App\Http\Resources\ApprovalRequestResource;
use App\Services\ApprovalService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

/**
 * Every API delete goes through admin approval: approvers delete at once (200),
 * everyone else gets 202 with the pending request. Clients send `reason` in the body.
 * Used next to the ApiResponse trait.
 */
trait DeletesWithApproval
{
    protected function deleteWithApproval(Model $record, string $deletedMessage): JsonResponse
    {
        $reason = request()->validate(['reason' => ['nullable', 'string', 'max:500']])['reason'] ?? null;
        $approval = app(ApprovalService::class)->requestDelete($record, $reason, request()->user());

        return $approval->status === 'approved'
            ? $this->successResponse(null, $deletedMessage)
            : $this->successResponse(new ApprovalRequestResource($approval), 'Sent to an administrator for approval.', 202);
    }
}
