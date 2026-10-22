<?php

namespace App\Services;

use App\Models\Account;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\VoucherType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

/**
 * The double-entry engine. Every money event in every module ends up here through
 * createDraft()/post() (manual vouchers) or record() (automatic postings from fees, payroll…).
 */
class JournalService
{
    public function __construct(
        private NumberSequenceService $numberSequenceService,
        private ActivityLogger $activityLogger,
    ) {}

    /**
     * @param  array{status?: string|null, voucher_type_id?: int|null, date_from?: string|null, date_to?: string|null}  $filters
     */
    public function query(array $filters = []): Builder
    {
        return JournalEntry::query()
            ->select(['id', 'voucher_no', 'voucher_type_id', 'fiscal_year_id', 'entry_date', 'narration', 'total_debit', 'total_credit', 'status', 'posted_at', 'reversed_by_entry_id', 'reverses_entry_id', 'source_type', 'created_at'])
            ->with('voucherType:id,name,code')
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['voucher_type_id'] ?? null, fn (Builder $query, int $typeId) => $query->where('voucher_type_id', $typeId))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('entry_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('entry_date', '<=', $date));
    }

    /**
     * @param  array{status?: string|null, voucher_type_id?: int|null, date_from?: string|null, date_to?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function datatable(array $filters): array
    {
        return DataTables::eloquent($this->query($filters))
            ->escapeColumns([])
            ->addColumn('voucher_type_code', fn (JournalEntry $entry): ?string => $entry->voucherType?->code)
            ->addColumn('is_automatic', fn (JournalEntry $entry): bool => $entry->source_type !== null)
            ->editColumn('entry_date', fn (JournalEntry $entry): string => $entry->entry_date->toDateString())
            ->removeColumn('voucher_type', 'source_type')
            ->toArray();
    }

    public function find(JournalEntry $entry): JournalEntry
    {
        return $entry->load([
            'lines.account:id,code,name',
            'voucherType:id,name,code',
            'fiscalYear:id,name',
            'postedBy:id,name',
            'reversedBy:id,voucher_no',
            'reverses:id,voucher_no',
        ]);
    }

    /**
     * @param  array{voucher_type_id: int, entry_date: string, narration?: string|null, lines: array<int, array{account_id: int, debit?: int|null, credit?: int|null, description?: string|null}>}  $data
     *
     * @throws ValidationException when the voucher does not balance or its date has no open fiscal year
     */
    public function createDraft(array $data, ?Model $source = null): JournalEntry
    {
        $lines = $this->cleanLines($data['lines']);
        $fiscalYear = $this->openFiscalYearFor($data['entry_date']);
        $voucherType = VoucherType::findOrFail($data['voucher_type_id']);

        return DB::transaction(function () use ($data, $lines, $fiscalYear, $voucherType, $source): JournalEntry {
            $entry = JournalEntry::create([
                'voucher_no' => $this->numberSequenceService->next($voucherType->number_sequence_type),
                'voucher_type_id' => $voucherType->id,
                'fiscal_year_id' => $fiscalYear->id,
                'entry_date' => $data['entry_date'],
                'narration' => $data['narration'] ?? null,
                'source_type' => $source ? $source::class : null,
                'source_id' => $source?->getKey(),
                'total_debit' => array_sum(array_column($lines, 'debit')),
                'total_credit' => array_sum(array_column($lines, 'credit')),
                'status' => JournalEntry::STATUS_DRAFT,
            ]);

            $entry->lines()->createMany($lines);

            return $this->find($entry);
        });
    }

