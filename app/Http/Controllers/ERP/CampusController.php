<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\ERP\StoreCampusRequest;
use App\Http\Requests\ERP\UpdateCampusRequest;
use App\Http\Resources\CampusResource;
use App\Models\Campus;
use App\Services\CampusService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampusController extends Controller
{
    use ApiResponse;

    public function __construct(private CampusService $campusService) {}

    public function index(Request $request): JsonResponse
    {
        $campuses = $this->campusService->paginate($this->filters($request), $request->integer('per_page', 15));

        return $this->paginatedResponse($campuses, CampusResource::class, 'Campuses retrieved.');
    }

    public function datatable(Request $request): JsonResponse
    {
        return $this->successResponse($this->campusService->datatable($this->filters($request)), 'Campuses retrieved.');
    }

    public function store(StoreCampusRequest $request): JsonResponse
    {
        return $this->successResponse(new CampusResource($this->campusService->create($request->validated())), 'Campus created.', 201);
    }

    public function show(Campus $campus): JsonResponse
    {
        return $this->successResponse(new CampusResource($campus->loadCount('users')), 'Campus retrieved.');
    }

    public function update(UpdateCampusRequest $request, Campus $campus): JsonResponse
    {
        return $this->successResponse(new CampusResource($this->campusService->update($campus, $request->validated())), 'Campus updated.');
    }

    public function destroy(Campus $campus): JsonResponse
    {
        $this->campusService->delete($campus);

        return $this->successResponse(null, 'Campus deleted.');
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
