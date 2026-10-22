<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\ERP\StoreLookupRequest;
use App\Http\Requests\ERP\UpdateLookupRequest;
use App\Http\Resources\LookupResource;
use App\Models\Lookup;
use App\Services\LookupService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private LookupService $lookupService) {}

    /**
     * Paginated list (mobile and simple lists): ?type=&is_active=&search=&per_page=
     */
    public function index(Request $request): JsonResponse
    {
        $lookups = $this->lookupService->paginate($this->filters($request), $request->integer('per_page', 15));

        return $this->paginatedResponse($lookups, LookupResource::class, 'Lookups retrieved.');
    }

    /**
     * Server-side DataTables grid for the admin panel.
     */
    public function datatable(Request $request): JsonResponse
    {
        return $this->successResponse($this->lookupService->datatable($this->filters($request)), 'Lookups retrieved.');
    }

    public function types(): JsonResponse
    {
        return $this->successResponse($this->lookupService->types(), 'Lookup types retrieved.');
    }

    /**
     * Active options of one list for dropdowns — available to every signed-in user.
     */
    public function options(string $type): JsonResponse
    {
        return $this->successResponse(LookupResource::collection($this->lookupService->options($type))->resolve(), 'Options retrieved.');
    }

    public function store(StoreLookupRequest $request): JsonResponse
    {
        $lookup = $this->lookupService->create($request->validated());

        return $this->successResponse(new LookupResource($lookup), 'Option created.', 201);
    }

    public function show(Lookup $lookup): JsonResponse
    {
        return $this->successResponse(new LookupResource($lookup), 'Option retrieved.');
    }

    public function update(UpdateLookupRequest $request, Lookup $lookup): JsonResponse
    {
        $lookup = $this->lookupService->update($lookup, $request->validated());

        return $this->successResponse(new LookupResource($lookup), 'Option updated.');
    }

    public function destroy(Lookup $lookup): JsonResponse
    {
        return $this->deleteWithApproval($lookup, 'Option deleted.');
    }

    /**
     * DataTables sends its own `search[value]` array; only a plain-text `search` is our filter
     * (the grid's global search is applied by Yajra itself).
     *
     * @return array{type: string|null, is_active: string|null, search: string|null}
     */
    private function filters(Request $request): array
    {
        $search = $request->input('search');

        return [
            'type' => is_string($request->input('type')) ? (trim($request->input('type')) ?: null) : null,
            'is_active' => is_scalar($request->input('is_active')) ? (string) $request->input('is_active') : null,
            'search' => is_string($search) ? (trim($search) ?: null) : null,
        ];
    }
}
