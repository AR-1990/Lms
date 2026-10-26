<?php

namespace App\Services;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class PaymentMethodService
{
    /**
     * @param  array{is_active?: string|null}  $filters
     */
    public function query(array $filters = []): Builder
    {
        return PaymentMethod::query()
            ->select(['id', 'name', 'code', 'account_id', 'requires_reference', 'requires_bank_details', 'is_online', 'payment_instructions', 'charges_percent', 'is_active', 'sort_order', 'created_at', 'updated_at'])
            ->with('account:id,code,name')
            ->when(isset($filters['is_active']) && $filters['is_active'] !== '', fn (Builder $query) => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)));
    }

    /**
     * Active methods in display order, for receipt and payment forms.
     *
     * @return Collection<int, PaymentMethod>
     */
    public function active(): Collection
    {
        return $this->query(['is_active' => '1'])->orderBy('sort_order')->orderBy('name')->get();
    }

    /**
     * @param  array{is_active?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function datatable(array $filters): array
    {
        return DataTables::eloquent($this->query($filters))
            ->escapeColumns([])
            ->addColumn('account_label', fn (PaymentMethod $method): ?string => $method->account ? $method->account->code.' '.$method->account->name : null)
            ->removeColumn('account')
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PaymentMethod
    {
        return DB::transaction(fn (): PaymentMethod => PaymentMethod::create([
            ...$data,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? ((int) PaymentMethod::max('sort_order') + 10),
            'requires_reference' => $data['requires_reference'] ?? false,
            'requires_bank_details' => $data['requires_bank_details'] ?? false,
            'is_online' => $data['is_online'] ?? false,
        ])->load('account:id,code,name'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PaymentMethod $method, array $data): PaymentMethod
    {
        return DB::transaction(function () use ($method, $data): PaymentMethod {
            $method->update($data);

            return $method->load('account:id,code,name');
        });
    }

    /**
     * Fee receipts will block this once they exist (PLAN.md phase 5).
     */
    public function delete(PaymentMethod $method): void
    {
        DB::transaction(fn () => $method->delete());
    }
}
