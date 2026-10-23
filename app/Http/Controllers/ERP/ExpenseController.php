<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expenses\PayExpenseRequest;
use App\Http\Requests\Expenses\PayVendorRequest;
use App\Http\Requests\Expenses\ReasonRequest;
use App\Http\Requests\Expenses\SaveExpenseCategoryRequest;
use App\Http\Requests\Expenses\SaveExpenseRequest;
use App\Http\Requests\Expenses\SaveReconciliationRequest;
use App\Http\Requests\Expenses\StartReconciliationRequest;
use App\Models\BankReconciliation;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Services\BankReconciliationService;
use App\Services\ExpenseService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Expenses, vendor payments (manage-expenses; approving needs approve-requests) and bank reconciliation
 * (reconcile-bank). Same API for web and mobile.
 */
class ExpenseController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private ExpenseService $expenseService, private BankReconciliationService $reconciliationService) {}

    /* ---------- Categories ---------- */

    public function categoriesDatatable(): JsonResponse
    {
        return $this->successResponse($this->expenseService->categoriesDatatable(), 'Expense categories retrieved.');
    }

    public function storeCategory(SaveExpenseCategoryRequest $request): JsonResponse
    {
        return $this->successResponse($this->expenseService->saveCategory(null, $request->validated()), 'Category added.', 201);
    }

    public function updateCategory(SaveExpenseCategoryRequest $request, ExpenseCategory $expenseCategory): JsonResponse
    {
        return $this->successResponse($this->expenseService->saveCategory($expenseCategory, $request->validated()), 'Category saved.');
    }

    public function destroyCategory(ExpenseCategory $expenseCategory): JsonResponse
    {
        return $this->deleteWithApproval($expenseCategory, 'Category deleted.');
    }

    /* ---------- Expenses ---------- */

    public function datatable(Request $request): JsonResponse
    {
        $filters = $request->validate(['status' => ['nullable', Rule::in(Expense::STATUSES)], 'expense_category_id' => ['nullable', 'integer'], 'vendor_id' => ['nullable', 'integer']]);

        return $this->successResponse($this->expenseService->datatable($filters), 'Expenses retrieved.');
    }

    public function show(Expense $expense): JsonResponse
    {
        return $this->successResponse($this->expenseService->details($expense), 'Expense retrieved.');
    }

    public function store(SaveExpenseRequest $request): JsonResponse
    {
        return $this->successResponse($this->expenseService->details($this->expenseService->save(null, $request->validated())), 'Expense saved as draft.', 201);
    }

    public function update(SaveExpenseRequest $request, Expense $expense): JsonResponse
    {
        return $this->successResponse($this->expenseService->details($this->expenseService->save($expense, $request->validated())), 'Expense saved.');
    }

    public function approve(Request $request, Expense $expense): JsonResponse
    {
        abort_unless($request->user()->hasPermission('approve-requests'), 403, 'Only an approver can approve expenses.');

        return $this->successResponse($this->expenseService->details($this->expenseService->approve($expense, $request->user())), 'Expense approved.');
    }

    public function pay(PayExpenseRequest $request, Expense $expense): JsonResponse
    {
        $paid = $this->expenseService->pay($expense, $request->validated(), $request->user());

        return $this->successResponse($this->expenseService->details($paid), 'Expense paid.');
    }

    public function cancel(ReasonRequest $request, Expense $expense): JsonResponse
    {
        return $this->successResponse($this->expenseService->details($this->expenseService->cancel($expense, $request->validated('reason'), $request->user())), 'Expense cancelled.');
    }

    public function destroy(Expense $expense): JsonResponse
    {
        return $this->deleteWithApproval($expense, 'Expense deleted.');
    }

    /* ---------- Vendors ---------- */

    public function vendorBalances(): JsonResponse
    {
        return $this->successResponse($this->expenseService->vendorBalancesDatatable(), 'Vendor balances retrieved.');
    }

    public function vendorOwed(Vendor $vendor): JsonResponse
    {
        return $this->successResponse(['vendor_id' => $vendor->id, 'owed' => $this->expenseService->owedTo($vendor)], 'Amount owed retrieved.');
    }

    public function paymentsDatatable(): JsonResponse
    {
        return $this->successResponse($this->expenseService->paymentsDatatable(), 'Vendor payments retrieved.');
    }

    public function payVendor(PayVendorRequest $request): JsonResponse
    {
        $payment = $this->expenseService->payVendor($request->validated(), $request->user());

        return $this->successResponse($payment, "Paid as {$payment->payment_no}.", 201);
    }

    public function reversePayment(ReasonRequest $request, VendorPayment $vendorPayment): JsonResponse
    {
        abort_unless($request->user()->hasPermission('approve-requests'), 403, 'Only an approver can reverse payments.');

        return $this->successResponse($this->expenseService->reversePayment($vendorPayment, $request->validated('reason'), $request->user()), 'Payment reversed.');
    }

    /* ---------- Bank reconciliation ---------- */

    public function reconciliations(): JsonResponse
    {
        return $this->successResponse($this->reconciliationService->datatable(), 'Reconciliations retrieved.');
    }

    public function bankAccounts(): JsonResponse
    {
        return $this->successResponse($this->reconciliationService->bankAccounts(), 'Bank accounts retrieved.');
    }

    public function startReconciliation(StartReconciliationRequest $request): JsonResponse
    {
        $reconciliation = $this->reconciliationService->start($request->validated(), $request->user());

        return $this->successResponse($this->reconciliationService->sheet($reconciliation), 'Reconciliation started.', 201);
    }

    public function showReconciliation(BankReconciliation $bankReconciliation): JsonResponse
    {
        return $this->successResponse($this->reconciliationService->sheet($bankReconciliation), 'Reconciliation retrieved.');
    }

    public function saveReconciliation(SaveReconciliationRequest $request, BankReconciliation $bankReconciliation): JsonResponse
    {
        $saved = $this->reconciliationService->save($bankReconciliation, $request->validated('cleared', []), $request->validated('statement_balance'));

        return $this->successResponse($this->reconciliationService->sheet($saved), 'Ticks saved.');
    }

    public function completeReconciliation(Request $request, BankReconciliation $bankReconciliation): JsonResponse
    {
        return $this->successResponse($this->reconciliationService->sheet($this->reconciliationService->complete($bankReconciliation, $request->user())), 'Bank reconciled.');
    }

    public function destroyReconciliation(BankReconciliation $bankReconciliation): JsonResponse
    {
        $this->reconciliationService->delete($bankReconciliation);

        return $this->successResponse(null, 'Reconciliation deleted.');
    }
}
