<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountType;
use App\Models\BankAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\PaymentMethod;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class AccountService
{
    /**
     * Chart of accounts with each account's posted movement (debit − credit) as a subquery.
     *
     * @param  array{account_type_id?: int|null, is_active?: string|null}  $filters
     */
    public function query(array $filters = []): Builder
    {
        return Account::query()
            ->select(['id', 'account_type_id', 'parent_id', 'code', 'name', 'is_group', 'is_bank_account', 'is_cash_account', 'is_system', 'opening_balance', 'opening_balance_type', 'description', 'is_active', 'created_at', 'updated_at'])
            ->addSelect(['movement' => $this->movementQuery()->whereColumn('journal_lines.account_id', 'accounts.id')])
            ->with(['accountType:id,name,normal_balance', 'parent:id,code,name'])
            ->withCount('children')
            ->when($filters['account_type_id'] ?? null, fn (Builder $query, int $typeId) => $query->where('account_type_id', $typeId))
            ->when(isset($filters['is_active']) && $filters['is_active'] !== '', fn (Builder $query) => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)));
    }

    /**
     * @param  array{account_type_id?: int|null, is_active?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function datatable(array $filters): array
    {
        return DataTables::eloquent($this->query($filters))
            ->escapeColumns([])
            ->addColumn('account_type_name', fn (Account $account): ?string => $account->accountType?->name)
            ->addColumn('parent_name', fn (Account $account): ?string => $account->parent ? $account->parent->code.' '.$account->parent->name : null)
            ->addColumn('balance', fn (Account $account): ?int => $account->is_group ? null : $this->balanceOf($account))
            ->removeColumn('account_type', 'parent', 'movement')
            ->toArray();
    }

    /**
     * Postable accounts and account types for dropdowns (web forms and the mobile app).
     *
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return [
            'account_types' => AccountType::orderBy('id')->get(['id', 'name', 'normal_balance']),
            'group_accounts' => Account::where('is_group', true)->orderBy('code')->get(['id', 'code', 'name', 'account_type_id']),
            'postable_accounts' => Account::where('is_group', false)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'account_type_id', 'is_bank_account', 'is_cash_account']),
        ];
    }

    public function find(Account $account): Account
    {
        return $this->query()->whereKey($account->id)->firstOrFail();
    }

    /**
     * Signed balance in paisa: debit-side positive, credit-side negative.
     */
    public function balanceOf(Account $account): int
    {
        $opening = $account->opening_balance_type === 'credit' ? -$account->opening_balance : (int) $account->opening_balance;

        return $opening + (int) ($account->movement ?? $this->movementQuery()->where('journal_lines.account_id', $account->id)->value('movement'));
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function create(array $data): Account
    {
        $this->checkPlacement(null, $data);

        return DB::transaction(fn (): Account => $this->find(Account::create([
            ...$data,
            'is_group' => $data['is_group'] ?? false,
            'is_bank_account' => $data['is_bank_account'] ?? false,
            'is_cash_account' => $data['is_cash_account'] ?? false,
            'is_system' => false,
            'opening_balance' => ($data['is_group'] ?? false) ? 0 : ($data['opening_balance'] ?? 0),
            'is_active' => $data['is_active'] ?? true,
        ])));
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function update(Account $account, array $data): Account
    {
        $this->checkPlacement($account, $data);

        $isGroup = (bool) ($data['is_group'] ?? $account->is_group);

        if ($account->is_system && ((int) ($data['account_type_id'] ?? $account->account_type_id) !== $account->account_type_id || $isGroup !== $account->is_group)) {
            throw ValidationException::withMessages(['account' => ['Built-in accounts keep their type and group setting.']]);
        }

        if ($isGroup && ! $account->is_group && $account->journalLines()->exists()) {
            throw ValidationException::withMessages(['is_group' => ['This account already has entries, so it cannot become a group heading.']]);
        }

        if (! $isGroup && $account->is_group && $account->children()->exists()) {
            throw ValidationException::withMessages(['is_group' => ['This group still has accounts under it.']]);
        }

        if ((int) ($data['account_type_id'] ?? $account->account_type_id) !== $account->account_type_id && ($account->journalLines()->exists() || $account->children()->exists())) {
            throw ValidationException::withMessages(['account_type_id' => ['The type cannot change once the account has entries or sub-accounts.']]);
        }

        return DB::transaction(function () use ($account, $data, $isGroup): Account {
            $account->update([...$data, 'opening_balance' => $isGroup ? 0 : ($data['opening_balance'] ?? $account->opening_balance)]);

            return $this->find($account);
        });
    }

    /**
     * @throws ValidationException when the account is built in or still referenced anywhere
     */
    public function delete(Account $account): void
    {
        $blockers = [
            'Built-in accounts cannot be deleted.' => $account->is_system,
            'This group still has accounts under it.' => $account->children()->exists(),
            'This account has journal entries. Mark it inactive instead.' => $account->journalLines()->exists(),
            'A payment method, bank account or vendor still uses this account.' => PaymentMethod::where('account_id', $account->id)->exists()
                || BankAccount::where('account_id', $account->id)->exists()
                || Vendor::where('payable_account_id', $account->id)->exists(),
        ];

        foreach ($blockers as $message => $blocked) {
            if ($blocked) {
                throw ValidationException::withMessages(['account' => [$message]]);
            }
        }

        DB::transaction(fn () => $account->delete());
    }

    /**
     * Account ledger grid for a date range, with opening / closing balances in the payload.
     *
     * @return array<string, mixed>
     */
    public function ledger(Account $account, ?string $from, ?string $to): array
    {
        $lines = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.account_id', $account->id)
            ->whereIn('journal_entries.status', JournalEntry::LEDGER_STATUSES)
            ->select([
                'journal_lines.id', 'journal_lines.debit', 'journal_lines.credit', 'journal_lines.description',
                'journal_entries.id as journal_entry_id', 'journal_entries.voucher_no', 'journal_entries.entry_date', 'journal_entries.narration',
            ]);

        $before = $from === null ? 0 : (int) (clone $lines)->whereDate('journal_entries.entry_date', '<', $from)->sum(DB::raw('journal_lines.debit - journal_lines.credit'));
        $period = (clone $lines)
            ->when($from, fn (Builder $query) => $query->whereDate('journal_entries.entry_date', '>=', $from))
            ->when($to, fn (Builder $query) => $query->whereDate('journal_entries.entry_date', '<=', $to));
        $totals = (clone $period)->toBase()->cloneWithout(['columns', 'orders'])->selectRaw('coalesce(sum(journal_lines.debit), 0) as debit, coalesce(sum(journal_lines.credit), 0) as credit')->first();

        $opening = ($account->opening_balance_type === 'credit' ? -$account->opening_balance : (int) $account->opening_balance) + $before;

        return DataTables::eloquent($period)
            ->escapeColumns([])
            ->filterColumn('voucher_no', fn (Builder $query, string $keyword) => $query->where('journal_entries.voucher_no', 'like', "%{$keyword}%"))
            ->filterColumn('narration', fn (Builder $query, string $keyword) => $query->where(fn (Builder $inner) => $inner
                ->where('journal_entries.narration', 'like', "%{$keyword}%")
                ->orWhere('journal_lines.description', 'like', "%{$keyword}%")))
            ->orderColumn('entry_date', 'journal_entries.entry_date $1, journal_entries.id $1')
            ->editColumn('entry_date', fn (JournalLine $line): string => substr((string) $line->entry_date, 0, 10))
            ->with('summary', [
                'account' => ['id' => $account->id, 'code' => $account->code, 'name' => $account->name],
                'opening_balance' => $opening,
                'total_debit' => (int) $totals->debit,
                'total_credit' => (int) $totals->credit,
                'closing_balance' => $opening + (int) $totals->debit - (int) $totals->credit,
            ])
            ->toArray();
    }

    /**
     * Trial balance as of a date: every postable account with a non-zero balance.
     *
     * @return array{as_of: string, rows: array<int, array<string, mixed>>, total_debit: int, total_credit: int, is_balanced: bool}
     */
    public function trialBalance(string $asOf): array
    {
        $movements = $this->movementQuery()
            ->whereDate('journal_entries.entry_date', '<=', $asOf)
            ->addSelect('journal_lines.account_id')
            ->groupBy('journal_lines.account_id')
            ->pluck('movement', 'account_id');

        $rows = Account::query()
            ->where('is_group', false)
            ->with('accountType:id,name')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'account_type_id', 'opening_balance', 'opening_balance_type'])
            ->map(function (Account $account) use ($movements): array {
                $balance = ($account->opening_balance_type === 'credit' ? -$account->opening_balance : (int) $account->opening_balance) + (int) ($movements[$account->id] ?? 0);

                return [
                    'account_id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'account_type' => $account->accountType?->name,
                    'debit' => max($balance, 0),
                    'credit' => max(-$balance, 0),
                ];
            })
            ->filter(fn (array $row): bool => $row['debit'] !== 0 || $row['credit'] !== 0)
            ->values();

        $totalDebit = (int) $rows->sum('debit');
        $totalCredit = (int) $rows->sum('credit');

        return [
            'as_of' => $asOf,
            'rows' => $rows->all(),
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'is_balanced' => $totalDebit === $totalCredit,
        ];
    }

    /**
     * Sum of posted debit − credit; callers add the account / date conditions.
     */
    private function movementQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->whereIn('journal_entries.status', JournalEntry::LEDGER_STATUSES)
            ->selectRaw('coalesce(sum(journal_lines.debit - journal_lines.credit), 0) as movement');
    }

    /**
     * Parent must be a group of the same type, and an account can never sit under itself.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function checkPlacement(?Account $account, array $data): void
    {
        $parentId = array_key_exists('parent_id', $data) ? $data['parent_id'] : $account?->parent_id;

        if ($parentId === null) {
            return;
        }

        $parent = Account::find($parentId);

        if ($parent === null || ! $parent->is_group) {
            throw ValidationException::withMessages(['parent_id' => ['The parent must be a group account.']]);
        }

        if ($parent->account_type_id !== (int) ($data['account_type_id'] ?? $account?->account_type_id)) {
            throw ValidationException::withMessages(['parent_id' => ['The parent belongs to a different account type.']]);
        }

        for ($ancestor = $parent; $account !== null && $ancestor !== null; $ancestor = $ancestor->parent) {
            if ($ancestor->id === $account->id) {
                throw ValidationException::withMessages(['parent_id' => ['An account cannot be placed under itself or one of its sub-accounts.']]);
            }
        }
    }
}
