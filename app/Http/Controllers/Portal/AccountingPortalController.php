<?php

namespace App\Http\Controllers\Portal;

use App\Models\Account;
use App\Models\Lookup;
use App\Models\VoucherType;
use App\Services\AccountService;
use Illuminate\View\View;

/**
 * Accounts & ledger screens for the admin and accounts portals. Grids, popups and actions
 * all call the /api/erp/ledger endpoints; these actions only render the page shell.
 */
class AccountingPortalController extends PortalController
{
    public function __construct(private AccountService $accountService) {}

    public function chart(): View
    {
        return $this->screen('chart', $this->accountService->options());
    }

    public function ledger(Account $account): View
    {
        return $this->screen('ledger', ['account' => $this->accountService->find($account)]);
    }

    public function vouchers(): View
    {
        return $this->screen('vouchers', [
            ...$this->accountService->options(),
            'voucher_types' => VoucherType::orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function trialBalance(): View
    {
        return $this->screen('trial-balance');
    }

    public function fiscalYears(): View
    {
        return $this->screen('fiscal-years');
    }

    public function voucherTypes(): View
    {
        return $this->screen('voucher-types');
    }

    public function paymentMethods(): View
    {
        return $this->screen('payment-methods', $this->accountService->options());
    }

    public function bankAccounts(): View
    {
        return $this->screen('bank-accounts', [
            'banks' => Lookup::ofType('bank')->active()->get(['id', 'name']),
            'bank_ledger_accounts' => Account::where('is_bank_account', true)->where('is_group', false)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function vendors(): View
    {
        return $this->screen('vendors', $this->accountService->options());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function screen(string $screen, array $data = []): View
    {
        return $this->page("dashboards.accounting.{$screen}", 'accounts', 'accounting', $data);
    }
}
