<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Services\AccountsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Accounts home page data. Vouchers, receipts and payroll have their own APIs (fee-invoices, fee-payments, payroll-runs).
 */
class AccountsController extends Controller
{
    use ApiResponse;

    public function __construct(private AccountsService $accountsService) {}

    public function dashboard(Request $request): JsonResponse
    {
        return $this->successResponse($this->accountsService->getAccountsDashboard($request->user()), 'Accounts dashboard retrieved successfully.');
    }
}
