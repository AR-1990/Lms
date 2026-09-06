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
     * System health & statistics.
     */
    public function stats(): JsonResponse
    {
        $summary = $this->adminService->getDashboardSummary();

        return $this->successResponse($summary['overview'], 'System statistics retrieved successfully.');
    }
}
