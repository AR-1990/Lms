<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\SaveFeeRefundRequest;
use App\Http\Requests\Students\IssueLeavingCertificateRequest;
use App\Models\FeeRefund;
use App\Models\Student;
use App\Services\StudentLeavingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Leaving school: clearance, School Leaving Certificate and fee refunds.
 */
class StudentLeavingController extends Controller
{
    use ApiResponse;

    public function __construct(private StudentLeavingService $leavingService) {}

    public function clearance(Student $student): JsonResponse
    {
        return $this->successResponse($this->leavingService->clearance($student), 'Clearance retrieved.');
    }

    public function issueCertificate(IssueLeavingCertificateRequest $request, Student $student): JsonResponse
    {
        $student = $this->leavingService->issueCertificate($student, $request->validated());

        return $this->successResponse($this->leavingService->clearance($student), 'School Leaving Certificate '.$student->slc_no.' issued.');
    }

    public function certificatePdf(Request $request, Student $student): Response
    {
        return $this->leavingService->certificatePdf($student, $request->user());
    }

    public function adjustDeposit(Request $request, Student $student): JsonResponse
    {
        $receipt = $this->leavingService->adjustDeposit($student, $request->user());

        return $this->successResponse($this->leavingService->clearance($student), 'Rs '.number_format($receipt->amount / 100).' of the deposit paid the dues — receipt '.$receipt->receipt_no.'.');
    }

    public function refundsDatatable(): JsonResponse
    {
        return $this->successResponse($this->leavingService->refundsDatatable(), 'Refunds retrieved.');
    }

    public function refund(SaveFeeRefundRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $refund = $this->leavingService->refund(Student::findOrFail($validated['student_id']), $validated, $request->user());

        return $this->successResponse($refund->only(['id', 'refund_no', 'source', 'amount', 'status', 'journal_entry_id']), 'Refund '.$refund->refund_no.' paid.', 201);
    }

    public function reverseRefund(Request $request, FeeRefund $feeRefund): JsonResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $refund = $this->leavingService->reverseRefund($feeRefund, $validated['reason'], $request->user());

        return $this->successResponse($refund->only(['id', 'refund_no', 'status', 'reversal_reason']), 'Refund '.$refund->refund_no.' reversed.');
    }
}
