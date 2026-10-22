<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounts\SavePaymentMethodRequest;
use App\Http\Resources\PaymentMethodResource;
use App\Models\PaymentMethod;
use App\Services\PaymentMethodService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private PaymentMethodService $paymentMethodService) {}

    /**
     * Active methods for receipt / payment forms on web and mobile.
     */
    public function index(): JsonResponse
    {
        return $this->successResponse(PaymentMethodResource::collection($this->paymentMethodService->active())->resolve(), 'Payment methods retrieved.');
    }

    public function datatable(Request $request): JsonResponse
    {
        $isActive = is_scalar($request->input('is_active')) ? (string) $request->input('is_active') : null;

        return $this->successResponse($this->paymentMethodService->datatable(['is_active' => $isActive]), 'Payment methods retrieved.');
    }

    public function store(SavePaymentMethodRequest $request): JsonResponse
    {
        return $this->successResponse(new PaymentMethodResource($this->paymentMethodService->create($request->validated())), 'Payment method created.', 201);
    }

    public function show(PaymentMethod $paymentMethod): JsonResponse
    {
        return $this->successResponse(new PaymentMethodResource($paymentMethod->load('account:id,code,name')), 'Payment method retrieved.');
    }

    public function update(SavePaymentMethodRequest $request, PaymentMethod $paymentMethod): JsonResponse
    {
        return $this->successResponse(new PaymentMethodResource($this->paymentMethodService->update($paymentMethod, $request->validated())), 'Payment method updated.');
    }

    public function destroy(PaymentMethod $paymentMethod): JsonResponse
    {
        return $this->deleteWithApproval($paymentMethod, 'Payment method deleted.');
    }
}
