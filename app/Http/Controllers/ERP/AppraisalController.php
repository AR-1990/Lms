<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Appraisals\SaveCriterionRequest;
use App\Http\Requests\Appraisals\SaveCycleRequest;
use App\Http\Requests\Appraisals\SaveReviewRequest;
use App\Models\Appraisal;
use App\Models\AppraisalCriterion;
use App\Models\AppraisalCycle;
use App\Models\Department;
use App\Services\AppraisalService;
use App\Services\AssetService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Staff appraisals API. HR (manage-appraisals) runs cycles and criteria; any staff member works on the
 * appraisals where they are the employee or the reviewer (checked in AppraisalService::roleOf).
 */
class AppraisalController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private AppraisalService $appraisalService) {}

    public function criteriaDatatable(): JsonResponse
    {
        return $this->successResponse($this->appraisalService->criteriaDatatable(), 'Criteria retrieved.');
    }

    public function storeCriterion(SaveCriterionRequest $request): JsonResponse
    {
        return $this->successResponse($this->appraisalService->saveCriterion(null, $request->validated()), 'Criterion added.', 201);
    }

    public function updateCriterion(SaveCriterionRequest $request, AppraisalCriterion $appraisalCriterion): JsonResponse
    {
        return $this->successResponse($this->appraisalService->saveCriterion($appraisalCriterion, $request->validated()), 'Criterion saved.');
    }

    public function destroyCriterion(AppraisalCriterion $appraisalCriterion): JsonResponse
    {
        return $this->deleteWithApproval($appraisalCriterion, 'Criterion deleted.');
    }

    public function cyclesDatatable(): JsonResponse
    {
        return $this->successResponse($this->appraisalService->cyclesDatatable(), 'Cycles retrieved.');
    }

    /**
     * Cycles and departments for the screen's pickers.
     */
    public function options(): JsonResponse
    {
        return $this->successResponse([
            'cycles' => AppraisalCycle::latest('id')->get(['id', 'name', 'status']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
        ], 'Options retrieved.');
    }

    public function showCycle(AppraisalCycle $appraisalCycle): JsonResponse
    {
        return $this->successResponse($this->appraisalService->summary($appraisalCycle), 'Cycle retrieved.');
    }

    public function storeCycle(SaveCycleRequest $request): JsonResponse
    {
        return $this->successResponse($this->appraisalService->saveCycle(null, $request->validated()), 'Cycle added.', 201);
    }

    public function updateCycle(SaveCycleRequest $request, AppraisalCycle $appraisalCycle): JsonResponse
    {
        return $this->successResponse($this->appraisalService->saveCycle($appraisalCycle, $request->validated()), 'Cycle saved.');
    }

    public function destroyCycle(AppraisalCycle $appraisalCycle): JsonResponse
    {
        return $this->deleteWithApproval($appraisalCycle, 'Cycle deleted.');
    }

    public function launch(Request $request, AppraisalCycle $appraisalCycle): JsonResponse
    {
        $count = $this->appraisalService->launch($appraisalCycle, $request->user());

        return $this->successResponse($this->appraisalService->summary($appraisalCycle->refresh()), $count > 0 ? "Appraisals started for {$count} staff." : 'Everyone already has an appraisal in this cycle.');
    }

    public function close(Request $request, AppraisalCycle $appraisalCycle): JsonResponse
    {
        return $this->successResponse($this->appraisalService->close($appraisalCycle, $request->user()), 'Cycle closed.');
    }

    public function appraisalsDatatable(Request $request): JsonResponse
    {
        $filters = $request->validate(['appraisal_cycle_id' => ['nullable', 'integer'], 'status' => ['nullable', Rule::in(Appraisal::STATUSES)]]);

        return $this->successResponse($this->appraisalService->appraisalsDatatable($filters), 'Appraisals retrieved.');
    }

    public function reviewers(Request $request, AssetService $assetService): JsonResponse
    {
        $term = (string) ($request->validate(['term' => ['nullable', 'string', 'max:100']])['term'] ?? '');

        return $this->successResponse($assetService->holders('employee', $term), 'Staff found.');
    }

    public function reassign(Request $request, Appraisal $appraisal): JsonResponse
    {
        $reviewerId = $request->validate(['reviewer_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')]])['reviewer_id'] ?? null;

        return $this->successResponse($this->appraisalService->reassign($appraisal, $reviewerId, $request->user()), 'Reviewer changed.');
    }

    public function reopen(Request $request, Appraisal $appraisal): JsonResponse
    {
        return $this->successResponse($this->appraisalService->reopen($appraisal, $request->user()), 'Appraisal reopened for the reviewer.');
    }

    /**
     * The signed-in staff member's own appraisals and the ones waiting for their review.
     */
    public function mine(Request $request): JsonResponse
    {
        return $this->successResponse($this->appraisalService->forStaff($request->user()), 'Appraisals retrieved.');
    }

    public function show(Request $request, Appraisal $appraisal): JsonResponse
    {
        $this->allowed($request, $appraisal);

        return $this->successResponse($this->appraisalService->details($appraisal, $request->user()), 'Appraisal retrieved.');
    }

    public function selfReview(SaveReviewRequest $request, Appraisal $appraisal): JsonResponse
    {
        $this->appraisalService->saveSelfReview($appraisal, $request->validated('scores', []), $request->boolean('submit'), $request->user());

        return $this->successResponse($this->appraisalService->details($appraisal, $request->user()), $request->boolean('submit') ? 'Self-review sent to your reviewer.' : 'Self-review saved.');
    }

    public function review(SaveReviewRequest $request, Appraisal $appraisal): JsonResponse
    {
        $this->appraisalService->saveReview($appraisal, $request->validated(), $request->boolean('submit'), $request->user());

        return $this->successResponse($this->appraisalService->details($appraisal, $request->user()), $request->boolean('submit') ? 'Appraisal submitted to the employee.' : 'Review saved.');
    }

    public function acknowledge(Request $request, Appraisal $appraisal): JsonResponse
    {
        $comments = $request->validate(['employee_comments' => ['nullable', 'string', 'max:5000']])['employee_comments'] ?? null;
        $this->appraisalService->acknowledge($appraisal, $comments, $request->user());

        return $this->successResponse($this->appraisalService->details($appraisal, $request->user()), 'Appraisal acknowledged.');
    }

    public function pdf(Request $request, Appraisal $appraisal): Response
    {
        $this->allowed($request, $appraisal);

        return $this->appraisalService->pdf($appraisal, $request->user());
    }

    private function allowed(Request $request, Appraisal $appraisal): void
    {
        abort_if($this->appraisalService->roleOf($appraisal, $request->user()) === null, 403, 'This appraisal is not yours to see.');
    }
}
