<?php

namespace App\Services;

use App\Models\NumberSequence;
use App\Models\VoucherType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class VoucherTypeService
{
    public function query(): Builder
    {
        return VoucherType::query()
            ->select(['id', 'name', 'code', 'number_sequence_type', 'is_system', 'created_at', 'updated_at'])
            ->withCount('journalEntries');
    }

    /**
     * @return array<string, mixed>
     */
    public function datatable(): array
    {
        return DataTables::eloquent($this->query())->escapeColumns([])->toArray();
    }

    /**
     * A new voucher type gets its own number series (e.g. code `BRV` → BRV-2026-00001).
     *
     * @param  array{name: string, code: string}  $data
     */
    public function create(array $data): VoucherType
    {
        return DB::transaction(function () use ($data): VoucherType {
            $sequenceType = 'voucher_'.strtolower(str_replace('-', '_', $data['code']));

            NumberSequence::firstOrCreate(['type' => $sequenceType], [
                'label' => $data['name'],
                'prefix' => $data['code'].'-',
                'include_year' => true,
                'padding' => 5,
                'next_number' => 1,
                'reset_yearly' => true,
                'current_year' => (int) now()->format('Y'),
            ]);

            return VoucherType::create([...$data, 'number_sequence_type' => $sequenceType, 'is_system' => false])->loadCount('journalEntries');
        });
    }

    /**
     * Only the name changes; the code and number series stay fixed.
     *
     * @param  array{name: string}  $data
     */
    public function update(VoucherType $voucherType, array $data): VoucherType
    {
        return DB::transaction(function () use ($voucherType, $data): VoucherType {
            $voucherType->update(['name' => $data['name']]);

            return $voucherType->loadCount('journalEntries');
        });
    }

    /**
     * @throws ValidationException for built-in types and types already used on vouchers
     */
    public function delete(VoucherType $voucherType): void
    {
        if ($voucherType->is_system || $voucherType->journalEntries()->exists()) {
            throw ValidationException::withMessages(['voucher_type' => [$voucherType->is_system ? 'Built-in voucher types cannot be deleted.' : 'This type is already used on vouchers.']]);
        }

        DB::transaction(fn () => $voucherType->delete());
    }
}
