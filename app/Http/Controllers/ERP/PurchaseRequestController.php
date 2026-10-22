<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\SavePurchaseRequest;
use App\Http\Resources\PurchaseRequestResource;
use App\Models\PurchaseRequest;
use App\Services\PurchaseRequestService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PurchaseRequestController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private PurchaseRequestService $purchaseService) {}

    public function index(Request $request): JsonResponse
    {
        $purchases = $this->purchaseService->query($this->filters($request))->latest('id')->paginate(min(max($request->integer('per_page', 15), 1), 100));

        return $this->paginatedResponse($purchases, PurchaseRequestResource::class, 'Purchase requests retrieved.');
    }

    public function datatable(Request $request): JsonResponse
    {
        return $this->successResponse($this->purchaseService->datatable($this->filters($request)), 'Purchase requests retrieved.');
    }

    public function store(SavePurchaseRequest $request): JsonResponse
    {
        $purchase = $this->purchaseService->create($request->validated(), $request->user());
        $message = $purchase->status === 'approved' ? "{$purchase->request_no} raised and approved." : "{$purchase->request_no} sent to an administrator for approval.";

        return $this->successResponse(new PurchaseRequestResource($purchase), $message, 201);
    }

    public function show(PurchaseRequest $purchaseRequest): JsonResponse
    {
        return $this->successResponse(new PurchaseRequestResource($this->purchaseService->find($purchaseRequest)), 'Purchase request retrieved.');
    }

    public function update(SavePurchaseRequest $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        return $this->successResponse(new PurchaseRequestResource($this->purchaseService->update($purchaseRequest, $request->validated())), 'Purchase request updated.');
    }

    public function destroy(PurchaseRequest $purchaseRequest): JsonResponse
    {
        return $this->deleteWithApproval($purchaseRequest, 'Purchase request deleted.');
    }

    /**
     * @return array{status: string|null, department_id: int|null}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(PurchaseRequest::STATUSES)],
            'department_id' => ['nullable', 'integer'],
        ]);

        return [
            'status' => $validated['status'] ?? null,
            'department_id' => isset($validated['department_id']) ? (int) $validated['department_id'] : null,
        ];
    }
}
