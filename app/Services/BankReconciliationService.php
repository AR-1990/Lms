<?php

namespace App\Services;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationLine;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

/**
 * Bank reconciliation (PLAN.md §11): a statement date and closing balance against the bank's ledger account.
 * Every posted line up to that date that no earlier reconciliation cleared is listed; ticking what the bank
 * shows leaves deposits in transit and unpresented cheques. Book balance = statement balance + uncleared
 * deposits − uncleared payments; it can be completed only when the difference is zero.
 */
class BankReconciliationService
{
    public function __construct(private ActivityLogger $activityLogger) {}

    /**
     * @return array<string, mixed>
     */
    public function datatable(): array
    {
        $query = BankReconciliation::query()
            ->select(['id', 'bank_account_id', 'statement_date', 'statement_balance', 'book_balance', 'status', 'reconciled_by', 'completed_at', 'created_at'])
            ->with(['bankAccount:id,account_title,account_number,bank_id', 'bankAccount.bank:id,name', 'reconciler:id,name'])
            ->withCount('lines');

        return DataTables::eloquent($query)
            ->addColumn('bank_label', fn (BankReconciliation $row): string => $this->bankLabel($row->bankAccount))
            ->addColumn('reconciler_name', fn (BankReconciliation $row): ?string => $row->reconciler?->name)
            ->editColumn('statement_date', fn (BankReconciliation $row): ?string => $row->statement_date?->toDateString())
            ->removeColumn('bank_account', 'reconciler')
            ->toArray();
    }

    /**
     * @param  array{bank_account_id: int, statement_date: string, statement_balance: int, remarks?: string|null}  $data
     */
    public function start(array $data, User $user): BankReconciliation
    {
        if (BankReconciliation::where('bank_account_id', $data['bank_account_id'])->where('status', 'draft')->exists()) {
            throw ValidationException::withMessages(['bank_account_id' => ['Finish or delete the open reconciliation of this bank first.']]);
        }

        $last = BankReconciliation::where('bank_account_id', $data['bank_account_id'])->where('status', 'completed')->max('statement_date');

        if ($last !== null && $data['statement_date'] <= substr((string) $last, 0, 10)) {
            throw ValidationException::withMessages(['statement_date' => ['This bank is already reconciled up to '.substr((string) $last, 0, 10).'.']]);
        }

        $reconciliation = BankReconciliation::create([...$data, 'status' => 'draft', 'reconciled_by' => $user->id]);
        $reconciliation->update(['book_balance' => $this->bookBalance($reconciliation)]);

        return $reconciliation;
    }

    /**
     * The statement with every line still to clear, the ticks so far and the totals.
     *
     * @return array<string, mixed>
     */
    public function sheet(BankReconciliation $reconciliation): array
    {
        $reconciliation->load(['bankAccount.bank:id,name', 'reconciler:id,name']);
        $cleared = $reconciliation->lines()->pluck('cleared_on', 'journal_line_id');
        $lines = $this->openLines($reconciliation)->get()->map(fn (JournalLine $line): array => [
            'id' => $line->id, 'entry_date' => substr((string) $line->entry_date, 0, 10), 'voucher_no' => $line->voucher_no,
            'narration' => $line->description ?: $line->narration, 'debit' => (int) $line->debit, 'credit' => (int) $line->credit,
            'cleared' => $cleared->has($line->id), 'cleared_on' => $cleared->has($line->id) ? substr((string) $cleared[$line->id], 0, 10) : null,
        ]);

        return [
            ...$reconciliation->attributesToArray(),
            'statement_date' => $reconciliation->statement_date?->toDateString(),
            'bank_label' => $this->bankLabel($reconciliation->bankAccount),
            'reconciler_name' => $reconciliation->reconciler?->name,
            'lines' => $lines->all(),
            ...$this->totals($reconciliation, $lines->all()),
        ];
    }

    /**
     * Save the ticks (cleared lines with the date the bank shows them); the book balance is refreshed.
     *
     * @param  array<int, array{journal_line_id: int, cleared_on?: string|null}>  $cleared
     */
    public function save(BankReconciliation $reconciliation, array $cleared, ?int $statementBalance): BankReconciliation
    {
        $this->ensureDraft($reconciliation);
        $allowed = $this->openLines($reconciliation)->pluck('journal_lines.id')->all();

        return DB::transaction(function () use ($reconciliation, $cleared, $allowed, $statementBalance): BankReconciliation {
            $reconciliation->lines()->get()->each->delete();

            foreach ($cleared as $row) {
                if (in_array((int) $row['journal_line_id'], $allowed, true)) {
                    $reconciliation->lines()->create(['journal_line_id' => $row['journal_line_id'], 'cleared_on' => $row['cleared_on'] ?? $reconciliation->statement_date]);
                }
            }

            $reconciliation->update(['book_balance' => $this->bookBalance($reconciliation), 'statement_balance' => $statementBalance ?? $reconciliation->statement_balance]);

            return $reconciliation;
        });
    }

