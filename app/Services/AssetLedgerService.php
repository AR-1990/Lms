<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetDepreciation;
use App\Models\AssetDisposal;
use App\Models\AssetMaintenance;
use App\Models\JournalEntry;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

/**
 * Asset postings to the ledger: purchase (asset ← payable or cash/bank), repair cost (expense ← payable or
 * cash/bank), monthly depreciation (expense ← accumulated depreciation, one JV per month) and disposal
 * (removes cost and accumulated depreciation, books the sale and the gain or loss).
 */
class AssetLedgerService
{
    public const PAYABLE_ACCOUNT = '2100';

    public const DEPRECIATION_EXPENSE_ACCOUNT = '5500';

    public const ACCUMULATED_DEPRECIATION_ACCOUNT = '1690';

    public const REPAIRS_ACCOUNT = '5700';

    public const GAIN_ACCOUNT = '4910';

    public const LOSS_ACCOUNT = '5810';

    public function __construct(private JournalService $journalService, private ActivityLogger $activityLogger) {}

    public function postPurchase(Asset $asset, ?int $paymentMethodId): JournalEntry
    {
        $category = AssetCategory::findOrFail($asset->asset_category_id);

        if ($category->asset_account_id === null) {
            throw ValidationException::withMessages(['asset_category_id' => ["Set the asset account of the {$category->name} category before posting a purchase."]]);
        }

        $entry = $this->journalService->record('JV', ($asset->purchase_date ?? today())->toDateString(), "Purchase of {$asset->asset_tag} {$asset->name}".($asset->invoice_no ? " (invoice {$asset->invoice_no})" : ''), [
            ['account_id' => $category->asset_account_id, 'debit' => $asset->purchase_cost, 'description' => $asset->name],
            $this->creditSide($asset->purchase_cost, $asset->vendor_id, $paymentMethodId, $asset->name),
        ], $asset);
        $asset->update(['journal_entry_id' => $entry->id]);

        return $entry;
    }

    public function postRepair(AssetMaintenance $job, ?int $paymentMethodId): JournalEntry
    {
        $asset = Asset::findOrFail($job->asset_id);
        $entry = $this->journalService->record('JV', $job->completed_on->toDateString(), "Repair of {$asset->asset_tag} {$asset->name}", [
            ['account_id' => $this->account(self::REPAIRS_ACCOUNT), 'debit' => $job->cost, 'description' => $job->description ?: 'Repair'],
            $this->creditSide($job->cost, $job->vendor_id, $paymentMethodId, 'Repair of '.$asset->asset_tag),
        ], $job);
        $job->update(['journal_entry_id' => $entry->id]);

        return $entry;
    }

    /* ---------- Depreciation ---------- */

    /**
     * What a month's run would charge, per asset (nothing is saved).
     *
     * @return array{month: string, already_posted: bool, total: int, rows: array<int, array<string, mixed>>}
     */
    public function preview(string $month): array
    {
        $start = Carbon::parse($month)->startOfMonth();
        $rows = $this->charges($start);

        return [
            'month' => $start->format('F Y'),
            'already_posted' => AssetDepreciation::whereDate('period_month', $start)->exists(),
            'total' => array_sum(array_column($rows, 'amount')),
            'rows' => $rows,
        ];
    }

    /**
     * Charge the month for every asset that still has value above salvage; one JV for the month.
     */
    public function run(string $month, User $user): ?JournalEntry
    {
        $start = Carbon::parse($month)->startOfMonth();

        if ($start->isAfter(today()->startOfMonth())) {
            throw ValidationException::withMessages(['month' => ['A future month cannot be depreciated.']]);
        }

        $rows = $this->charges($start);

        if ($rows === []) {
            throw ValidationException::withMessages(['month' => ['Nothing to depreciate for '.$start->format('F Y').'.']]);
        }

        return DB::transaction(function () use ($start, $rows, $user): JournalEntry {
            $lines = [];

            foreach (collect($rows)->groupBy('category_id') as $categoryRows) {
                $category = AssetCategory::find($categoryRows->first()['category_id']);
                $amount = (int) $categoryRows->sum('amount');
                $lines[] = ['account_id' => $category?->depreciation_account_id ?? $this->account(self::DEPRECIATION_EXPENSE_ACCOUNT), 'debit' => $amount, 'description' => 'Depreciation · '.($category?->name ?? 'Assets')];
                $lines[] = ['account_id' => $category?->accumulated_depreciation_account_id ?? $this->account(self::ACCUMULATED_DEPRECIATION_ACCOUNT), 'credit' => $amount, 'description' => 'Accumulated depreciation · '.($category?->name ?? 'Assets')];
            }

            $entry = $this->journalService->record('JV', $start->copy()->endOfMonth()->toDateString(), 'Depreciation for '.$start->format('F Y').' ('.count($rows).' assets)', $lines);

            foreach ($rows as $row) {
                AssetDepreciation::create(['asset_id' => $row['asset_id'], 'period_month' => $start->toDateString(), 'amount' => $row['amount'], 'book_value_after' => $row['book_value_after'], 'journal_entry_id' => $entry->id]);
                Asset::whereKey($row['asset_id'])->update(['current_book_value' => $row['book_value_after']]);
            }

            $this->activityLogger->record('assets', 'depreciated', "{$user->name} posted depreciation for {$start->format('F Y')} as {$entry->voucher_no}", $entry);

            return $entry;
        });
    }

