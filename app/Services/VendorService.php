<?php

namespace App\Services;

use App\Models\Vendor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class VendorService
{
    /**
     * @param  array{is_active?: string|null, search?: string|null}  $filters
     */
    public function query(array $filters = []): Builder
    {
        return Vendor::query()
            ->select(['id', 'name', 'contact_person', 'phone', 'email', 'address', 'ntn', 'strn', 'payable_account_id', 'bank_name', 'bank_account_no', 'is_active', 'created_at', 'updated_at'])
            ->with('payableAccount:id,code,name')
            ->when(isset($filters['is_active']) && $filters['is_active'] !== '', fn (Builder $query) => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)))
            ->when(filled($filters['search'] ?? null), fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('name', 'like', '%'.$filters['search'].'%')
                ->orWhere('phone', 'like', '%'.$filters['search'].'%')
                ->orWhere('ntn', 'like', '%'.$filters['search'].'%')));
    }

    /**
     * @param  array{is_active?: string|null, search?: string|null}  $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query($filters)->orderBy('name')->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @param  array{is_active?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function datatable(array $filters): array
    {
        return DataTables::eloquent($this->query(['is_active' => $filters['is_active'] ?? null]))
            ->escapeColumns([])
            ->addColumn('payable_account_label', fn (Vendor $vendor): ?string => $vendor->payableAccount ? $vendor->payableAccount->code.' '.$vendor->payableAccount->name : null)
            ->removeColumn('payable_account')
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Vendor
    {
        return DB::transaction(fn (): Vendor => Vendor::create([...$data, 'is_active' => $data['is_active'] ?? true])->load('payableAccount:id,code,name'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Vendor $vendor, array $data): Vendor
    {
        return DB::transaction(function () use ($vendor, $data): Vendor {
            $vendor->update($data);

            return $vendor->load('payableAccount:id,code,name');
        });
    }

    /**
     * Expenses and purchases will block this once they exist (PLAN.md phases 15 and 17).
     */
    public function delete(Vendor $vendor): void
    {
        DB::transaction(fn () => $vendor->delete());
    }
}
