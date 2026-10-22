<?php

namespace App\Http\Controllers\Portal;

use App\Models\Account;
use App\Models\ConcessionType;
use App\Models\FeeHead;
use App\Services\AcademicSessionService;
use App\Services\LookupService;
use App\Services\PaymentMethodService;
use Illuminate\View\View;

/**
 * Fee screens for the admin and accounts portals; all data goes through /api/erp/fees.
 */
class FeesPortalController extends PortalController
{
    public function __construct(private AcademicSessionService $sessionService) {}

    public function challans(PaymentMethodService $paymentMethodService, LookupService $lookupService): View
    {
        return $this->screen('challans', [
            'payment_methods' => $paymentMethodService->active(),
            'banks' => $lookupService->options('bank'),
        ]);
    }

    public function receipts(PaymentMethodService $paymentMethodService, LookupService $lookupService): View
    {
        return $this->screen('receipts', [
            'payment_methods' => $paymentMethodService->active(),
            'banks' => $lookupService->options('bank'),
        ]);
    }

    public function structures(): View
    {
        return $this->screen('structures');
    }

    public function heads(): View
    {
        return $this->screen('heads', [
            'frequencies' => app(LookupService::class)->options('fee_frequency'),
            'income_accounts' => Account::where('is_group', false)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function concessions(): View
    {
        return $this->screen('concessions');
    }

    public function concessionTypes(): View
    {
        return $this->screen('concession-types', [
            'expense_accounts' => Account::where('is_group', false)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function screen(string $screen, array $data = []): View
    {
        return $this->page("dashboards.fees.{$screen}", 'accounts', 'fees', [
            ...$this->sessionService->options(),
            'fee_heads' => FeeHead::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'code', 'frequency_id']),
            'concession_types' => ConcessionType::where('is_active', true)->orderBy('name')->get(['id', 'name', 'calculation', 'default_value']),
            ...$data,
        ]);
    }
}
