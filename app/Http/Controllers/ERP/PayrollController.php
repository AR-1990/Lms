<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\SaveSalaryComponentRequest;
use App\Http\Requests\Hr\SaveSalaryStructureRequest;
use App\Http\Requests\Hr\SaveTaxSlabRequest;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\SalaryComponent;
use App\Models\SalaryIncrement;
use App\Models\TaxSlab;
use App\Services\PayrollService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Salary components, tax slabs, salary structures and the monthly payroll run (permission manage-payroll;
 * approving a run needs approve-requests). Payslip PDFs are the one non-JSON response.
 */
class PayrollController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private PayrollService $payrollService) {}

    /* ---------- Components ---------- */

    public function componentsDatatable(): JsonResponse
    {
        return $this->successResponse($this->payrollService->componentsDatatable(), 'Salary components retrieved.');
    }

    public function components(): JsonResponse
    {
        return $this->successResponse(SalaryComponent::where('is_active', true)->whereNotIn('code', ['BASIC', 'ABSENT', 'LOAN'])->orderBy('type')->orderBy('sort_order')->get(['id', 'name', 'code', 'type', 'calculation', 'default_value']), 'Salary components retrieved.');
    }

    public function showComponent(SalaryComponent $salaryComponent): JsonResponse
    {
        return $this->successResponse($salaryComponent, 'Salary component retrieved.');
    }

    public function storeComponent(SaveSalaryComponentRequest $request): JsonResponse
    {
        return $this->successResponse($this->payrollService->saveComponent(null, $request->validated()), 'Salary component added.', 201);
    }

    public function updateComponent(SaveSalaryComponentRequest $request, SalaryComponent $salaryComponent): JsonResponse
    {
        return $this->successResponse($this->payrollService->saveComponent($salaryComponent, $request->validated()), 'Salary component updated.');
    }

    public function destroyComponent(SalaryComponent $salaryComponent): JsonResponse
    {
        return $this->deleteWithApproval($salaryComponent, 'Salary component deleted.');
    }

    /* ---------- Tax slabs ---------- */

    public function taxSlabs(Request $request): JsonResponse
    {
        $slabs = TaxSlab::query()->when($request->integer('fiscal_year_id'), fn ($query, int $id) => $query->where('fiscal_year_id', $id))->orderBy('min_income')->get();

        return $this->successResponse($slabs, 'Tax slabs retrieved.');
    }

    public function storeTaxSlab(SaveTaxSlabRequest $request): JsonResponse
    {
        return $this->successResponse($this->payrollService->saveTaxSlab(null, $request->validated()), 'Tax slab added.', 201);
    }

    public function updateTaxSlab(SaveTaxSlabRequest $request, TaxSlab $taxSlab): JsonResponse
    {
        return $this->successResponse($this->payrollService->saveTaxSlab($taxSlab, $request->validated()), 'Tax slab updated.');
    }

    public function destroyTaxSlab(TaxSlab $taxSlab): JsonResponse
    {
        return $this->deleteWithApproval($taxSlab, 'Tax slab deleted.');
    }

    /* ---------- Salary structures ---------- */

    public function structuresDatatable(): JsonResponse
    {
        return $this->successResponse($this->payrollService->structuresDatatable(), 'Salary structures retrieved.');
    }

    public function structure(Employee $employee): JsonResponse
    {
        $structure = $this->payrollService->currentStructure($employee);

        return $this->successResponse([
            'employee' => ['id' => $employee->id, 'name' => $employee->fullName(), 'employee_code' => $employee->employee_code],
            'structure' => $structure,
            'increments' => SalaryIncrement::where('employee_id', $employee->id)->latest('effective_from')->limit(10)->get(['effective_from', 'old_basic', 'new_basic', 'increment_percent', 'reason']),
        ], 'Salary structure retrieved.');
    }

    public function saveStructure(SaveSalaryStructureRequest $request, Employee $employee): JsonResponse
    {
        return $this->successResponse($this->payrollService->saveStructure($employee, $request->validated(), $request->user()), 'Salary structure saved.', 201);
    }

    /* ---------- Payroll runs ---------- */

    public function runsDatatable(): JsonResponse
    {
        return $this->successResponse($this->payrollService->runsDatatable(), 'Payroll runs retrieved.');
    }

    public function showRun(PayrollRun $payrollRun): JsonResponse
    {
        return $this->successResponse($payrollRun->load('approver:id,name'), 'Payroll run retrieved.');
    }

    public function generate(Request $request): JsonResponse
    {
        $month = $request->validate(['month' => ['required', 'date_format:Y-m']])['month'];
        $run = $this->payrollService->generate($month, $request->user());

        return $this->successResponse($run, "Payslips ready for {$run->employee_count} employees.", 201);
    }

    public function payslipsDatatable(PayrollRun $payrollRun): JsonResponse
    {
        return $this->successResponse($this->payrollService->payslipsDatatable($payrollRun), 'Payslips retrieved.');
    }

    public function approve(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        abort_unless($request->user()->hasPermission('approve-requests'), 403, 'Only an administrator can approve payroll.');

        return $this->successResponse($this->payrollService->approve($payrollRun, $request->user()), 'Payroll approved.');
    }

    public function post(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        $run = $this->payrollService->post($payrollRun, $request->user());

        return $this->successResponse($run, 'Posted to the ledger.');
    }

    public function pay(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        $validated = $request->validate([
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->where('is_active', 1)->whereNull('deleted_at')],
            'paid_on' => ['required', 'date_format:Y-m-d'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        return $this->successResponse($this->payrollService->pay($payrollRun, $request->user(), $validated['payment_method_id'], $validated['paid_on'], $validated['reference'] ?? null), 'Salaries marked paid.');
    }

    public function reverse(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']])['reason'];

        return $this->successResponse($this->payrollService->reverse($payrollRun, $request->user(), $reason), 'Payroll reversed; it is a draft again.');
    }

    public function pdf(Request $request, PayrollRun $payrollRun): Response
    {
        $ids = $request->validate(['ids' => ['sometimes', 'array', 'max:500'], 'ids.*' => ['integer']])['ids'] ?? null;
        $payslips = Payslip::where('payroll_run_id', $payrollRun->id)->when($ids, fn ($query) => $query->whereIn('id', $ids))->get();
        abort_if($payslips->isEmpty(), 404, 'No payslips to print.');

        return $this->payrollService->pdf($payslips, 'payslips-'.$payrollRun->month->format('Y-m').'.pdf');
    }
}