    public function complete(BankReconciliation $reconciliation, User $user): BankReconciliation
    {
        $this->ensureDraft($reconciliation);
        $sheet = $this->sheet($reconciliation);

        if ($sheet['difference'] !== 0) {
            throw ValidationException::withMessages(['reconciliation' => ['The statement does not agree with the books yet (difference Rs '.number_format($sheet['difference'] / 100, 2).'). Tick the cleared lines, or record the bank charges / interest as vouchers first.']]);
        }

        $reconciliation->update(['status' => 'completed', 'completed_at' => now(), 'reconciled_by' => $user->id]);
        $this->activityLogger->record('bank_reconciliation', 'completed', "{$user->name} reconciled ".$this->bankLabel($reconciliation->bankAccount)." up to {$reconciliation->statement_date->toDateString()}", $reconciliation);

        return $reconciliation;
    }

    public function delete(BankReconciliation $reconciliation): void
    {
        $this->ensureDraft($reconciliation);

        DB::transaction(function () use ($reconciliation): void {
            $reconciliation->lines()->get()->each->delete();
            $reconciliation->delete();
        });
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function bankAccounts(): array
    {
        return BankAccount::with('bank:id,name')->get(['id', 'bank_id', 'account_title', 'account_number', 'account_id'])
            ->map(fn (BankAccount $account): array => ['id' => $account->id, 'name' => $this->bankLabel($account)])->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{book_balance: int, uncleared_deposits: int, uncleared_payments: int, adjusted_statement: int, difference: int}
     */
    private function totals(BankReconciliation $reconciliation, array $lines): array
    {
        $open = array_filter($lines, fn (array $line): bool => ! $line['cleared']);
        $deposits = array_sum(array_column($open, 'debit'));
        $payments = array_sum(array_column($open, 'credit'));
        $book = $this->bookBalance($reconciliation);
        $adjusted = $reconciliation->statement_balance + $deposits - $payments;

        return ['book_balance' => $book, 'uncleared_deposits' => $deposits, 'uncleared_payments' => $payments, 'adjusted_statement' => $adjusted, 'difference' => $book - $adjusted];
    }

    /**
     * Posted lines on the bank's ledger account up to the statement date that no completed reconciliation cleared.
     */
    private function openLines(BankReconciliation $reconciliation): Builder
    {
        $accountId = BankAccount::whereKey($reconciliation->bank_account_id)->value('account_id');

        return JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.account_id', $accountId)
            ->whereIn('journal_entries.status', JournalEntry::LEDGER_STATUSES)
            ->whereDate('journal_entries.entry_date', '<=', $reconciliation->statement_date)
            ->whereNotIn('journal_lines.id', BankReconciliationLine::query()
                ->whereIn('bank_reconciliation_id', BankReconciliation::where('bank_account_id', $reconciliation->bank_account_id)->where('status', 'completed')->select('id'))
                ->select('journal_line_id'))
            ->orderBy('journal_entries.entry_date')->orderBy('journal_lines.id')
            ->select(['journal_lines.id', 'journal_lines.debit', 'journal_lines.credit', 'journal_lines.description', 'journal_entries.voucher_no', 'journal_entries.entry_date', 'journal_entries.narration']);
    }

    /**
     * The bank's ledger balance on the statement date (opening balance + posted debits − credits).
     */
    private function bookBalance(BankReconciliation $reconciliation): int
    {
        $account = Account::findOrFail(BankAccount::whereKey($reconciliation->bank_account_id)->value('account_id'));
        $movement = (int) JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.account_id', $account->id)
            ->whereIn('journal_entries.status', JournalEntry::LEDGER_STATUSES)
            ->whereDate('journal_entries.entry_date', '<=', $reconciliation->statement_date)
            ->sum(DB::raw('journal_lines.debit - journal_lines.credit'));

        return ($account->opening_balance_type === 'credit' ? -(int) $account->opening_balance : (int) $account->opening_balance) + $movement;
    }

    private function ensureDraft(BankReconciliation $reconciliation): void
    {
        if ($reconciliation->status !== 'draft') {
            throw ValidationException::withMessages(['reconciliation' => ['A completed reconciliation is closed.']]);
        }
    }

    private function bankLabel(?BankAccount $account): string
    {
        return $account === null ? '—' : trim(($account->bank?->name ?? '').' · '.$account->account_title.' · '.$account->account_number, ' ·');
    }
}
