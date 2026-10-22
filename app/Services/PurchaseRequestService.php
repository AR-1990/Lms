<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

/**
 * HR purchase requests: raised with item lines and estimated prices, then approved or
 * rejected by an administrator through the approval queue.
 */
class PurchaseRequestService
{
    public function __construct(
        private NumberSequenceService $numberSequenceService,
        private ApprovalService $approvalService,
    ) {}

    /**
     * @param  array{status?: string|null, department_id?: int|null}  $filters
     */
    public function query(array $filters = []): Builder
    {
        return PurchaseRequest::query()
            ->with(['department:id,name', 'creator:id,name', 'approval:id,status,decided_by,decided_at,decision_note', 'approval.decider:id,name'])
            ->withCount('items')
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['department_id'] ?? null, fn (Builder $query, int $id) => $query->where('department_id', $id));
    }

    /**
     * @param  array{status?: string|null, department_id?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function datatable(array $filters): array
    {
        return DataTables::eloquent($this->query($filters))
            ->escapeColumns([])
            ->addColumn('department_name', fn (PurchaseRequest $purchase): ?string => $purchase->department?->name)
            ->addColumn('requested_by_name', fn (PurchaseRequest $purchase): ?string => $purchase->creator?->name)
            ->addColumn('decided_by_name', fn (PurchaseRequest $purchase): ?string => $purchase->approval?->decider?->name)
            ->editColumn('request_date', fn (PurchaseRequest $purchase): string => $purchase->request_date->toDateString())
            ->editColumn('needed_by', fn (PurchaseRequest $purchase): ?string => $purchase->needed_by?->toDateString())
            ->filterColumn('request_no', fn (Builder $query, string $keyword) => $query->where(fn (Builder $inner) => $inner
                ->where('request_no', 'like', "%{$keyword}%")
                ->orWhere('purpose', 'like', "%{$keyword}%")))
            ->removeColumn('department', 'creator', 'approval')
            ->toArray();
    }

    public function find(PurchaseRequest $purchase): PurchaseRequest
    {
        return $purchase->load(['department:id,name', 'creator:id,name', 'items.category:id,name', 'items.unit:id,name', 'approval.decider:id,name']);
    }

    /**
     * @param  array<string, mixed>  $data  fields + items[]
     */
    public function create(array $data, User $user): PurchaseRequest
    {
        return DB::transaction(function () use ($data, $user): PurchaseRequest {
            $purchase = PurchaseRequest::create([
                ...collect($data)->except('items')->all(),
                'request_no' => $this->numberSequenceService->next('purchase_request_no'),
                'request_date' => $data['request_date'] ?? now()->toDateString(),
                'status' => 'pending',
            ]);

            $this->saveItems($purchase, $data['items']);
            $this->approvalService->requestPurchase($purchase, $user);

            return $this->find($purchase->refresh());
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException when the request was already decided
     */
    public function update(PurchaseRequest $purchase, array $data): PurchaseRequest
    {
        $this->ensurePending($purchase);

        return DB::transaction(function () use ($purchase, $data): PurchaseRequest {
            $purchase->update(collect($data)->except('items')->all());
            $purchase->items()->delete();
            $this->saveItems($purchase, $data['items']);
            ApprovalRequest::whereKey($purchase->approval_request_id)->update(['amount' => $purchase->estimated_total, 'reason' => $purchase->purpose]);

            return $this->find($purchase->refresh());
        });
    }

    /**
     * Withdraw a request that is still waiting; approved purchases stay on record.
     *
     * @throws ValidationException when it was already approved
     */
    public function delete(PurchaseRequest $purchase): void
    {
        if ($purchase->status === 'approved') {
            throw ValidationException::withMessages(['purchase' => ['An approved purchase request cannot be deleted.']]);
        }

        DB::transaction(function () use ($purchase): void {
            ApprovalRequest::whereKey($purchase->approval_request_id)->where('status', 'pending')
                ->update(['status' => 'rejected', 'decided_by' => auth()->id(), 'decided_at' => now(), 'decision_note' => 'Purchase request deleted.']);
            $purchase->items()->delete();
            $purchase->delete();
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function saveItems(PurchaseRequest $purchase, array $items): void
    {
        $total = 0;

        foreach (array_values($items) as $position => $item) {
            $lineTotal = (int) round((float) $item['quantity'] * (int) ($item['estimated_unit_price'] ?? 0));
            $total += $lineTotal;

            $purchase->items()->create([...$item, 'line_total' => $lineTotal, 'sort_order' => $position]);
        }

        $purchase->update(['estimated_total' => $total]);
    }

    private function ensurePending(PurchaseRequest $purchase): void
    {
        if ($purchase->status !== 'pending') {
            throw ValidationException::withMessages(['purchase' => ["This request was already {$purchase->status}."]]);
        }
    }
}
