<?php

namespace App\Http\Controllers\Portal;

use App\Models\Account;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\Vendor;
use App\Services\BankReconciliationService;
use Illuminate\View\View;

/**
 * Expenses, vendor payments and bank reconciliation screens in the Accounts area (data: /api/erp/expenses…).
 */
class ExpensesPortalController extends PortalController
{
    public function expenses(): View
    {
        return $this->screen('expenses');
    }

    public function categories(): View
    {
        return $this->screen('expense-categories', ['accounts' => Account::where('is_group', false)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name'])]);
    }

    public function vendorPayments(): View
    {
        return $this->screen('vendor-payments');
    }

    public function reconciliation(BankReconciliationService $reconciliationService): View
    {
        return $this->screen('bank-reconciliation', ['bank_accounts' => collect($reconciliationService->bankAccounts())->map(fn (array $row): object => (object) $row)]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function screen(string $screen, array $data = []): View
    {
        return $this->page("dashboards.accounting.{$screen}", 'accounts', 'accounting', [
            'categories' => ExpenseCategory::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'vendors' => Vendor::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'payment_methods' => PaymentMethod::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            ...$data,
        ]);
    }
}
