<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

/**
 * Procurement (PLAN.md §16): purchase orders to vendors (draft → approved → partially_received / received,
 * or cancelled) and goods receipts. A receipt raises stock in a store, turns asset lines into assets in the
 * register, and posts the purchase (inventory / asset accounts ← vendor payable). Requests come from the
 * HR purchase requests.
 */
class ProcurementService
{
    public function __construct(
        private NumberSequenceService $numberSequenceService,
        private StockService $stockService,
        private JournalService $journalService,
        private ActivityLogger $activityLogger,
    ) {}

    /* ---------- Purchase orders ---------- */

    /**
     * @param  array{status?: string|null, vendor_id?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function ordersDatatable(array $filters): array
    {
        $query = PurchaseOrder::query()
            ->select(['id', 'po_no', 'vendor_id', 'purchase_request_id', 'location_id', 'order_date', 'expected_date', 'total', 'status', 'approved_by', 'created_at'])
            ->with(['vendor:id,name', 'location:id,name', 'request:id,request_no'])
            ->withCount('items')
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['vendor_id'] ?? null, fn (Builder $query, int $id) => $query->where('vendor_id', $id));

        return DataTables::eloquent($query)
            ->addColumn('vendor_name', fn (PurchaseOrder $order): ?string => $order->vendor?->name)
            ->addColumn('location_name', fn (PurchaseOrder $order): ?string => $order->location?->name)
            ->addColumn('request_no', fn (PurchaseOrder $order): ?string => $order->request?->request_no)
            ->editColumn('order_date', fn (PurchaseOrder $order): ?string => $order->order_date?->toDateString())
            ->editColumn('expected_date', fn (PurchaseOrder $order): ?string => $order->expected_date?->toDateString())
            ->removeColumn('vendor', 'location', 'request')
            ->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function order(PurchaseOrder $order): array
    {
        $order->load(['vendor:id,name', 'location:id,name', 'request:id,request_no', 'approver:id,name', 'items.item:id,sku,name', 'items.assetCategory:id,name', 'receipts:id,purchase_order_id,grn_no,received_on,total']);

        return [
            ...$order->attributesToArray(),
            'order_date' => $order->order_date?->toDateString(),
            'expected_date' => $order->expected_date?->toDateString(),
            'vendor_name' => $order->vendor?->name,
            'location_name' => $order->location?->name,
            'request_no' => $order->request?->request_no,
            'approver_name' => $order->approver?->name,
            'items' => $order->items->sortBy('sort_order')->values()->map(fn (PurchaseOrderItem $line): array => [
                'id' => $line->id, 'inventory_item_id' => $line->inventory_item_id, 'asset_category_id' => $line->asset_category_id,
                'kind' => $line->asset_category_id ? 'asset' : 'stock', 'label' => $line->item ? $line->item->sku.' '.$line->item->name : ($line->assetCategory ? 'Asset · '.$line->assetCategory->name : null),
                'description' => $line->description, 'quantity' => $line->quantity, 'unit_cost' => $line->unit_cost, 'line_total' => $line->line_total,
                'received_quantity' => $line->received_quantity, 'remaining' => round($line->quantity - $line->received_quantity, 3),
            ])->all(),
            'receipts' => $order->receipts->map(fn (GoodsReceipt $receipt): array => ['id' => $receipt->id, 'grn_no' => $receipt->grn_no, 'received_on' => $receipt->received_on?->toDateString(), 'total' => $receipt->total])->all(),
        ];
    }

    /**
     * Create or change a draft order; the lines are replaced (old ones soft deleted) and totals recalculated.
     *
     * @param  array<string, mixed>  $data  order fields + items[{inventory_item_id?|asset_category_id?, description, quantity, unit_cost}]
     */
    public function saveOrder(?PurchaseOrder $order, array $data): PurchaseOrder
    {
        if ($order !== null && $order->status !== 'draft') {
            throw ValidationException::withMessages(['order' => ['Only a draft order can be changed.']]);
        }

        return DB::transaction(function () use ($order, $data): PurchaseOrder {
            $order ??= new PurchaseOrder(['po_no' => $this->numberSequenceService->next('po_no'), 'status' => 'draft']);
            $order->fill([...collect($data)->except('items')->all(), 'tax' => (int) ($data['tax'] ?? 0), 'discount' => (int) ($data['discount'] ?? 0)])->save();
            $order->items()->get()->each->delete();
            $subtotal = 0;

            foreach (array_values($data['items']) as $position => $line) {
                $lineTotal = (int) round((float) $line['quantity'] * (int) $line['unit_cost']);
                $subtotal += $lineTotal;
                $order->items()->create([
                    'inventory_item_id' => $line['inventory_item_id'] ?? null,
                    'asset_category_id' => empty($line['inventory_item_id']) ? ($line['asset_category_id'] ?? null) : null,
                    'description' => $line['description'] ?? (isset($line['inventory_item_id']) ? InventoryItem::whereKey($line['inventory_item_id'])->value('name') : AssetCategory::whereKey($line['asset_category_id'])->value('name')),
                    'quantity' => $line['quantity'], 'unit_cost' => $line['unit_cost'], 'line_total' => $lineTotal, 'sort_order' => $position,
                ]);
            }

            $order->update(['subtotal' => $subtotal, 'total' => max(0, $subtotal + (int) ($data['tax'] ?? 0) - (int) ($data['discount'] ?? 0))]);

            return $order;
        });
    }