    /**
     * Undo the latest posted month: reversal voucher, book values restored, rows soft deleted.
     */
    public function reverse(string $month, string $reason, User $user): void
    {
        $start = Carbon::parse($month)->startOfMonth();
        $rows = AssetDepreciation::whereDate('period_month', $start)->get();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['month' => ['Nothing was posted for '.$start->format('F Y').'.']]);
        }

        if (AssetDepreciation::whereDate('period_month', '>', $start)->exists()) {
            throw ValidationException::withMessages(['month' => ['Reverse the later months first.']]);
        }

        DB::transaction(function () use ($rows, $reason, $start, $user): void {
            foreach ($rows->pluck('journal_entry_id')->filter()->unique() as $entryId) {
                $this->journalService->reverse(JournalEntry::findOrFail($entryId), $reason);
            }

            foreach ($rows as $row) {
                Asset::whereKey($row->asset_id)->increment('current_book_value', $row->amount);
                $row->delete();
            }

            $this->activityLogger->record('assets', 'reversed', "{$user->name} reversed the depreciation of {$start->format('F Y')}: {$reason}");
        });
    }

    /**
     * Months already posted, newest first.
     *
     * @return array<string, mixed>
     */
    public function runsDatatable(): array
    {
        $query = AssetDepreciation::query()
            ->selectRaw('min(id) as id, period_month, count(*) as assets, sum(amount) as total, max(journal_entry_id) as journal_entry_id, min(created_at) as created_at')
            ->groupBy('period_month');

        return DataTables::eloquent($query)
            ->addColumn('month_label', fn (AssetDepreciation $row): string => Carbon::parse($row->period_month)->format('F Y'))
            ->addColumn('voucher_no', fn (AssetDepreciation $row): ?string => JournalEntry::whereKey($row->journal_entry_id)->value('voucher_no'))
            ->editColumn('period_month', fn (AssetDepreciation $row): string => Carbon::parse($row->period_month)->toDateString())
            ->toArray();
    }

    /* ---------- Disposal ---------- */

    /**
     * Sell, scrap, donate or write off an asset (it must be in store, in repair or lost).
     *
     * @param  array{disposed_on: string, method: string, sale_amount?: int|null, payment_method_id?: int|null, reason: string}  $data
     */
    public function dispose(Asset $asset, array $data, User $user): AssetDisposal
    {
        if (! in_array($asset->status, ['in_store', 'lost'], true)) {
            throw ValidationException::withMessages(['asset' => ['Take the asset back (or close its repair) before disposing of it.']]);
        }

        $sale = (int) ($data['sale_amount'] ?? 0);

        if ($sale > 0 && empty($data['payment_method_id'])) {
            throw ValidationException::withMessages(['payment_method_id' => ['Choose how the sale money was received.']]);
        }

        return DB::transaction(function () use ($asset, $data, $sale, $user): AssetDisposal {
            $disposal = AssetDisposal::create([...$data, 'asset_id' => $asset->id, 'sale_amount' => $sale, 'book_value_at_disposal' => $asset->current_book_value, 'approved_by' => $user->id]);

            if ($asset->purchase_cost > 0 || $sale > 0) {
                $disposal->update(['journal_entry_id' => $this->postDisposal($asset, $disposal)->id]);
            }

            $asset->update(['status' => 'disposed', 'current_holder_type' => null, 'current_holder_id' => null, 'current_book_value' => 0]);
            $this->activityLogger->record('assets', 'disposed', "{$user->name} disposed of {$asset->asset_tag} {$asset->name} ({$data['method']}): {$data['reason']}", $asset);

            return $disposal;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function disposalsDatatable(): array
    {
        $query = AssetDisposal::query()
            ->select(['id', 'asset_id', 'disposed_on', 'method', 'sale_amount', 'book_value_at_disposal', 'reason', 'approved_by', 'journal_entry_id', 'created_at'])
            ->with(['asset:id,asset_tag,name', 'approver:id,name', 'journalEntry:id,voucher_no']);

        return DataTables::eloquent($query)
            ->addColumn('asset_label', fn (AssetDisposal $row): string => $row->asset ? $row->asset->asset_tag.' '.$row->asset->name : '—')
            ->addColumn('approver_name', fn (AssetDisposal $row): ?string => $row->approver?->name)
            ->addColumn('voucher_no', fn (AssetDisposal $row): ?string => $row->journalEntry?->voucher_no)
            ->addColumn('gain_loss', fn (AssetDisposal $row): int => $row->sale_amount - $row->book_value_at_disposal)
            ->filterColumn('asset_label', fn ($query, string $keyword) => $query->whereHas('asset', fn ($inner) => $inner->where('asset_tag', 'like', "%{$keyword}%")->orWhere('name', 'like', "%{$keyword}%")))
            ->editColumn('disposed_on', fn (AssetDisposal $row): ?string => $row->disposed_on?->toDateString())
            ->removeColumn('asset', 'approver', 'journal_entry')
            ->toArray();
    }

    private function postDisposal(Asset $asset, AssetDisposal $disposal): JournalEntry
    {
        $category = AssetCategory::findOrFail($asset->asset_category_id);

        if ($asset->purchase_cost > 0 && $category->asset_account_id === null) {
            throw ValidationException::withMessages(['asset' => ["Set the asset account of the {$category->name} category before disposing."]]);
        }

        $accumulated = $asset->purchase_cost - $asset->current_book_value;
        $gainOrLoss = $disposal->sale_amount - $asset->current_book_value;
        $lines = array_values(array_filter([
            $accumulated > 0 ? ['account_id' => $category->accumulated_depreciation_account_id ?? $this->account(self::ACCUMULATED_DEPRECIATION_ACCOUNT), 'debit' => $accumulated, 'description' => 'Accumulated depreciation removed'] : null,
            $disposal->sale_amount > 0 ? ['account_id' => PaymentMethod::findOrFail($disposal->payment_method_id)->account_id, 'debit' => $disposal->sale_amount, 'description' => 'Sale proceeds'] : null,
            $asset->purchase_cost > 0 ? ['account_id' => $category->asset_account_id, 'credit' => $asset->purchase_cost, 'description' => 'Cost removed'] : null,
            $gainOrLoss > 0 ? ['account_id' => $this->account(self::GAIN_ACCOUNT), 'credit' => $gainOrLoss, 'description' => 'Gain on disposal'] : null,
            $gainOrLoss < 0 ? ['account_id' => $this->account(self::LOSS_ACCOUNT), 'debit' => -$gainOrLoss, 'description' => 'Loss on disposal'] : null,
        ]));

        return $this->journalService->record('JV', $disposal->disposed_on->toDateString(), "Disposal of {$asset->asset_tag} {$asset->name} ({$disposal->method}): {$disposal->reason}", $lines, $disposal);
    }

    /**
     * Each asset's charge for the month (straight line or reducing balance), never below salvage.
     *
     * @return array<int, array{asset_id: int, asset_tag: string, name: string, category_id: int, category: string|null, book_value: int, amount: int, book_value_after: int}>
     */
    private function charges(Carbon $start): array
    {
        $posted = AssetDepreciation::whereDate('period_month', $start)->pluck('asset_id')->all();

        return Asset::whereNotIn('status', ['disposed', 'lost'])
            ->whereNotIn('id', $posted)
            ->whereDate('depreciation_start_date', '<=', $start->copy()->endOfMonth())
            ->whereColumn('current_book_value', '>', 'salvage_value')
            ->whereHas('category', fn ($query) => $query->where('depreciation_method', '!=', 'none')->where('useful_life_months', '>', 0))
            ->with('category:id,name,depreciation_method,useful_life_months')
            ->orderBy('asset_tag')
            ->get(['id', 'asset_tag', 'name', 'asset_category_id', 'purchase_cost', 'salvage_value', 'current_book_value'])
            ->map(function (Asset $asset): ?array {
                $life = (int) $asset->category->useful_life_months;
                $monthly = $asset->category->depreciation_method === 'reducing_balance'
                    ? (int) round($asset->current_book_value * 2 / $life)
                    : (int) round(($asset->purchase_cost - $asset->salvage_value) / $life);
                $amount = min($monthly, $asset->current_book_value - $asset->salvage_value);

                return $amount <= 0 ? null : [
                    'asset_id' => $asset->id, 'asset_tag' => $asset->asset_tag, 'name' => $asset->name, 'category_id' => $asset->asset_category_id,
                    'category' => $asset->category->name, 'book_value' => $asset->current_book_value, 'amount' => $amount, 'book_value_after' => $asset->current_book_value - $amount,
                ];
            })->filter()->values()->all();
    }

    /**
     * The credit line of a purchase or repair: the payment method's cash/bank account, else the vendor's payable.
     *
     * @return array<string, mixed>
     */
    private function creditSide(int $amount, ?int $vendorId, ?int $paymentMethodId, string $description): array
    {
        if ($paymentMethodId !== null) {
            return ['account_id' => PaymentMethod::findOrFail($paymentMethodId)->account_id, 'credit' => $amount, 'description' => $description];
        }

        $vendor = $vendorId ? Vendor::find($vendorId) : null;

        return ['account_id' => $vendor?->payable_account_id ?? $this->account(self::PAYABLE_ACCOUNT), 'credit' => $amount, 'description' => $description, 'party_type' => $vendor ? Vendor::class : null, 'party_id' => $vendor?->id];
    }

    private function account(string $code): int
    {
        return Account::where('code', $code)->firstOrFail()->id;
    }
}
