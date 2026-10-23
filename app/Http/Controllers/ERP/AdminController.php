<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Services\AdminService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    use ApiResponse;

    protected AdminService $adminService;

    public function __construct(AdminService $adminService)
    {
        $this->adminService = $adminService;
    }

    /**
     * Admin Dashboard Summary & Stats.
     */
    public function dashboard(): JsonResponse
    {
        $summary = $this->adminService->getDashboardSummary();

        return $this->successResponse($summary, 'Admin dashboard summary retrieved successfully.');
    }

    /**
     * Fee collection, pending fees and spending for the admin home (web and mobile).
     */
    public function finance(Request $request): JsonResponse
    {
        $months = (int) ($request->validate(['months' => ['nullable', 'integer', 'in:6,12']])['months'] ?? 12);

        return $this->successResponse($this->adminService->getFinanceDashboard($months), 'Finance dashboard retrieved.');
    }

    /**
     * System health & statistics.
     */
    public function stats(): JsonResponse
    {
        $summary = $this->adminService->getDashboardSummary();

        return $this->successResponse($summary['overview'], 'System statistics retrieved successfully.');
    }
}
