<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

/**
 * Expenses (PLAN.md §11): categories with their expense accounts; bills go draft → approved → paid (payment
 * voucher: expense ← cash / bank) or cancelled (a paid one by reversal). Vendor payments settle what goods
 * receipts left owed (payment voucher: vendor payable ← cash / bank).
 */
class ExpenseService
{
    public function __construct(private NumberSequenceService $numberSequenceService, private JournalService $journalService, private ActivityLogger $activityLogger) {}

    /* ---------- Categories ---------- */

    /**
     * @return array<string, mixed>
     */
    public function categoriesDatatable(): array
    {
        $query = ExpenseCategory::query()
            ->select(['id', 'name', 'parent_id', 'expense_account_id', 'is_active', 'created_at'])
            ->with(['parent:id,name', 'account:id,code,name'])
            ->withCount('expenses');

        return DataTables::eloquent($query)
            ->addColumn('parent_name', fn (ExpenseCategory $row): ?string => $row->parent?->name)
            ->addColumn('account_name', fn (ExpenseCategory $row): ?string => $row->account ? $row->account->code.' '.$row->account->name : null)
            ->removeColumn('parent', 'account')
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveCategory(?ExpenseCategory $category, array $data): ExpenseCategory
    {
        $category ??= new ExpenseCategory(['is_active' => true]);
        $category->fill($data)->save();

        return $category;
    }

    public function deleteCategory(ExpenseCategory $category): void
    {
        if ($category->expenses()->exists() || ExpenseCategory::where('parent_id', $category->id)->exists()) {
            throw ValidationException::withMessages(['category' => ['This category has expenses or sub-categories. Mark it inactive instead.']]);
        }

        $category->delete();
    }

    /* ---------- Expenses ---------- */

    /**
     * @param  array{status?: string|null, expense_category_id?: int|null, vendor_id?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function datatable(array $filters): array
    {
        $query = Expense::query()
            ->select(['id', 'expense_no', 'expense_category_id', 'vendor_id', 'expense_date', 'amount', 'tax_amount', 'payment_method_id', 'reference_no', 'description', 'status', 'paid_on', 'journal_entry_id', 'created_at'])
            ->with(['category:id,name', 'vendor:id,name', 'paymentMethod:id,name', 'journalEntry:id,voucher_no'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['expense_category_id'] ?? null, fn (Builder $query, int $id) => $query->where('expense_category_id', $id))
            ->when($filters['vendor_id'] ?? null, fn (Builder $query, int $id) => $query->where('vendor_id', $id));

        return DataTables::eloquent($query)
            ->addColumn('category_name', fn (Expense $row): ?string => $row->category?->name)
            ->addColumn('vendor_name', fn (Expense $row): ?string => $row->vendor?->name)
            ->addColumn('payment_method_name', fn (Expense $row): ?string => $row->paymentMethod?->name)
            ->addColumn('voucher_no', fn (Expense $row): ?string => $row->journalEntry?->voucher_no)
            ->addColumn('total', fn (Expense $row): int => $row->total())
            ->editColumn('expense_date', fn (Expense $row): ?string => $row->expense_date?->toDateString())
            ->editColumn('paid_on', fn (Expense $row): ?string => $row->paid_on?->toDateString())
            ->removeColumn('category', 'vendor', 'payment_method', 'journal_entry')
            ->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function details(Expense $expense): array
    {
        $expense->load(['category:id,name', 'vendor:id,name', 'paymentMethod:id,name', 'approver:id,name', 'journalEntry:id,voucher_no']);

        return [
            ...$expense->attributesToArray(),
            'expense_date' => $expense->expense_date?->toDateString(),
            'paid_on' => $expense->paid_on?->toDateString(),
            'total' => $expense->total(),
            'category_name' => $expense->category?->name,
            'vendor_name' => $expense->vendor?->name,
            'payment_method_name' => $expense->paymentMethod?->name,
            'approver_name' => $expense->approver?->name,
            'voucher_no' => $expense->journalEntry?->voucher_no,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(?Expense $expense, array $data): Expense
    {
        if ($expense !== null && $expense->status !== 'draft') {
            throw ValidationException::withMessages(['expense' => ['Only a draft expense can be changed.']]);
        }

        return DB::transaction(function () use ($expense, $data): Expense {
            $expense ??= new Expense(['expense_no' => $this->numberSequenceService->next('expense_no'), 'status' => 'draft']);
            $expense->fill([...$data, 'tax_amount' => (int) ($data['tax_amount'] ?? 0)])->save();

            return $expense;
        });
    }

    public function approve(Expense $expense, User $user): Expense
    {
        if ($expense->status !== 'draft') {
            throw ValidationException::withMessages(['expense' => ['Only a draft expense can be approved.']]);
        }

        $expense->update(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()]);
        $this->activityLogger->record('expenses', 'approved', "{$user->name} approved {$expense->expense_no}", $expense);

        return $expense;
    }

    /**
     * Pay an approved expense: a payment voucher (expense account ← the payment method's cash / bank account).
     *
     * @param  array{paid_on: string, payment_method_id: int, reference_no?: string|null}  $data
     */
    public function pay(Expense $expense, array $data, User $user): Expense
    {
        if ($expense->status !== 'approved') {
            throw ValidationException::withMessages(['expense' => ['Approve the expense before paying it.']]);
        }

        return DB::transaction(function () use ($expense, $data, $user): Expense {
            $category = ExpenseCategory::findOrFail($expense->expense_category_id);
            $method = PaymentMethod::findOrFail($data['payment_method_id']);
            $vendor = $expense->vendor_id ? Vendor::find($expense->vendor_id) : null;
            $entry = $this->journalService->record('PV', $data['paid_on'], "{$expense->expense_no} · {$category->name}".($vendor ? " · {$vendor->name}" : '').($expense->description ? ": {$expense->description}" : ''), [
                ['account_id' => $category->expense_account_id, 'debit' => $expense->total(), 'description' => $expense->description ?: $category->name, 'party_type' => $vendor ? Vendor::class : null, 'party_id' => $vendor?->id],
                ['account_id' => $method->account_id, 'credit' => $expense->total(), 'description' => $method->name.(($data['reference_no'] ?? null) ? ' '.$data['reference_no'] : '')],
            ], $expense);
            $expense->update(['status' => 'paid', 'paid_on' => $data['paid_on'], 'payment_method_id' => $method->id, 'reference_no' => $data['reference_no'] ?? $expense->reference_no, 'journal_entry_id' => $entry->id]);
            $this->activityLogger->record('expenses', 'paid', "{$user->name} paid {$expense->expense_no} as {$entry->voucher_no}", $expense);

            return $expense;
        });
    }

    /**
     * Cancel a draft or approved expense; a paid one is reversed with an equal and opposite voucher.
     */
    public function cancel(Expense $expense, string $reason, User $user): Expense
    {
        if ($expense->status === 'cancelled') {
            throw ValidationException::withMessages(['expense' => ['This expense is already cancelled.']]);
        }

        return DB::transaction(function () use ($expense, $reason, $user): Expense {
            if ($expense->status === 'paid' && $expense->journal_entry_id !== null) {
                $this->journalService->reverse(JournalEntry::findOrFail($expense->journal_entry_id), $reason);
            }

            $expense->update(['status' => 'cancelled', 'cancel_reason' => $reason]);
            $this->activityLogger->record('expenses', 'cancelled', "{$user->name} cancelled {$expense->expense_no}: {$reason}", $expense);

            return $expense;
        });
    }

    public function delete(Expense $expense): void
    {
        if ($expense->status !== 'draft') {
            throw ValidationException::withMessages(['expense' => ['Only a draft can be deleted; cancel it instead.']]);
        }

        $expense->delete();
    }

    /* ---------- Vendors ---------- */

    /**
     * Vendors with what the school owes them (credits − debits on their payable lines).
     *
     * @return array<string, mixed>
     */
    public function vendorBalancesDatatable(): array
    {
        $balance = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->whereIn('journal_entries.status', JournalEntry::LEDGER_STATUSES)
            ->where('journal_lines.party_type', Vendor::class)
            ->whereColumn('journal_lines.party_id', 'vendors.id')
            ->selectRaw('coalesce(sum(journal_lines.credit - journal_lines.debit), 0)');

        $query = Vendor::query()->select(['id', 'name', 'phone', 'payable_account_id', 'is_active', 'created_at'])->selectSub($balance, 'owed');

        return DataTables::eloquent($query)
            ->editColumn('owed', fn (Vendor $vendor): int => (int) $vendor->owed)
            ->orderColumn('owed', 'owed $1')
            ->toArray();
    }

    public function owedTo(Vendor $vendor): int
    {
        return (int) JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->whereIn('journal_entries.status', JournalEntry::LEDGER_STATUSES)
            ->where('journal_lines.party_type', Vendor::class)
            ->where('journal_lines.party_id', $vendor->id)
            ->sum(DB::raw('journal_lines.credit - journal_lines.debit'));
    }

    /**
     * @return array<string, mixed>
     */
    public function paymentsDatatable(): array
    {
        $query = VendorPayment::query()
            ->select(['id', 'payment_no', 'vendor_id', 'paid_on', 'amount', 'payment_method_id', 'reference_no', 'remarks', 'status', 'journal_entry_id', 'created_at'])
            ->with(['vendor:id,name', 'paymentMethod:id,name', 'journalEntry:id,voucher_no']);

        return DataTables::eloquent($query)
            ->addColumn('vendor_name', fn (VendorPayment $row): ?string => $row->vendor?->name)
            ->addColumn('payment_method_name', fn (VendorPayment $row): ?string => $row->paymentMethod?->name)
            ->addColumn('voucher_no', fn (VendorPayment $row): ?string => $row->journalEntry?->voucher_no)
            ->editColumn('paid_on', fn (VendorPayment $row): ?string => $row->paid_on?->toDateString())
            ->removeColumn('vendor', 'payment_method', 'journal_entry')
            ->toArray();
    }

    /**
     * Pay a vendor (vendor payable ← cash / bank). The amount may not exceed what they are owed.
     *
     * @param  array{vendor_id: int, paid_on: string, amount: int, payment_method_id: int, reference_no?: string|null, remarks?: string|null}  $data
     */
    public function payVendor(array $data, User $user): VendorPayment
    {
        $vendor = Vendor::findOrFail($data['vendor_id']);
        $owed = $this->owedTo($vendor);

        if ($data['amount'] > $owed) {
            throw ValidationException::withMessages(['amount' => ['Only Rs '.number_format($owed / 100, 2).' is owed to '.$vendor->name.'.']]);
        }

        return DB::transaction(function () use ($vendor, $data, $user): VendorPayment {
            $method = PaymentMethod::findOrFail($data['payment_method_id']);
            $payment = VendorPayment::create([...$data, 'payment_no' => $this->numberSequenceService->next('vendor_payment_no'), 'status' => 'posted']);
            $entry = $this->journalService->record('PV', $data['paid_on'], "Payment to {$vendor->name} ({$payment->payment_no})".(($data['reference_no'] ?? null) ? ' ref '.$data['reference_no'] : ''), [
                ['account_id' => $vendor->payable_account_id ?? Account::where('code', AssetLedgerService::PAYABLE_ACCOUNT)->firstOrFail()->id, 'debit' => $data['amount'], 'description' => $vendor->name, 'party_type' => Vendor::class, 'party_id' => $vendor->id],
                ['account_id' => $method->account_id, 'credit' => $data['amount'], 'description' => $method->name],
            ], $payment);
            $payment->update(['journal_entry_id' => $entry->id]);
            $this->activityLogger->record('expenses', 'paid', "{$user->name} paid {$vendor->name} {$payment->payment_no} as {$entry->voucher_no}", $payment);

            return $payment;
        });
    }

    public function reversePayment(VendorPayment $payment, string $reason, User $user): VendorPayment
    {
        if ($payment->status !== 'posted') {
            throw ValidationException::withMessages(['payment' => ['This payment is already reversed.']]);
        }

        return DB::transaction(function () use ($payment, $reason, $user): VendorPayment {
            $this->journalService->reverse(JournalEntry::findOrFail($payment->journal_entry_id), $reason);
            $payment->update(['status' => 'reversed', 'reversal_reason' => $reason]);
            $this->activityLogger->record('expenses', 'reversed', "{$user->name} reversed {$payment->payment_no}: {$reason}", $payment);

            return $payment;
        });
    }
}
