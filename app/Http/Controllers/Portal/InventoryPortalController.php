<?php

namespace App\Http\Controllers\Portal;

use App\Models\Account;
use App\Models\AssetCategory;
use App\Models\FeeHead;
use App\Models\ItemCategory;
use App\Models\Location;
use App\Models\PurchaseRequest;
use App\Models\Vendor;
use App\Services\LookupService;
use Illuminate\View\View;

/**
 * Inventory and procurement screens (data: /api/erp/inventory…, purchase-orders, goods-receipts, stock-…).
 */
class InventoryPortalController extends PortalController
{
    public function __construct(private LookupService $lookupService) {}

    public function index(): View
    {
        return $this->screen('stock');
    }

    public function orders(): View
    {
        return $this->screen('orders', ['requests' => PurchaseRequest::where('status', 'approved')->latest('request_date')->limit(100)->get(['id', 'request_no as name'])]);
    }

    public function receipts(): View
    {
        return $this->screen('receipts');
    }

    public function issues(): View
    {
        return $this->screen('issues');
    }

    public function transfers(): View
    {
        return $this->screen('transfers');
    }

    public function counts(): View
    {
        return $this->screen('counts', ['reasons' => $this->lookupService->options('stock_adjustment_reason')]);
    }

    public function ledger(): View
    {
        return $this->screen('ledger');
    }

    public function categories(): View
    {
        return $this->screen('categories', [
            'accounts' => Account::where('is_group', false)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'fee_heads' => FeeHead::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function screen(string $screen, array $data = []): View
    {
        return $this->page("dashboards.inventory.{$screen}", 'admin', 'inventory', [
            'item_categories' => ItemCategory::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'asset_categories' => AssetCategory::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'stores' => Location::where('is_store', true)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'vendors' => Vendor::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'units' => $this->lookupService->options('unit_of_measure'),
            'conditions' => $this->lookupService->options('asset_condition'),
            ...$data,
        ]);
    }
}
