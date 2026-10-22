<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounts\SaveVendorRequest;
use App\Http\Resources\VendorResource;
use App\Models\Vendor;
use App\Services\VendorService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private VendorService $vendorService) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedResponse($this->vendorService->paginate($this->filters($request), $request->integer('per_page', 15)), VendorResource::class, 'Vendors retrieved.');
    }

    public function datatable(Request $request): JsonResponse
    {
        return $this->successResponse($this->vendorService->datatable($this->filters($request)), 'Vendors retrieved.');
    }

    public function store(SaveVendorRequest $request): JsonResponse
    {
        return $this->successResponse(new VendorResource($this->vendorService->create($request->validated())), 'Vendor created.', 201);
    }

    public function show(Vendor $vendor): JsonResponse
    {
        return $this->successResponse(new VendorResource($vendor->load('payableAccount:id,code,name')), 'Vendor retrieved.');
    }

    public function update(SaveVendorRequest $request, Vendor $vendor): JsonResponse
    {
        return $this->successResponse(new VendorResource($this->vendorService->update($vendor, $request->validated())), 'Vendor updated.');
    }

    public function destroy(Vendor $vendor): JsonResponse
    {
        return $this->deleteWithApproval($vendor, 'Vendor deleted.');
    }

    /**
     * @return array{is_active: string|null, search: string|null}
     */
    private function filters(Request $request): array
    {
        $search = $request->input('search');

        return [
            'is_active' => is_scalar($request->input('is_active')) ? (string) $request->input('is_active') : null,
            'search' => is_string($search) ? (trim($search) ?: null) : null,
        ];
    }
}