    public function approveOrder(PurchaseOrder $order, User $user): PurchaseOrder
    {
        if ($order->status !== 'draft') {
            throw ValidationException::withMessages(['order' => ['Only a draft order can be approved.']]);
        }

        $order->update(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()]);
        $this->activityLogger->record('inventory', 'approved', "{$user->name} approved purchase order {$order->po_no}", $order);

        return $order;
    }

    public function cancelOrder(PurchaseOrder $order, string $reason, User $user): PurchaseOrder
    {
        if (! in_array($order->status, ['draft', 'approved'], true)) {
            throw ValidationException::withMessages(['order' => ['Goods were already received on this order; it cannot be cancelled.']]);
        }

        $order->update(['status' => 'cancelled', 'cancel_reason' => $reason]);
        $this->activityLogger->record('inventory', 'cancelled', "{$user->name} cancelled purchase order {$order->po_no}: {$reason}", $order);

        return $order;
    }

    public function deleteOrder(PurchaseOrder $order): void
    {
        if ($order->status !== 'draft') {
            throw ValidationException::withMessages(['order' => ['Only a draft order can be deleted; cancel it instead.']]);
        }

        DB::transaction(function () use ($order): void {
            $order->items()->get()->each->delete();
            $order->delete();
        });
    }

    /* ---------- Goods receipts ---------- */

    /**
     * @return array<string, mixed>
     */
    public function receiptsDatatable(): array
    {
        $query = GoodsReceipt::query()
            ->select(['id', 'grn_no', 'purchase_order_id', 'vendor_id', 'received_on', 'location_id', 'vendor_invoice_no', 'total', 'journal_entry_id', 'created_at'])
            ->with(['vendor:id,name', 'location:id,name', 'order:id,po_no', 'journalEntry:id,voucher_no'])
            ->withCount('items');

        return DataTables::eloquent($query)
            ->addColumn('vendor_name', fn (GoodsReceipt $row): ?string => $row->vendor?->name)
            ->addColumn('location_name', fn (GoodsReceipt $row): ?string => $row->location?->name)
            ->addColumn('po_no', fn (GoodsReceipt $row): ?string => $row->order?->po_no)
            ->addColumn('voucher_no', fn (GoodsReceipt $row): ?string => $row->journalEntry?->voucher_no)
            ->editColumn('received_on', fn (GoodsReceipt $row): ?string => $row->received_on?->toDateString())
            ->removeColumn('vendor', 'location', 'order', 'journal_entry')
            ->toArray();
    }

    /**
     * Receive goods (against an approved order, or directly from a vendor).
     *
     * @param  array{purchase_order_id?: int|null, vendor_id?: int|null, received_on: string, location_id: int, vendor_invoice_no?: string|null, vendor_invoice_date?: string|null, remarks?: string|null, items: array<int, array{purchase_order_item_id?: int|null, inventory_item_id?: int|null, asset_category_id?: int|null, description?: string|null, quantity: float, unit_cost?: int|null, batch_no?: string|null, expiry_date?: string|null}>}  $data
     */
    public function receive(array $data, User $user): GoodsReceipt
    {
        $order = isset($data['purchase_order_id']) ? PurchaseOrder::findOrFail($data['purchase_order_id']) : null;

        if ($order !== null && ! in_array($order->status, ['approved', 'partially_received'], true)) {
            throw ValidationException::withMessages(['purchase_order_id' => ['Goods can only be received on an approved order.']]);
        }

        $vendorId = $order?->vendor_id ?? ($data['vendor_id'] ?? throw ValidationException::withMessages(['vendor_id' => ['Choose the vendor.']]));

        return DB::transaction(function () use ($data, $user, $order, $vendorId): GoodsReceipt {
            $receipt = GoodsReceipt::create([
                ...collect($data)->except(['items', 'vendor_id'])->all(),
                'grn_no' => $this->numberSequenceService->next('grn_no'),
                'vendor_id' => $vendorId,
                'received_by' => $user->id,
            ]);
            $debits = [];
            $total = 0;

            foreach ($data['items'] as $row) {
                $quantity = (float) $row['quantity'];

                if ($quantity <= 0) {
                    continue;
                }

                $orderLine = isset($row['purchase_order_item_id']) ? PurchaseOrderItem::where('purchase_order_id', $order?->id)->whereKey($row['purchase_order_item_id'])->firstOrFail() : null;

                if ($orderLine !== null && $orderLine->received_quantity + $quantity > $orderLine->quantity + 0.0005) {
                    throw ValidationException::withMessages(['items' => ["More {$orderLine->description} is being received than was ordered."]]);
                }

                $itemId = $orderLine?->inventory_item_id ?? ($row['inventory_item_id'] ?? null);
                $assetCategoryId = $itemId === null ? ($orderLine?->asset_category_id ?? ($row['asset_category_id'] ?? null)) : null;
                $unitCost = (int) ($row['unit_cost'] ?? $orderLine?->unit_cost ?? 0);
                $lineTotal = (int) round($quantity * $unitCost);
                $line = $receipt->items()->create([
                    'purchase_order_item_id' => $orderLine?->id, 'inventory_item_id' => $itemId, 'asset_category_id' => $assetCategoryId,
                    'description' => $row['description'] ?? $orderLine?->description ?? ($itemId ? InventoryItem::whereKey($itemId)->value('name') : 'Asset'),
                    'quantity' => $quantity, 'unit_cost' => $unitCost, 'line_total' => $lineTotal, 'batch_no' => $row['batch_no'] ?? null, 'expiry_date' => $row['expiry_date'] ?? null,
                ]);

                if ($itemId !== null) {
                    $item = InventoryItem::findOrFail($itemId);
                    $this->stockService->move($item, (int) $data['location_id'], 'purchase_in', $quantity, $unitCost, $receipt, $data['received_on'], $row['batch_no'] ?? null, $row['expiry_date'] ?? null);
                    $account = ItemCategory::whereKey($item->item_category_id)->value('inventory_account_id') ?? $this->account(StockService::INVENTORY_ACCOUNT);
                } elseif ($assetCategoryId !== null) {
                    $account = $this->createAssets($line, $receipt, $order, (int) $data['location_id']);
                } else {
                    throw ValidationException::withMessages(['items' => ['Each line needs a stock item or an asset category.']]);
                }

                $debits[$account] = ($debits[$account] ?? 0) + $lineTotal;
                $total += $lineTotal;
                $orderLine?->increment('received_quantity', $quantity);
            }

            if ($total === 0) {
                throw ValidationException::withMessages(['items' => ['Enter the quantity received on at least one line.']]);
            }

            $vendor = Vendor::findOrFail($vendorId);
            $lines = collect($debits)->map(fn (int $amount, int $accountId): array => ['account_id' => $accountId, 'debit' => $amount, 'description' => 'Goods received '.$receipt->grn_no])->values()->all();
            $lines[] = ['account_id' => $vendor->payable_account_id ?? $this->account(AssetLedgerService::PAYABLE_ACCOUNT), 'credit' => $total, 'description' => $vendor->name.($receipt->vendor_invoice_no ? ' invoice '.$receipt->vendor_invoice_no : ''), 'party_type' => Vendor::class, 'party_id' => $vendor->id];
            $entry = $this->journalService->record('JV', $data['received_on'], "Goods received {$receipt->grn_no} from {$vendor->name}".($order ? " (PO {$order->po_no})" : ''), $lines, $receipt);
            $receipt->update(['total' => $total, 'journal_entry_id' => $entry->id]);
            Asset::whereNull('journal_entry_id')->where('invoice_no', $receipt->grn_no)->update(['journal_entry_id' => $entry->id]);

            if ($order !== null) {
                $open = $order->items()->get()->contains(fn (PurchaseOrderItem $line): bool => $line->received_quantity < $line->quantity - 0.0005);
                $order->update(['status' => $open ? 'partially_received' : 'received']);
            }

            $this->activityLogger->record('inventory', 'received', "{$user->name} received {$receipt->grn_no} from {$vendor->name}", $receipt);

            return $receipt;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function receipt(GoodsReceipt $receipt): array
    {
        $receipt->load(['vendor:id,name', 'location:id,name', 'order:id,po_no', 'journalEntry:id,voucher_no', 'receiver:id,name', 'items']);

        return [
            ...$receipt->attributesToArray(),
            'received_on' => $receipt->received_on?->toDateString(),
            'vendor_name' => $receipt->vendor?->name,
            'location_name' => $receipt->location?->name,
            'po_no' => $receipt->order?->po_no,
            'voucher_no' => $receipt->journalEntry?->voucher_no,
            'received_by_name' => $receipt->receiver?->name,
            'items' => $receipt->items->map(fn (GoodsReceiptItem $line): array => $line->only(['id', 'description', 'quantity', 'unit_cost', 'line_total', 'batch_no']))->all(),
        ];
    }

    /**
     * One asset per unit of an asset line; returns the category's asset account for the posting.
     */
    private function createAssets(GoodsReceiptItem $line, GoodsReceipt $receipt, ?PurchaseOrder $order, int $locationId): int
    {
        if (abs($line->quantity - round($line->quantity)) > 0.0005) {
            throw ValidationException::withMessages(['items' => ['Assets are received in whole numbers.']]);
        }

        $category = AssetCategory::findOrFail($line->asset_category_id);

        if ($category->asset_account_id === null) {
            throw ValidationException::withMessages(['items' => ["Set the asset account of the {$category->name} category first."]]);
        }

        for ($unit = 0; $unit < (int) round($line->quantity); $unit++) {
            Asset::create([
                'asset_tag' => $this->numberSequenceService->next('asset_tag'),
                'asset_category_id' => $category->id,
                'name' => $line->description,
                'vendor_id' => $receipt->vendor_id,
                'purchase_date' => $receipt->received_on->toDateString(),
                'purchase_cost' => $line->unit_cost,
                'salvage_value' => (int) round($line->unit_cost * (float) $category->salvage_percent / 100),
                'current_book_value' => $line->unit_cost,
                'depreciation_start_date' => $receipt->received_on->copy()->startOfMonth()->toDateString(),
                'invoice_no' => $receipt->grn_no,
                'purchase_order_id' => $order?->id,
                'location_id' => $locationId,
                'status' => 'in_store',
            ]);
        }

        return $category->asset_account_id;
    }

    private function account(string $code): int
    {
        return Account::where('code', $code)->firstOrFail()->id;
    }
}
