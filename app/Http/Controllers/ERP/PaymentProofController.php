<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\SubmitPaymentProofRequest;
use App\Models\PaymentProof;
use App\Models\Student;
use App\Services\PaymentProofService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Online payments by screenshot: families send them (web portal and mobile app); fee staff approve or reject.
 */
class PaymentProofController extends Controller
{
    use ApiResponse;

    public function __construct(private PaymentProofService $paymentProofService) {}

    public function index(Request $request, Student $student): JsonResponse
    {
        return $this->successResponse($this->paymentProofService->forStudent($student, $request->user()), 'Online payments retrieved.');
    }

    public function store(SubmitPaymentProofRequest $request, Student $student): JsonResponse
    {
        $proof = $this->paymentProofService->submit($student, $request->safe()->except('screenshot'), $request->file('screenshot'), $request->user());

        return $this->successResponse($this->paymentProofService->present($proof->load(['paymentMethod:id,name', 'invoice:id,invoice_no'])), 'Sent. The school will check it and send you the receipt.', 201);
    }

    public function datatable(Request $request): JsonResponse
    {
        $status = $request->validate(['status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])]])['status'] ?? null;

        return $this->successResponse($this->paymentProofService->datatable(['status' => $status]), 'Online payments retrieved.');
    }

    public function approve(Request $request, PaymentProof $paymentProof): JsonResponse
    {
        $receivedOn = $request->validate(['received_on' => ['nullable', 'date', 'before_or_equal:today']])['received_on'] ?? null;
        $proof = $this->paymentProofService->approve($paymentProof, $receivedOn);

        return $this->successResponse($this->paymentProofService->present($proof), "Approved. Receipt {$proof->payment?->receipt_no} made and the family has been told.");
    }

    public function reject(Request $request, PaymentProof $paymentProof): JsonResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'];

        return $this->successResponse($this->paymentProofService->present($this->paymentProofService->reject($paymentProof, $reason)), 'Rejected; the family has been told why.');
    }

    public function screenshot(Request $request, PaymentProof $paymentProof): Response
    {
        return $this->paymentProofService->screenshot($paymentProof, $request->user());
    }
}
