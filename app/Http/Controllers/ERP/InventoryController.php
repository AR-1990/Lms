<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AdjustStockRequest;
use App\Http\Requests\Inventory\CancelOrderRequest;
use App\Http\Requests\Inventory\IssueStockRequest;
use App\Http\Requests\Inventory\ReceiveGoodsRequest;
use App\Http\Requests\Inventory\ReturnStockRequest;
use App\Http\Requests\Inventory\SaveInventoryItemRequest;
use App\Http\Requests\Inventory\SaveItemCategoryRequest;
use App\Http\Requests\Inventory\SavePurchaseOrderRequest;
use App\Http\Requests\Inventory\TransferStockRequest;
use App\Models\GoodsReceipt;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\PurchaseOrder;
use App\Models\StockIssue;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Services\AssetService;
use App\Services\ProcurementService;
use App\Services\StockService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Inventory and procurement API (PLAN.md §16): manage-inventory for items, orders, receipts, transfers and
 * counts; issue-stock for issuing, selling to students and returns. Same API for web and mobile.
 */
class InventoryController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private StockService $stockService, private ProcurementService $procurementService) {}

    /* ---------- Catalogue ---------- */

    public function categoriesDatatable(): JsonResponse
    {
        return $this->successResponse($this->stockService->categoriesDatatable(), 'Item categories retrieved.');
    }

    public function storeCategory(SaveItemCategoryRequest $request): JsonResponse
    {
        return $this->successResponse($this->stockService->saveCategory(null, $request->validated()), 'Category added.', 201);
    }

    public function updateCategory(SaveItemCategoryRequest $request, ItemCategory $itemCategory): JsonResponse
    {
        return $this->successResponse($this->stockService->saveCategory($itemCategory, $request->validated()), 'Category saved.');
    }

    public function destroyCategory(ItemCategory $itemCategory): JsonResponse
    {
        return $this->deleteWithApproval($itemCategory, 'Category deleted.');
    }

    public function itemsDatatable(Request $request): JsonResponse
    {
        $filters = $request->validate(['item_category_id' => ['nullable', 'integer'], 'location_id' => ['nullable', 'integer'], 'low' => ['nullable', 'boolean']]);

        return $this->successResponse($this->stockService->itemsDatatable($filters), 'Items retrieved.');
    }

    public function showItem(InventoryItem $inventoryItem): JsonResponse
    {
        return $this->successResponse([
            ...$inventoryItem->attributesToArray(),
            'levels' => $inventoryItem->levels()->with('location:id,name')->get()->map(fn ($level): array => ['location' => $level->location?->name, 'quantity' => $level->quantity, 'average_cost' => $level->average_cost])->all(),
        ], 'Item retrieved.');
    }

    public function storeItem(SaveInventoryItemRequest $request): JsonResponse
    {
        return $this->successResponse($this->stockService->saveItem(null, $request->validated()), 'Item added.', 201);
    }

    public function updateItem(SaveInventoryItemRequest $request, InventoryItem $inventoryItem): JsonResponse
    {
        return $this->successResponse($this->stockService->saveItem($inventoryItem, $request->validated()), 'Item saved.');
    }

    public function destroyItem(InventoryItem $inventoryItem): JsonResponse
    {
        return $this->deleteWithApproval($inventoryItem, 'Item deleted.');
    }

    public function searchItems(Request $request): JsonResponse
    {
        $validated = $request->validate(['term' => ['nullable', 'string', 'max:100'], 'location_id' => ['nullable', 'integer'], 'sellable' => ['nullable', 'boolean']]);

        return $this->successResponse($this->stockService->search((string) ($validated['term'] ?? ''), $validated['location_id'] ?? null, (bool) ($validated['sellable'] ?? false)), 'Items found.');
    }

    public function movementsDatatable(Request $request): JsonResponse
    {
        $filters = $request->validate(['location_id' => ['nullable', 'integer'], 'inventory_item_id' => ['nullable', 'integer'], 'movement_type' => ['nullable', Rule::in(StockMovement::TYPES)]]);

        return $this->successResponse($this->stockService->movementsDatatable($filters), 'Stock ledger retrieved.');
    }

    /* ---------- Purchase orders and receipts ---------- */

    public function ordersDatatable(Request $request): JsonResponse
    {
        $filters = $request->validate(['status' => ['nullable', Rule::in(PurchaseOrder::STATUSES)], 'vendor_id' => ['nullable', 'integer']]);

        return $this->successResponse($this->procurementService->ordersDatatable($filters), 'Purchase orders retrieved.');
    }

    public function showOrder(PurchaseOrder $purchaseOrder): JsonResponse
    {
        return $this->successResponse($this->procurementService->order($purchaseOrder), 'Purchase order retrieved.');
    }

    public function storeOrder(SavePurchaseOrderRequest $request): JsonResponse
    {
        return $this->successResponse($this->procurementService->order($this->procurementService->saveOrder(null, $request->validated())), 'Purchase order saved as draft.', 201);
    }

    public function updateOrder(SavePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        return $this->successResponse($this->procurementService->order($this->procurementService->saveOrder($purchaseOrder, $request->validated())), 'Purchase order saved.');
    }

    public function approveOrder(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        abort_unless($request->user()->hasPermission('approve-requests'), 403, 'Only an approver can approve purchase orders.');

        return $this->successResponse($this->procurementService->order($this->procurementService->approveOrder($purchaseOrder, $request->user())), 'Purchase order approved.');
    }

    public function cancelOrder(CancelOrderRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        return $this->successResponse($this->procurementService->order($this->procurementService->cancelOrder($purchaseOrder, $request->validated('reason'), $request->user())), 'Purchase order cancelled.');
    }

    public function destroyOrder(PurchaseOrder $purchaseOrder): JsonResponse
    {
        return $this->deleteWithApproval($purchaseOrder, 'Purchase order deleted.');
    }

    public function receiptsDatatable(): JsonResponse
    {
        return $this->successResponse($this->procurementService->receiptsDatatable(), 'Goods receipts retrieved.');
    }

    public function showReceipt(GoodsReceipt $goodsReceipt): JsonResponse
    {
        return $this->successResponse($this->procurementService->receipt($goodsReceipt), 'Goods receipt retrieved.');
    }

    public function receive(ReceiveGoodsRequest $request): JsonResponse
    {
        $receipt = $this->procurementService->receive($request->validated(), $request->user());

        return $this->successResponse($this->procurementService->receipt($receipt), "Goods received as {$receipt->grn_no}.", 201);
    }

    /* ---------- Issues, returns, transfers, counts ---------- */

    public function recipients(Request $request, AssetService $assetService): JsonResponse
    {
        $validated = $request->validate(['type' => ['required', Rule::in(array_keys(StockIssue::RECIPIENTS))], 'term' => ['nullable', 'string', 'max:100']]);

        return $this->successResponse($assetService->holders($validated['type'], (string) ($validated['term'] ?? '')), 'Recipients retrieved.');
    }

    public function issuesDatatable(Request $request): JsonResponse
    {
        $filters = $request->validate(['status' => ['nullable', Rule::in(['issued', 'partially_returned', 'returned'])], 'is_sale' => ['nullable', Rule::in(['', '0', '1'])]]);
        $isSale = ($filters['is_sale'] ?? '') === '' ? null : $filters['is_sale'] === '1';

        return $this->successResponse($this->stockService->issuesDatatable(['status' => $filters['status'] ?? null, ...($isSale === null ? [] : ['is_sale' => $isSale])]), 'Issues retrieved.');
    }

    public function showIssue(StockIssue $stockIssue): JsonResponse
    {
        return $this->successResponse($this->stockService->issue($stockIssue), 'Issue retrieved.');
    }

    public function storeIssue(IssueStockRequest $request): JsonResponse
    {
        $issue = $this->stockService->createIssue($request->validated(), $request->user());

        return $this->successResponse($this->stockService->issue($issue), ($issue->is_sale ? 'Sold' : 'Issued')." as {$issue->issue_no}".($issue->fee_invoice_id ? '; the amount is on the fee challan.' : '.'), 201);
    }

    public function returnIssue(ReturnStockRequest $request, StockIssue $stockIssue): JsonResponse
    {
        $this->stockService->returnItems($stockIssue, $request->validated(), $request->user());

        return $this->successResponse($this->stockService->issue($stockIssue->refresh()), 'Items taken back into stock.');
    }

    public function overdueReturns(): JsonResponse
    {
        return $this->successResponse($this->stockService->overdueReturns()->all(), 'Overdue returns retrieved.');
    }

    public function transfersDatatable(): JsonResponse
    {
        return $this->successResponse($this->stockService->transfersDatatable(), 'Transfers retrieved.');
    }

    public function showTransfer(StockTransfer $stockTransfer): JsonResponse
    {
        $stockTransfer->load(['fromLocation:id,name', 'toLocation:id,name', 'items.item:id,sku,name']);

        return $this->successResponse([
            ...$stockTransfer->attributesToArray(),
            'transferred_on' => $stockTransfer->transferred_on?->toDateString(),
            'from_name' => $stockTransfer->fromLocation?->name,
            'to_name' => $stockTransfer->toLocation?->name,
            'items' => $stockTransfer->items->map(fn ($line): array => ['name' => $line->item?->sku.' '.$line->item?->name, 'quantity' => $line->quantity, 'unit_cost' => $line->unit_cost])->all(),
        ], 'Transfer retrieved.');
    }

    public function transfer(TransferStockRequest $request): JsonResponse
    {
        $transfer = $this->stockService->transfer($request->validated(), $request->user());

        return $this->successResponse(['id' => $transfer->id, 'transfer_no' => $transfer->transfer_no], "Stock moved ({$transfer->transfer_no}).", 201);
    }

    public function adjustmentsDatatable(): JsonResponse
    {
        return $this->successResponse($this->stockService->adjustmentsDatatable(), 'Stock counts retrieved.');
    }

    public function adjust(AdjustStockRequest $request): JsonResponse
    {
        $adjustment = $this->stockService->adjust($request->validated(), $request->user());

        return $this->successResponse(['id' => $adjustment->id, 'adjustment_no' => $adjustment->adjustment_no], "Stock count recorded ({$adjustment->adjustment_no}).", 201);
    }
}