    /**
     * @param  array{voucher_type_id?: int, entry_date: string, narration?: string|null, lines: array<int, array<string, mixed>>}  $data
     *
     * @throws ValidationException for vouchers that are no longer drafts
     */
    public function updateDraft(JournalEntry $entry, array $data): JournalEntry
    {
        $this->ensureDraft($entry, 'edited');

        $lines = $this->cleanLines($data['lines']);
        $fiscalYear = $this->openFiscalYearFor($data['entry_date']);

        return DB::transaction(function () use ($entry, $data, $lines, $fiscalYear): JournalEntry {
            $entry->update([
                'fiscal_year_id' => $fiscalYear->id,
                'entry_date' => $data['entry_date'],
                'narration' => $data['narration'] ?? null,
                'total_debit' => array_sum(array_column($lines, 'debit')),
                'total_credit' => array_sum(array_column($lines, 'credit')),
            ]);

            $entry->lines()->delete();
            $entry->lines()->createMany($lines);

            return $this->find($entry->refresh());
        });
    }

    /**
     * @throws ValidationException for vouchers that are no longer drafts
     */
    public function deleteDraft(JournalEntry $entry): void
    {
        $this->ensureDraft($entry, 'deleted');

        DB::transaction(function () use ($entry): void {
            $entry->lines()->delete();
            $entry->delete();
        });
    }

    /**
     * Post a draft to the ledger. From here on the voucher can only be reversed.
     *
     * @throws ValidationException
     */
    public function post(JournalEntry $entry): JournalEntry
    {
        $this->ensureDraft($entry, 'posted again');
        $this->openFiscalYearFor($entry->entry_date->toDateString());

        return DB::transaction(function () use ($entry): JournalEntry {
            $entry->forceFill(['status' => JournalEntry::STATUS_POSTED, 'posted_by' => auth()->id(), 'posted_at' => now()])->saveQuietly();

            $this->activityLogger->record(
                'vouchers',
                'posted',
                (auth()->user()?->name ?? 'System')." posted voucher {$entry->voucher_no}",
                $entry,
                ['status' => ['old' => JournalEntry::STATUS_DRAFT, 'new' => JournalEntry::STATUS_POSTED]],
            );

            return $this->find($entry);
        });
    }

    /**
     * Cancel a posted voucher with an equal and opposite posted voucher dated $date (today by default).
     *
     * @throws ValidationException
     */
    public function reverse(JournalEntry $entry, string $reason, ?string $date = null): JournalEntry
    {
        if ($entry->status !== JournalEntry::STATUS_POSTED) {
            throw ValidationException::withMessages(['voucher' => [
                $entry->status === JournalEntry::STATUS_REVERSED ? 'This voucher has already been reversed.' : 'Only posted vouchers can be reversed. Delete or edit the draft instead.',
            ]]);
        }

        $date ??= now()->toDateString();
        $fiscalYear = $this->openFiscalYearFor($date);

        return DB::transaction(function () use ($entry, $reason, $date, $fiscalYear): JournalEntry {
            $entry->loadMissing('lines', 'voucherType');

            $reversal = JournalEntry::create([
                'voucher_no' => $this->numberSequenceService->next($entry->voucherType->number_sequence_type),
                'voucher_type_id' => $entry->voucher_type_id,
                'fiscal_year_id' => $fiscalYear->id,
                'entry_date' => $date,
                'narration' => "Reversal of {$entry->voucher_no}: {$reason}",
                'total_debit' => $entry->total_credit,
                'total_credit' => $entry->total_debit,
                'status' => JournalEntry::STATUS_POSTED,
                'posted_by' => auth()->id(),
                'posted_at' => now(),
                'reverses_entry_id' => $entry->id,
            ]);

            $reversal->lines()->createMany($entry->lines->map(fn ($line): array => [
                'account_id' => $line->account_id,
                'debit' => $line->credit,
                'credit' => $line->debit,
                'description' => $line->description,
                'party_type' => $line->party_type,
                'party_id' => $line->party_id,
                'cost_center_id' => $line->cost_center_id,
                'line_no' => $line->line_no,
            ])->all());

            $entry->forceFill([
                'status' => JournalEntry::STATUS_REVERSED,
                'reversed_by_entry_id' => $reversal->id,
                'reversal_reason' => $reason,
            ])->saveQuietly();

            $this->activityLogger->record(
                'vouchers',
                'reversed',
                (auth()->user()?->name ?? 'System')." reversed voucher {$entry->voucher_no} with {$reversal->voucher_no}",
                $entry,
                ['status' => ['old' => JournalEntry::STATUS_POSTED, 'new' => JournalEntry::STATUS_REVERSED], 'reason' => ['old' => null, 'new' => $reason]],
            );

            return $this->find($reversal);
        });
    }

