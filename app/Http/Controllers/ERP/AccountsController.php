<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Services\AccountsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountsController extends Controller
{
    use ApiResponse;

    public function __construct(private AccountsService $accountsService) {}

    /**
     * Accounts dashboard overview.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $data = $this->accountsService->getAccountsDashboard($request->user());

        return $this->successResponse($data, 'Accounts dashboard retrieved successfully.');
    }

    /**
     * Get finance collections.
     */
    public function collections(Request $request): JsonResponse
    {
        $collections = $this->accountsService->getCollections($request->user());

        return $this->successResponse($collections, 'Collections retrieved successfully.');
    }

    /**
     * Get issued challans.
     */
    public function challans(Request $request): JsonResponse
    {
        $challans = $this->accountsService->getChallans($request->user());

        return $this->successResponse($challans, 'Challans retrieved successfully.');
    }

    /**
     * Get payroll overview.
     */
    public function payroll(Request $request): JsonResponse
    {
        $payroll = $this->accountsService->getPayroll($request->user());

        return $this->successResponse($payroll, 'Payroll overview retrieved successfully.');
    }
}
