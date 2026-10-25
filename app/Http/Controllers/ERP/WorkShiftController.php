<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\SaveWorkShiftRequest;
use App\Models\WorkShift;
use App\Services\WorkShiftService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;

class WorkShiftController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private WorkShiftService $shiftService) {}

    public function datatable(): JsonResponse
    {
        return $this->successResponse($this->shiftService->datatable(), 'Shifts retrieved.');
    }

    public function store(SaveWorkShiftRequest $request): JsonResponse
    {
        return $this->successResponse($this->shiftService->save(null, $request->validated()), 'Shift added.', 201);
    }

    public function show(WorkShift $workShift): JsonResponse
    {
        return $this->successResponse($workShift->loadCount('employees'), 'Shift retrieved.');
    }

    public function update(SaveWorkShiftRequest $request, WorkShift $workShift): JsonResponse
    {
        return $this->successResponse($this->shiftService->save($workShift, $request->validated()), 'Shift saved.');
    }

    public function destroy(WorkShift $workShift): JsonResponse
    {
        return $this->deleteWithApproval($workShift, 'Shift deleted.');
    }
}
