<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\RequestLoanRequest;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Services\LoanService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Staff loans and advances: the list and paying out (manage-payroll), approving (approve-requests).
 */
class LoanController extends Controller
{
    use ApiResponse;

    public function __construct(private LoanService $loanService) {}

    public function datatable(Request $request): JsonResponse
    {
        $filters = $request->validate(['status' => ['nullable', Rule::in(EmployeeLoan::STATUSES)]]);

        return $this->successResponse($this->loanService->datatable($filters), 'Loans retrieved.');
    }

    public function show(EmployeeLoan $employeeLoan): JsonResponse
    {
        return $this->successResponse($employeeLoan->load(['employee:id,first_name,last_name,employee_code', 'loanType:id,name', 'instalmentRows']), 'Loan retrieved.');
    }

    public function store(RequestLoanRequest $request): JsonResponse
    {
        $employee = Employee::findOrFail($request->validated('employee_id'));

        return $this->successResponse($this->loanService->request($employee, $request->validated(), $request->user()), 'Loan request added; an administrator approves it.', 201);
    }

    public function decide(Request $request, EmployeeLoan $employeeLoan): JsonResponse
    {
        abort_unless($request->user()->hasPermission('approve-requests'), 403, 'Only an administrator can approve loans.');
        $validated = $request->validate(['approve' => ['required', 'boolean'], 'remarks' => ['nullable', 'required_if:approve,false', 'string', 'max:500']]);

        return $this->successResponse($this->loanService->decide($request->user(), $employeeLoan->load('employee'), $validated['approve'], $validated['remarks'] ?? null), $validated['approve'] ? 'Loan approved.' : 'Loan rejected.');
    }

    public function disburse(Request $request, EmployeeLoan $employeeLoan): JsonResponse
    {
        $validated = $request->validate([
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->where('is_active', 1)->whereNull('deleted_at')],
            'issued_on' => ['required', 'date_format:Y-m-d'],
        ]);

        return $this->successResponse($this->loanService->disburse($request->user(), $employeeLoan->load('employee'), $validated['payment_method_id'], $validated['issued_on']), 'Loan paid out; instalments start '.$employeeLoan->start_month->format('M Y').'.');
    }
}
