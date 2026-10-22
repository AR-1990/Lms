<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\SaveJobOpeningRequest;
use App\Http\Resources\JobOpeningResource;
use App\Models\JobOpening;
use App\Services\RecruitmentService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobOpeningController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private RecruitmentService $recruitmentService) {}

    public function index(Request $request): JsonResponse
    {
        $openings = $this->recruitmentService->openingsQuery($this->filters($request))->latest()->paginate(min(max($request->integer('per_page', 15), 1), 100));

        return $this->paginatedResponse($openings, JobOpeningResource::class, 'Job openings retrieved.');
    }

    public function datatable(Request $request): JsonResponse
    {
        return $this->successResponse($this->recruitmentService->openingsDatatable($this->filters($request)), 'Job openings retrieved.');
    }

    public function store(SaveJobOpeningRequest $request): JsonResponse
    {
        return $this->successResponse(new JobOpeningResource($this->recruitmentService->createOpening($request->validated())), 'Job opening created.', 201);
    }

    public function show(JobOpening $jobOpening): JsonResponse
    {
        return $this->successResponse(new JobOpeningResource($this->recruitmentService->findOpening($jobOpening)), 'Job opening retrieved.');
    }

    public function update(SaveJobOpeningRequest $request, JobOpening $jobOpening): JsonResponse
    {
        return $this->successResponse(new JobOpeningResource($this->recruitmentService->updateOpening($jobOpening, $request->validated())), 'Job opening updated.');
    }

    public function destroy(JobOpening $jobOpening): JsonResponse
    {
        return $this->deleteWithApproval($jobOpening, 'Job opening deleted.');
    }

    /**
     * @return array{status: string|null}
     */
    private function filters(Request $request): array
    {
        return ['status' => is_string($request->input('status')) ? ($request->input('status') ?: null) : null];
    }
}
