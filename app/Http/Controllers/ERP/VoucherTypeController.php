<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounts\SaveVoucherTypeRequest;
use App\Http\Resources\VoucherTypeResource;
use App\Models\VoucherType;
use App\Services\VoucherTypeService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class VoucherTypeController extends Controller
{
    use ApiResponse;

    public function __construct(private VoucherTypeService $voucherTypeService) {}

    public function index(): JsonResponse
    {
        return $this->successResponse(VoucherTypeResource::collection($this->voucherTypeService->query()->orderBy('name')->get())->resolve(), 'Voucher types retrieved.');
    }

    public function datatable(): JsonResponse
    {
        return $this->successResponse($this->voucherTypeService->datatable(), 'Voucher types retrieved.');
    }

    public function store(SaveVoucherTypeRequest $request): JsonResponse
    {
        return $this->successResponse(new VoucherTypeResource($this->voucherTypeService->create($request->validated())), 'Voucher type created.', 201);
    }

    public function show(VoucherType $voucherType): JsonResponse
    {
        return $this->successResponse(new VoucherTypeResource($voucherType->loadCount('journalEntries')), 'Voucher type retrieved.');
    }

    public function update(SaveVoucherTypeRequest $request, VoucherType $voucherType): JsonResponse
    {
        return $this->successResponse(new VoucherTypeResource($this->voucherTypeService->update($voucherType, $request->validated())), 'Voucher type updated.');
    }

    public function destroy(VoucherType $voucherType): JsonResponse
    {
        $this->voucherTypeService->delete($voucherType);

        return $this->successResponse(null, 'Voucher type deleted.');
    }
}