    /**
     * Create and post in one step — the entry point for automatic postings from other modules.
     *
     * @param  array<int, array{account_id: int, debit?: int|null, credit?: int|null, description?: string|null, party_type?: string|null, party_id?: int|null}>  $lines
     */
    public function record(string $voucherTypeCode, string $entryDate, string $narration, array $lines, ?Model $source = null): JournalEntry
    {
        return DB::transaction(fn (): JournalEntry => $this->post($this->createDraft([
            'voucher_type_id' => VoucherType::where('code', $voucherTypeCode)->firstOrFail()->id,
            'entry_date' => $entryDate,
            'narration' => $narration,
            'lines' => $lines,
        ], $source)));
    }

    /**
     * Validate the double-entry rules and shape the lines for saving.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     *
     * @throws ValidationException
     */
    private function cleanLines(array $lines): array
    {
        $errors = [];
        $clean = [];

        foreach (array_values($lines) as $index => $line) {
            $debit = (int) ($line['debit'] ?? 0);
            $credit = (int) ($line['credit'] ?? 0);

            if (($debit > 0) === ($credit > 0)) {
                $errors["lines.{$index}.debit"] = ['Enter either a debit or a credit amount on each line.'];
            }

            $clean[] = [
                'account_id' => (int) $line['account_id'],
                'debit' => $debit,
                'credit' => $credit,
                'description' => $line['description'] ?? null,
                'party_type' => $line['party_type'] ?? null,
                'party_id' => $line['party_id'] ?? null,
                'line_no' => $index + 1,
            ];
        }

        $postable = Account::whereIn('id', array_column($clean, 'account_id'))
            ->where('is_group', false)
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        foreach ($clean as $index => $line) {
            if (! in_array($line['account_id'], $postable, true)) {
                $errors["lines.{$index}.account_id"] = ['Choose an active account that is not a group heading.'];
            }
        }

        $totalDebit = array_sum(array_column($clean, 'debit'));
        $totalCredit = array_sum(array_column($clean, 'credit'));

        if (count($clean) < 2) {
            $errors['lines'] = ['A voucher needs at least two lines.'];
        } elseif ($errors === [] && $totalDebit !== $totalCredit) {
            $errors['lines'] = ['Debits ('.$this->money($totalDebit).') and credits ('.$this->money($totalCredit).') must be equal.'];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $clean;
    }

    /**
     * @throws ValidationException when no open fiscal year covers the date
     */
    private function openFiscalYearFor(string $date): FiscalYear
    {
        $day = Carbon::parse($date)->toDateString();
        $fiscalYear = FiscalYear::whereDate('starts_on', '<=', $day)->whereDate('ends_on', '>=', $day)->first();

        if ($fiscalYear === null) {
            throw ValidationException::withMessages(['entry_date' => ['No fiscal year covers this date. Add it under Fiscal Years first.']]);
        }

        if ($fiscalYear->status !== FiscalYear::STATUS_OPEN) {
            throw ValidationException::withMessages(['entry_date' => ["{$fiscalYear->name} is closed. Reopen it or use a date in an open year."]]);
        }

        return $fiscalYear;
    }

    /**
     * @throws ValidationException
     */
    private function ensureDraft(JournalEntry $entry, string $action): void
    {
        if ($entry->status !== JournalEntry::STATUS_DRAFT) {
            throw ValidationException::withMessages(['voucher' => ["A {$entry->status} voucher cannot be {$action}. Reverse it instead."]]);
        }
    }

    private function money(int $paisa): string
    {
        return 'Rs '.number_format($paisa / 100, 2);
    }
}
