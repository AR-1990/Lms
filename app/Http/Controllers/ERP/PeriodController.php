<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\SavePeriodRequest;
use App\Http\Resources\PeriodResource;
use App\Models\Period;
use App\Services\PeriodService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class PeriodController extends Controller
{
    use ApiResponse;

    public function __construct(private PeriodService $periodService) {}

    public function index(): JsonResponse
    {
        return $this->successResponse(PeriodResource::collection($this->periodService->all())->resolve(), 'Bell schedule retrieved.');
    }

    public function datatable(): JsonResponse
    {
        return $this->successResponse($this->periodService->datatable(), 'Bell schedule retrieved.');
    }

    public function store(SavePeriodRequest $request): JsonResponse
    {
        return $this->successResponse(new PeriodResource($this->periodService->create($request->validated())), 'Period created.', 201);
    }

    public function show(Period $period): JsonResponse
    {
        return $this->successResponse(new PeriodResource($period), 'Period retrieved.');
    }

    public function update(SavePeriodRequest $request, Period $period): JsonResponse
    {
        return $this->successResponse(new PeriodResource($this->periodService->update($period, $request->validated())), 'Period updated.');
    }

    public function destroy(Period $period): JsonResponse
    {
        $this->periodService->delete($period);

        return $this->successResponse(null, 'Period deleted.');
    }
}
