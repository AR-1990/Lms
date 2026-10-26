<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\SaveLateFeeRuleRequest;
use App\Models\LateFeeRule;
use App\Services\FeeAutomationService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Late fee rules and automatic monthly challans (manage-fee-setup). The nightly jobs live in routes/console.php.
 */
class FeeAutomationController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private FeeAutomationService $automationService) {}

    public function rulesDatatable(): JsonResponse
    {
        return $this->successResponse($this->automationService->rulesDatatable(), 'Late fee rules retrieved.');
    }

    public function storeRule(SaveLateFeeRuleRequest $request): JsonResponse
    {
        return $this->successResponse($this->automationService->saveRule(null, $request->validated()), 'Late fee rule added.', 201);
    }

    public function updateRule(SaveLateFeeRuleRequest $request, LateFeeRule $lateFeeRule): JsonResponse
    {
        return $this->successResponse($this->automationService->saveRule($lateFeeRule, $request->validated()), 'Late fee rule saved.');
    }

    public function destroyRule(LateFeeRule $lateFeeRule): JsonResponse
    {
        return $this->deleteWithApproval($lateFeeRule, 'Late fee rule deleted.');
    }

    public function settings(): JsonResponse
    {
        return $this->successResponse($this->automationService->settings(), 'Fee automation retrieved.');
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $data = $request->validate(['fee_auto_generate_day' => ['nullable', 'integer', 'min:1', 'max:31'], 'fee_reminder_days' => ['nullable', 'integer', 'min:1', 'max:15']]);

        return $this->successResponse($this->automationService->saveSettings(['fee_auto_generate_day' => $data['fee_auto_generate_day'] ?? null, 'fee_reminder_days' => $data['fee_reminder_days'] ?? null], $request->user()), 'Fee automation saved.');
    }
}
