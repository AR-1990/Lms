<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\SettleEmployeeExitRequest;
use App\Http\Requests\Hr\StartEmployeeExitRequest;
use App\Models\EmployeeExit;
use App\Models\ExitClearance;
use App\Services\EmployeeExitService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff exits: start, clear by area, preview and pay the final settlement, experience letter.
 */
class EmployeeExitController extends Controller
{
    use ApiResponse;

    public function __construct(private EmployeeExitService $exitService) {}

    public function datatable(Request $request): JsonResponse
    {
        return $this->successResponse($this->exitService->datatable(['status' => $request->string('status')->toString() ?: null]), 'Exits retrieved.');
    }

    public function store(StartEmployeeExitRequest $request): JsonResponse
    {
        return $this->successResponse($this->exitService->start($request->validated()), 'Exit started; clearance is now open.', 201);
    }

    public function show(EmployeeExit $employeeExit): JsonResponse
    {
        return $this->successResponse(['exit' => $this->exitService->find($employeeExit), 'settlement' => $employeeExit->status === EmployeeExit::STATUS_CLEARANCE ? $this->exitService->settlement($employeeExit) : null], 'Exit retrieved.');
    }

    /**
     * The settlement with HR's extra lines, before paying it.
     */
    public function preview(SettleEmployeeExitRequest $request, EmployeeExit $employeeExit): JsonResponse
    {
        return $this->successResponse($this->exitService->settlement($employeeExit, $request->validated('lines') ?? []), 'Settlement worked out.');
    }

    public function settle(SettleEmployeeExitRequest $request, EmployeeExit $employeeExit): JsonResponse
    {
        return $this->successResponse($this->exitService->settle($employeeExit, $request->validated(), $request->user()), 'Final settlement paid and the employee closed.');
    }

    public function cancel(Request $request, EmployeeExit $employeeExit): JsonResponse
    {
        return $this->successResponse($this->exitService->cancel($employeeExit, $request->user()), 'Exit cancelled.');
    }

    public function clear(Request $request, ExitClearance $exitClearance): JsonResponse
    {
        $validated = $request->validate(['cleared' => ['required', 'boolean'], 'remarks' => ['nullable', 'string', 'max:255']]);

        return $this->successResponse($this->exitService->clear($exitClearance, $validated['cleared'], $validated['remarks'] ?? null, $request->user()), $validated['cleared'] ? 'Cleared.' : 'Clearance withdrawn.');
    }

    public function experienceLetter(Request $request, EmployeeExit $employeeExit): Response
    {
        return $this->exitService->experienceLetter($employeeExit, $request->user());
    }
}
