<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounts\SaveFiscalYearRequest;
use App\Http\Resources\FiscalYearResource;
use App\Models\FiscalYear;
use App\Services\FiscalYearService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class FiscalYearController extends Controller
{
    use ApiResponse;

    public function __construct(private FiscalYearService $fiscalYearService) {}

    public function index(): JsonResponse
    {
        return $this->successResponse(FiscalYearResource::collection($this->fiscalYearService->query()->orderByDesc('starts_on')->get())->resolve(), 'Fiscal years retrieved.');
    }

    public function datatable(): JsonResponse
    {
        return $this->successResponse($this->fiscalYearService->datatable(), 'Fiscal years retrieved.');
    }

    public function store(SaveFiscalYearRequest $request): JsonResponse
    {
        return $this->successResponse(new FiscalYearResource($this->fiscalYearService->create($request->validated())), 'Fiscal year created.', 201);
    }

    public function show(FiscalYear $fiscalYear): JsonResponse
    {
        return $this->successResponse(new FiscalYearResource($fiscalYear->loadCount('journalEntries')), 'Fiscal year retrieved.');
    }

    public function update(SaveFiscalYearRequest $request, FiscalYear $fiscalYear): JsonResponse
    {
        return $this->successResponse(new FiscalYearResource($this->fiscalYearService->update($fiscalYear, $request->validated())), 'Fiscal year updated.');
    }

    public function destroy(FiscalYear $fiscalYear): JsonResponse
    {
        $this->fiscalYearService->delete($fiscalYear);

        return $this->successResponse(null, 'Fiscal year deleted.');
    }

    public function close(FiscalYear $fiscalYear): JsonResponse
    {
        return $this->successResponse(new FiscalYearResource($this->fiscalYearService->setStatus($fiscalYear, FiscalYear::STATUS_CLOSED)), "{$fiscalYear->name} closed.");
    }

    public function reopen(FiscalYear $fiscalYear): JsonResponse
    {
        return $this->successResponse(new FiscalYearResource($this->fiscalYearService->setStatus($fiscalYear, FiscalYear::STATUS_OPEN)), "{$fiscalYear->name} reopened.");
    }
}
