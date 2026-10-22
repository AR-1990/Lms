<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\ReceivePaymentRequest;
use App\Http\Resources\FeePaymentResource;
use App\Models\FeePayment;
use App\Services\FeePaymentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeePaymentController extends Controller
{
    use ApiResponse;

    public function __construct(private FeePaymentService $paymentService) {}

    public function index(Request $request): JsonResponse
    {
        $payments = $this->paymentService->query($this->filters($request))->latest('received_on')->latest('id')->paginate(min(max($request->integer('per_page', 15), 1), 100));

        return $this->paginatedResponse($payments, FeePaymentResource::class, 'Receipts retrieved.');
    }

    public function datatable(Request $request): JsonResponse
    {
        return $this->successResponse($this->paymentService->datatable($this->filters($request)), 'Receipts retrieved.');
    }

    public function store(ReceivePaymentRequest $request): JsonResponse
    {
        $payment = $this->paymentService->receive($request->validated());

        return $this->successResponse(new FeePaymentResource($payment), "Receipt {$payment->receipt_no} posted.", 201);
    }

    public function show(FeePayment $feePayment): JsonResponse
    {
        return $this->successResponse(new FeePaymentResource($this->paymentService->find($feePayment)), 'Receipt retrieved.');
    }

    public function reverse(Request $request, FeePayment $feePayment): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:200'],
            'cheque_bounced' => ['sometimes', 'boolean'],
        ], [], ['reason' => 'narration']);

        $payment = $this->paymentService->reverse($feePayment, $validated['reason'], (bool) ($validated['cheque_bounced'] ?? false));

        return $this->successResponse(new FeePaymentResource($payment), "Receipt {$payment->receipt_no} reversed.");
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'student_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['posted', 'reversed'])],
            'payment_method_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return [
            'student_id' => isset($validated['student_id']) ? (int) $validated['student_id'] : null,
            'status' => $validated['status'] ?? null,
            'payment_method_id' => isset($validated['payment_method_id']) ? (int) $validated['payment_method_id'] : null,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
        ];
    }
}
