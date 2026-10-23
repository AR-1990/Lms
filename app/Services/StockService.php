<?php

namespace App\Services;

use App\Models\Account;
use App\Models\GoodsReceipt;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\JournalEntry;
use App\Models\Location;
use App\Models\Lookup;
use App\Models\StockAdjustment;
use App\Models\StockIssue;
use App\Models\StockIssueItem;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\StockReturn;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

/**
 * Inventory (PLAN.md §16): item categories and items, stock per store with weighted average cost, the stock
 * ledger, and the documents that move stock — issues (and sales to students on a fee challan), returns,
 * transfers and counts. Purchase orders and goods receipts are in ProcurementService.
 */
class StockService
{
    public const INVENTORY_ACCOUNT = '1500';

    public const EXPENSE_ACCOUNT = '5600';

    public const OTHER_INCOME_ACCOUNT = '4900';

    public const OPENING_EQUITY_ACCOUNT = '3300';

    public function __construct(
        private NumberSequenceService $numberSequenceService,
        private JournalService $journalService,
        private FeeInvoiceService $feeInvoiceService,
        private NotificationService $notificationService,
        private ActivityLogger $activityLogger,
    ) {}

    /* ---------- Catalogue ---------- */

    /**
     * @return array<string, mixed>
     */
    public function categoriesDatatable(): array
    {
        $query = ItemCategory::query()
            ->select(['id', 'name', 'parent_id', 'inventory_account_id', 'expense_account_id', 'income_account_id', 'sale_fee_head_id', 'is_active', 'created_at'])
            ->with(['parent:id,name', 'expenseAccount:id,code,name', 'saleFeeHead:id,name'])
            ->withCount('items');

        return DataTables::eloquent($query)
            ->addColumn('parent_name', fn (ItemCategory $row): ?string => $row->parent?->name)
            ->addColumn('expense_account_name', fn (ItemCategory $row): ?string => $row->expenseAccount ? $row->expenseAccount->code.' '.$row->expenseAccount->name : null)
            ->addColumn('sale_fee_head_name', fn (ItemCategory $row): ?string => $row->saleFeeHead?->name)
            ->removeColumn('parent', 'expense_account', 'sale_fee_head')
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveCategory(?ItemCategory $category, array $data): ItemCategory
    {
        $category ??= new ItemCategory(['is_active' => true]);
        $category->fill($data)->save();

        return $category;
    }

    public function deleteCategory(ItemCategory $category): void
    {
        if ($category->items()->exists() || ItemCategory::where('parent_id', $category->id)->exists()) {
            throw ValidationException::withMessages(['category' => ['This category still has items or sub-categories.']]);
        }

        $category->delete();
    }

    /**
     * Items with the stock across all stores (or one store) and a low-stock flag.
     *
     * @param  array{item_category_id?: int|null, location_id?: int|null, low?: bool}  $filters
     * @return array<string, mixed>
     */
    public function itemsDatatable(array $filters): array
    {
        $locationId = $filters['location_id'] ?? null;
        $query = InventoryItem::query()
            ->select(['id', 'sku', 'item_category_id', 'name', 'unit_id', 'brand', 'size', 'color', 'reorder_level', 'reorder_quantity', 'standard_cost', 'sale_price', 'is_sellable', 'is_returnable', 'is_active', 'created_at'])
            ->with(['category:id,name', 'unit:id,name,code'])
            ->withSum(['levels as on_hand' => fn (Builder $query) => $query->when($locationId, fn (Builder $inner) => $inner->where('location_id', $locationId))], 'quantity')
            ->when($filters['item_category_id'] ?? null, fn (Builder $query, int $id) => $query->where('item_category_id', $id))
            ->when($filters['low'] ?? false, fn (Builder $query) => $query->where('reorder_level', '>', 0)
                ->whereRaw('coalesce((select sum(quantity) from stock_levels where stock_levels.inventory_item_id = inventory_items.id'.($locationId ? ' and stock_levels.location_id = '.(int) $locationId : '').'), 0) <= inventory_items.reorder_level'));

        return DataTables::eloquent($query)
            ->addColumn('category_name', fn (InventoryItem $item): ?string => $item->category?->name)
            ->addColumn('unit_name', fn (InventoryItem $item): ?string => $item->unit?->code ?? $item->unit?->name)
            ->addColumn('is_low', fn (InventoryItem $item): bool => $item->reorder_level > 0 && (float) $item->on_hand <= $item->reorder_level)
            ->editColumn('on_hand', fn (InventoryItem $item): float => (float) $item->on_hand)
            ->removeColumn('category', 'unit')
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveItem(?InventoryItem $item, array $data): InventoryItem
    {
        return DB::transaction(function () use ($item, $data): InventoryItem {
            $item ??= new InventoryItem(['is_active' => true]);
            // Optional numbers left empty mean zero (the columns are not nullable).
            $item->fill(collect($data)->except('sku')->map(fn ($value, string $key) => in_array($key, ['reorder_level', 'reorder_quantity', 'standard_cost', 'sale_price'], true) ? ($value ?? 0) : $value)->all());
            $item->sku = filled($data['sku'] ?? null) ? $data['sku'] : ($item->sku ?? $this->numberSequenceService->next('sku'));
            $item->save();

            return $item;
        });
    }

    public function deleteItem(InventoryItem $item): void
    {
        if ($item->movements()->exists()) {
            throw ValidationException::withMessages(['item' => ['This item has stock history. Mark it inactive instead.']]);
        }

        $item->delete();
    }

    /**
     * Item search for document lines, with the quantity and average cost in a store.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $term, ?int $locationId, bool $sellableOnly = false): array
    {
        return InventoryItem::where('is_active', true)
            ->when($sellableOnly, fn (Builder $query) => $query->where('is_sellable', true))
            ->where(fn (Builder $query) => $query->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%")->orWhere('barcode', $term))
            ->with(['unit:id,code,name', 'levels' => fn ($query) => $query->when($locationId, fn ($inner) => $inner->where('location_id', $locationId))])
            ->orderBy('name')->limit(20)
            ->get(['id', 'sku', 'name', 'unit_id', 'standard_cost', 'sale_price', 'is_returnable'])
            ->map(fn (InventoryItem $item): array => [
                'id' => $item->id, 'sku' => $item->sku, 'name' => $item->name, 'unit' => $item->unit?->code ?? $item->unit?->name,
                'on_hand' => (float) $item->levels->sum('quantity'), 'average_cost' => (int) ($item->levels->first()?->average_cost ?? $item->standard_cost),
                'standard_cost' => $item->standard_cost, 'sale_price' => $item->sale_price, 'is_returnable' => $item->is_returnable,
            ])->all();
    }

    /**
     * @param  array{location_id?: int|null, inventory_item_id?: int|null, movement_type?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function movementsDatatable(array $filters): array
    {
        $query = StockMovement::query()
            ->select(['id', 'inventory_item_id', 'location_id', 'movement_type', 'quantity', 'unit_cost', 'balance_after', 'reference_type', 'reference_id', 'moved_on', 'created_by', 'created_at'])
            ->with(['item:id,sku,name', 'location:id,name', 'creator:id,name'])
            ->when($filters['location_id'] ?? null, fn (Builder $query, int $id) => $query->where('location_id', $id))
            ->when($filters['inventory_item_id'] ?? null, fn (Builder $query, int $id) => $query->where('inventory_item_id', $id))
            ->when($filters['movement_type'] ?? null, fn (Builder $query, string $type) => $query->where('movement_type', $type));

        return DataTables::eloquent($query)
            ->addColumn('item_label', fn (StockMovement $row): string => $row->item ? $row->item->sku.' '.$row->item->name : '—')
            ->addColumn('location_name', fn (StockMovement $row): ?string => $row->location?->name)
            ->addColumn('creator_name', fn (StockMovement $row): ?string => $row->creator?->name)
            ->addColumn('document', fn (StockMovement $row): ?string => $this->documentNo($row->reference_type, $row->reference_id))
            ->filterColumn('item_label', fn (Builder $query, string $keyword) => $query->whereHas('item', fn (Builder $inner) => $inner->where('name', 'like', "%{$keyword}%")->orWhere('sku', 'like', "%{$keyword}%")))
            ->editColumn('moved_on', fn (StockMovement $row): ?string => $row->moved_on?->toDateString())
            ->removeColumn('item', 'location', 'creator')
            ->toArray();
    }

    /* ---------- The one place stock changes ---------- */

    /**
     * Move stock in (+) or out (−) of a store, keep the weighted average cost and write the stock ledger.
     * Returns the unit cost used (the average for outgoing stock).
     */
    public function move(InventoryItem $item, int $locationId, string $type, float $quantity, int $unitCost, Model $reference, string $date, ?string $batchNo = null, ?string $expiryDate = null): int
    {
        $level = StockLevel::where('inventory_item_id', $item->id)->where('location_id', $locationId)->lockForUpdate()->first()
            ?? StockLevel::create(['inventory_item_id' => $item->id, 'location_id' => $locationId, 'quantity' => 0, 'average_cost' => $item->standard_cost]);
        $onHand = (float) $level->quantity;

        if ($quantity < 0 && $onHand + $quantity < -0.0005) {
            throw ValidationException::withMessages(['items' => ["Only {$this->qty($onHand)} of {$item->name} in ".Location::whereKey($locationId)->value('name').'.']]);
        }

        $cost = $quantity > 0 ? $unitCost : $level->average_cost;
        $newQuantity = round($onHand + $quantity, 3);

        if ($quantity > 0) {
            $level->average_cost = $onHand <= 0 ? $unitCost : (int) round(($onHand * $level->average_cost + $quantity * $unitCost) / ($onHand + $quantity));
        }

        $level->quantity = $newQuantity;
        $level->save();

        StockMovement::create([
            'inventory_item_id' => $item->id, 'location_id' => $locationId, 'movement_type' => $type, 'quantity' => $quantity, 'unit_cost' => $cost,
            'balance_after' => $newQuantity, 'batch_no' => $batchNo, 'expiry_date' => $expiryDate,
            'reference_type' => class_basename($reference), 'reference_id' => $reference->getKey(), 'moved_on' => $date, 'created_by' => auth()->id(),
        ]);

        if ($quantity < 0 && $item->reorder_level > 0 && $newQuantity <= $item->reorder_level && $onHand > $item->reorder_level) {
            $this->notificationService->notify(
                User::where('is_active', true)->whereHas('roles.permissions', fn (Builder $query) => $query->where('slug', 'manage-inventory'))->pluck('id')->all(),
                'stock_low', "{$item->name} is running low", "{$this->qty($newQuantity)} left (reorder level {$this->qty($item->reorder_level)}).", route('admin.inventory.index'), $item,
            );
        }

        return $cost;
    }

    /* ---------- Issues and sales ---------- */

    /**
     * @param  array{status?: string|null, is_sale?: bool|null}  $filters
     * @return array<string, mixed>
     */
    public function issuesDatatable(array $filters): array
    {
        $query = StockIssue::query()
            ->select(['id', 'issue_no', 'issued_on', 'location_id', 'recipient_type', 'recipient_id', 'purpose', 'is_sale', 'fee_invoice_id', 'total_cost', 'sale_total', 'status', 'journal_entry_id', 'created_at'])
            ->with(['location:id,name', 'feeInvoice:id,invoice_no', 'journalEntry:id,voucher_no'])
            ->withCount('items')
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when(isset($filters['is_sale']), fn (Builder $query) => $query->where('is_sale', $filters['is_sale']));

        return DataTables::eloquent($query)
            ->addColumn('recipient_name', fn (StockIssue $issue): ?string => $issue->recipientName())
            ->addColumn('location_name', fn (StockIssue $issue): ?string => $issue->location?->name)
            ->addColumn('invoice_no', fn (StockIssue $issue): ?string => $issue->feeInvoice?->invoice_no)
            ->addColumn('voucher_no', fn (StockIssue $issue): ?string => $issue->journalEntry?->voucher_no)
            ->editColumn('issued_on', fn (StockIssue $issue): ?string => $issue->issued_on?->toDateString())
            ->removeColumn('location', 'fee_invoice', 'journal_entry')
            ->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function issue(StockIssue $issue): array
    {
        $issue->load(['location:id,name', 'items.item:id,sku,name,unit_id,is_returnable', 'items.item.unit:id,code,name', 'feeInvoice:id,invoice_no', 'journalEntry:id,voucher_no', 'issuer:id,name']);

        return [
            ...$issue->attributesToArray(),
            'issued_on' => $issue->issued_on?->toDateString(),
            'recipient_name' => $issue->recipientName(),
            'location_name' => $issue->location?->name,
            'invoice_no' => $issue->feeInvoice?->invoice_no,
            'voucher_no' => $issue->journalEntry?->voucher_no,
            'issued_by_name' => $issue->issuer?->name,
            'items' => $issue->items->map(fn (StockIssueItem $line): array => [
                'id' => $line->id, 'sku' => $line->item?->sku, 'name' => $line->item?->name, 'unit' => $line->item?->unit?->code,
                'quantity' => $line->quantity, 'returned_quantity' => $line->returned_quantity, 'unit_cost' => $line->unit_cost, 'sale_price' => $line->sale_price,
                'due_back_on' => $line->due_back_on?->toDateString(), 'is_returnable' => (bool) $line->item?->is_returnable,
            ])->all(),
        ];
    }

    /**
     * Issue stock from a store. A sale to a student adds the items to a fee challan at their sale price.
     *
     * @param  array{issued_on: string, location_id: int, recipient_type: string, recipient_id: int, purpose?: string|null, purchase_request_id?: int|null, received_signature?: bool, is_sale?: bool, due_date?: string|null, items: array<int, array{inventory_item_id: int, quantity: float, sale_price?: int|null, due_back_on?: string|null}>}  $data
     */
    public function createIssue(array $data, User $user): StockIssue
    {
        $isSale = (bool) ($data['is_sale'] ?? false);

        if ($isSale && $data['recipient_type'] !== 'student') {
            throw ValidationException::withMessages(['is_sale' => ['Only students can be sold to (the amount goes on their fee challan).']]);
        }

        StockIssue::RECIPIENTS[$data['recipient_type']]::findOrFail($data['recipient_id']);

        return DB::transaction(function () use ($data, $user, $isSale): StockIssue {
            $issue = StockIssue::create([
                ...collect($data)->except(['items', 'due_date'])->all(),
                'issue_no' => $this->numberSequenceService->next('issue_no'),
                'issued_by' => $user->id,
                'is_sale' => $isSale,
                'status' => 'issued',
            ]);
            $costs = [];
            $saleLines = [];

            foreach ($data['items'] as $row) {
                $item = InventoryItem::findOrFail($row['inventory_item_id']);
                $quantity = (float) $row['quantity'];
                $salePrice = $isSale ? (int) ($row['sale_price'] ?? $item->sale_price) : 0;

                if ($isSale && ! $item->is_sellable) {
                    throw ValidationException::withMessages(['items' => ["{$item->name} is not for sale."]]);
                }

                $cost = $this->move($item, (int) $data['location_id'], $isSale ? 'sale_out' : 'issue_out', -$quantity, 0, $issue, $data['issued_on']);
                $issue->items()->create(['inventory_item_id' => $item->id, 'quantity' => $quantity, 'unit_cost' => $cost, 'sale_price' => $salePrice, 'due_back_on' => $row['due_back_on'] ?? null]);
                $costs[$item->item_category_id] = ($costs[$item->item_category_id] ?? 0) + (int) round($quantity * $cost);

                if ($isSale) {
                    $saleLines[] = ['category_id' => $item->item_category_id, 'amount' => (int) round($quantity * $salePrice), 'description' => $item->name.' × '.$this->qty($quantity)];
                }
            }

            $totalCost = array_sum($costs);
            $issue->total_cost = $totalCost;
            $issue->sale_total = array_sum(array_column($saleLines, 'amount'));

            if ($totalCost > 0) {
                $issue->journal_entry_id = $this->postCost($costs, 'expense', $data['issued_on'], ($isSale ? 'Cost of items sold' : 'Stock issued').' — '.$issue->issue_no.' to '.$issue->recipientName(), $issue)->id;
            }

            if ($isSale && $issue->sale_total > 0) {
                $issue->fee_invoice_id = $this->feeInvoiceService->manual([
                    'student_id' => (int) $data['recipient_id'],
                    'issue_date' => $data['issued_on'],
                    'due_date' => $data['due_date'] ?? $data['issued_on'],
                    'notes' => 'Items sold — '.$issue->issue_no,
                    'items' => collect($saleLines)->map(fn (array $line): array => [
                        'fee_head_id' => ItemCategory::whereKey($line['category_id'])->value('sale_fee_head_id') ?? throw ValidationException::withMessages(['items' => ['Set the fee head for sales on the item category first.']]),
                        'amount' => $line['amount'],
                        'description' => $line['description'],
                    ])->all(),
                ])->id;
            }

            $issue->save();
            $this->activityLogger->record('inventory', $isSale ? 'sold' : 'issued', "{$user->name} ".($isSale ? 'sold' : 'issued')." {$issue->issue_no} to {$issue->recipientName()}", $issue);

            return $issue;
        });
    }

    /**
     * Take items back against an issue at the cost they went out; sold items are not taken back here
     * (cancel or adjust the fee challan instead).
     *
     * @param  array{returned_on: string, condition_id?: int|null, remarks?: string|null, items: array<int, array{stock_issue_item_id: int, quantity: float}>}  $data
     */
    public function returnItems(StockIssue $issue, array $data, User $user): StockReturn
    {
        if ($issue->is_sale) {
            throw ValidationException::withMessages(['issue' => ['Sold items are corrected on the fee challan, not returned here.']]);
        }

        return DB::transaction(function () use ($issue, $data, $user): StockReturn {
            $return = $issue->returns()->create(['returned_on' => $data['returned_on'], 'received_by' => $user->id, 'condition_id' => $data['condition_id'] ?? null, 'remarks' => $data['remarks'] ?? null]);
            $costs = [];

            foreach ($data['items'] as $row) {
                $line = StockIssueItem::where('stock_issue_id', $issue->id)->whereKey($row['stock_issue_item_id'])->firstOrFail();
                $quantity = (float) $row['quantity'];

                if ($quantity <= 0) {
                    continue;
                }

                if ($line->returned_quantity + $quantity > $line->quantity + 0.0005) {
                    throw ValidationException::withMessages(['items' => ['More is being returned than was issued.']]);
                }

                $item = InventoryItem::findOrFail($line->inventory_item_id);
                $this->move($item, $issue->location_id, 'return_in', $quantity, $line->unit_cost, $return, $data['returned_on']);
                $return->items()->create(['stock_issue_item_id' => $line->id, 'quantity' => $quantity, 'condition_id' => $data['condition_id'] ?? null]);
                $line->increment('returned_quantity', $quantity);
                $costs[$item->item_category_id] = ($costs[$item->item_category_id] ?? 0) + (int) round($quantity * $line->unit_cost);
            }

            if (array_sum($costs) > 0) {
                $return->update(['journal_entry_id' => $this->postCost($costs, 'return', $data['returned_on'], "Stock returned against {$issue->issue_no}", $return)->id]);
            }

            $fullyBack = $issue->items()->get()->every(fn (StockIssueItem $line): bool => $line->returned_quantity >= $line->quantity - 0.0005);
            $issue->update(['status' => $fullyBack ? 'returned' : 'partially_returned']);
            $this->activityLogger->record('inventory', 'returned', "{$user->name} took back items against {$issue->issue_no}", $return);

            return $return;
        });
    }

    /**
     * Returnable items still out past their due-back date (e.g. sports kit).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function overdueReturns(): Collection
    {
        return StockIssueItem::whereNotNull('due_back_on')->whereDate('due_back_on', '<', today())->whereColumn('returned_quantity', '<', 'quantity')
            ->with(['issue:id,issue_no,recipient_type,recipient_id', 'item:id,name'])->limit(100)->get()
            ->map(fn (StockIssueItem $line): array => ['issue_no' => $line->issue?->issue_no, 'recipient' => $line->issue?->recipientName(), 'item' => $line->item?->name, 'outstanding' => $line->quantity - $line->returned_quantity, 'due_back_on' => $line->due_back_on?->toDateString()]);
    }

    /* ---------- Transfers and counts ---------- */

    /**
     * @return array<string, mixed>
     */
    public function transfersDatatable(): array
    {
        $query = StockTransfer::query()
            ->select(['id', 'transfer_no', 'from_location_id', 'to_location_id', 'transferred_on', 'status', 'remarks', 'created_at'])
            ->with(['fromLocation:id,name', 'toLocation:id,name'])
            ->withCount('items');

        return DataTables::eloquent($query)
            ->addColumn('from_name', fn (StockTransfer $row): ?string => $row->fromLocation?->name)
            ->addColumn('to_name', fn (StockTransfer $row): ?string => $row->toLocation?->name)
            ->editColumn('transferred_on', fn (StockTransfer $row): ?string => $row->transferred_on?->toDateString())
            ->removeColumn('from_location', 'to_location')
            ->toArray();
    }

    /**
     * @param  array{from_location_id: int, to_location_id: int, transferred_on: string, remarks?: string|null, items: array<int, array{inventory_item_id: int, quantity: float}>}  $data
     */
    public function transfer(array $data, User $user): StockTransfer
    {
        return DB::transaction(function () use ($data, $user): StockTransfer {
            $transfer = StockTransfer::create([...collect($data)->except('items')->all(), 'transfer_no' => $this->numberSequenceService->next('transfer_no'), 'status' => 'completed']);

            foreach ($data['items'] as $row) {
                $item = InventoryItem::findOrFail($row['inventory_item_id']);
                $cost = $this->move($item, (int) $data['from_location_id'], 'transfer_out', -(float) $row['quantity'], 0, $transfer, $data['transferred_on']);
                $this->move($item, (int) $data['to_location_id'], 'transfer_in', (float) $row['quantity'], $cost, $transfer, $data['transferred_on']);
                $transfer->items()->create(['inventory_item_id' => $item->id, 'quantity' => $row['quantity'], 'unit_cost' => $cost]);
            }

            $this->activityLogger->record('inventory', 'transferred', "{$user->name} moved stock ({$transfer->transfer_no})", $transfer);

            return $transfer;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function adjustmentsDatatable(): array
    {
        $query = StockAdjustment::query()
            ->select(['id', 'adjustment_no', 'location_id', 'adjusted_on', 'reason_id', 'approved_by', 'journal_entry_id', 'remarks', 'created_at'])
            ->with(['location:id,name', 'reason:id,name', 'approver:id,name', 'journalEntry:id,voucher_no'])
            ->withCount('items');

        return DataTables::eloquent($query)
            ->addColumn('location_name', fn (StockAdjustment $row): ?string => $row->location?->name)
            ->addColumn('reason_name', fn (StockAdjustment $row): ?string => $row->reason?->name)
            ->addColumn('approver_name', fn (StockAdjustment $row): ?string => $row->approver?->name)
            ->addColumn('voucher_no', fn (StockAdjustment $row): ?string => $row->journalEntry?->voucher_no)
            ->editColumn('adjusted_on', fn (StockAdjustment $row): ?string => $row->adjusted_on?->toDateString())
            ->removeColumn('location', 'reason', 'approver', 'journal_entry')
            ->toArray();
    }

    /**
     * Record a count: each item is set to its counted quantity. Shortages are expensed; excess goes to other
     * income, or to opening balance equity when the reason is "Opening Stock" (unit cost taken from the line).
     *
     * @param  array{location_id: int, adjusted_on: string, reason_id?: int|null, remarks?: string|null, items: array<int, array{inventory_item_id: int, counted_qty: float, unit_cost?: int|null}>}  $data
     */
    public function adjust(array $data, User $user): StockAdjustment
    {
        $isOpening = isset($data['reason_id']) && Lookup::whereKey($data['reason_id'])->value('name') === 'Opening Stock';

        return DB::transaction(function () use ($data, $user, $isOpening): StockAdjustment {
            $adjustment = StockAdjustment::create([...collect($data)->except('items')->all(), 'adjustment_no' => $this->numberSequenceService->next('adjustment_no'), 'approved_by' => $user->id]);
            $short = [];
            $excess = [];

            foreach ($data['items'] as $row) {
                $item = InventoryItem::findOrFail($row['inventory_item_id']);
                $level = StockLevel::where('inventory_item_id', $item->id)->where('location_id', $data['location_id'])->first();
                $system = (float) ($level->quantity ?? 0);
                $difference = round((float) $row['counted_qty'] - $system, 3);

                if (abs($difference) < 0.0005) {
                    continue;
                }

                $unitCost = $difference > 0 ? (int) ($row['unit_cost'] ?? ($level->average_cost ?? $item->standard_cost)) : (int) ($level->average_cost ?? $item->standard_cost);
                $cost = $this->move($item, (int) $data['location_id'], $isOpening ? 'opening' : 'adjustment', $difference, $unitCost, $adjustment, $data['adjusted_on']);
                $adjustment->items()->create(['inventory_item_id' => $item->id, 'system_qty' => $system, 'counted_qty' => $row['counted_qty'], 'difference' => $difference, 'unit_cost' => $cost]);
                $value = (int) round(abs($difference) * $cost);

                if ($difference < 0) {
                    $short[$item->item_category_id] = ($short[$item->item_category_id] ?? 0) + $value;
                } else {
                    $excess[$item->item_category_id] = ($excess[$item->item_category_id] ?? 0) + $value;
                }
            }

            if (! $adjustment->items()->exists()) {
                throw ValidationException::withMessages(['items' => ['Every count matches the system; nothing to adjust.']]);
            }

            $lines = [];

            foreach ($short as $categoryId => $amount) {
                $category = ItemCategory::find($categoryId);
                $lines[] = ['account_id' => $category?->expense_account_id ?? $this->account(self::EXPENSE_ACCOUNT), 'debit' => $amount, 'description' => 'Stock shortage · '.$category?->name];
                $lines[] = ['account_id' => $category?->inventory_account_id ?? $this->account(self::INVENTORY_ACCOUNT), 'credit' => $amount, 'description' => 'Stock shortage · '.$category?->name];
            }

            foreach ($excess as $categoryId => $amount) {
                $category = ItemCategory::find($categoryId);
                $lines[] = ['account_id' => $category?->inventory_account_id ?? $this->account(self::INVENTORY_ACCOUNT), 'debit' => $amount, 'description' => ($isOpening ? 'Opening stock · ' : 'Stock excess · ').$category?->name];
                $lines[] = ['account_id' => $this->account($isOpening ? self::OPENING_EQUITY_ACCOUNT : self::OTHER_INCOME_ACCOUNT), 'credit' => $amount, 'description' => ($isOpening ? 'Opening stock · ' : 'Stock excess · ').$category?->name];
            }

            if (collect($lines)->sum('debit') > 0) {
                $adjustment->update(['journal_entry_id' => $this->journalService->record('JV', $data['adjusted_on'], "Stock count {$adjustment->adjustment_no}".($data['remarks'] ?? null ? ': '.$data['remarks'] : ''), $lines, $adjustment)->id]);
            }

            $this->activityLogger->record('inventory', 'adjusted', "{$user->name} recorded the stock count {$adjustment->adjustment_no}", $adjustment);

            return $adjustment;
        });
    }

    /* ---------- Helpers ---------- */

    /**
     * Post the cost of stock leaving (expense ← inventory) or coming back (inventory ← expense), per category.
     *
     * @param  array<int, int>  $costs  category id => paisa
     */
    private function postCost(array $costs, string $direction, string $date, string $narration, Model $source): JournalEntry
    {
        $lines = [];

        foreach (array_filter($costs) as $categoryId => $amount) {
            $category = ItemCategory::find($categoryId);
            $expense = ['account_id' => $category?->expense_account_id ?? $this->account(self::EXPENSE_ACCOUNT), 'description' => $category?->name];
            $inventory = ['account_id' => $category?->inventory_account_id ?? $this->account(self::INVENTORY_ACCOUNT), 'description' => $category?->name];
            $lines[] = $direction === 'expense' ? [...$expense, 'debit' => $amount] : [...$inventory, 'debit' => $amount];
            $lines[] = $direction === 'expense' ? [...$inventory, 'credit' => $amount] : [...$expense, 'credit' => $amount];
        }

        return $this->journalService->record('JV', $date, $narration, $lines, $source);
    }

    private function documentNo(?string $type, ?int $id): ?string
    {
        return match ($type) {
            'StockIssue' => StockIssue::withTrashed()->whereKey($id)->value('issue_no'),
            'StockReturn' => 'Return of '.StockIssue::withTrashed()->whereKey(StockReturn::withTrashed()->whereKey($id)->value('stock_issue_id'))->value('issue_no'),
            'StockTransfer' => StockTransfer::withTrashed()->whereKey($id)->value('transfer_no'),
            'StockAdjustment' => StockAdjustment::withTrashed()->whereKey($id)->value('adjustment_no'),
            'GoodsReceipt' => GoodsReceipt::withTrashed()->whereKey($id)->value('grn_no'),
            default => null,
        };
    }

    private function qty(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }

    private function account(string $code): int
    {
        return Account::where('code', $code)->firstOrFail()->id;
    }
}
