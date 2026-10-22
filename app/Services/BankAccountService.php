<?php

namespace App\Services;

use App\Models\BankAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class BankAccountService
{
    public function query(): Builder
    {
        return BankAccount::query()
            ->select(['id', 'account_id', 'bank_id', 'branch', 'account_title', 'account_number', 'iban', 'branch_code', 'is_default_for_fees', 'created_at', 'updated_at'])
            ->with(['account:id,code,name', 'bank:id,name']);
    }

    /**
     * @return array<string, mixed>
     */
    public function datatable(): array
    {
        return DataTables::eloquent($this->query())
            ->escapeColumns([])
            ->addColumn('bank_name', fn (BankAccount $bankAccount): ?string => $bankAccount->bank?->name)
            ->addColumn('account_label', fn (BankAccount $bankAccount): ?string => $bankAccount->account ? $bankAccount->account->code.' '.$bankAccount->account->name : null)
            ->removeColumn('account', 'bank')
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): BankAccount
    {
        return DB::transaction(function () use ($data): BankAccount {
            $bankAccount = BankAccount::create([...$data, 'is_default_for_fees' => $data['is_default_for_fees'] ?? false]);
            $this->keepSingleDefault($bankAccount);

            return $bankAccount->load(['account:id,code,name', 'bank:id,name']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(BankAccount $bankAccount, array $data): BankAccount
    {
        return DB::transaction(function () use ($bankAccount, $data): BankAccount {
            $bankAccount->update($data);
            $this->keepSingleDefault($bankAccount);

            return $bankAccount->load(['account:id,code,name', 'bank:id,name']);
        });
    }

    public function delete(BankAccount $bankAccount): void
    {
        DB::transaction(fn () => $bankAccount->delete());
    }

    /**
     * Fee challans print one default bank account.
     */
    private function keepSingleDefault(BankAccount $bankAccount): void
    {
        if (! $bankAccount->is_default_for_fees) {
            return;
        }

        BankAccount::where('is_default_for_fees', true)->whereKeyNot($bankAccount->id)->get()
            ->each(fn (BankAccount $other) => $other->update(['is_default_for_fees' => false]));
    }
}
