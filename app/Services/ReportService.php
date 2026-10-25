<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Employee;
use App\Models\FeeInvoice;
use App\Models\JournalEntry;
use App\Models\Lookup;
use App\Models\SystemSetting;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports centre (PLAN.md phase 17e): every report is computed from the module tables (no report tables). Each report
 * returns columns, rows, totals and a short summary; long lists (day book, defaulters, staff attendance, stock)
 * are paginated on screen and streamed in chunks for CSV. Access follows the permission of the module.
 */
class ReportService
{
    /**
     * key => [title, group, description, permissions (any), filters, paged].
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: array<int, string>, 4: array<int, string>, 5: bool}>
     */
    public const REPORTS = [
        'profit-loss' => ['Income & Expenditure', 'Finance', 'Income and expense accounts for a period and the surplus or deficit.', ['view-ledger'], ['from', 'to'], false],
        'balance-sheet' => ['Balance Sheet', 'Finance', 'Assets, liabilities and equity on a date, including the surplus so far.', ['view-ledger'], ['as_of'], false],
        'cash-bank-book' => ['Cash & Bank Summary', 'Finance', 'Opening, money in, money out and closing for every cash and bank account.', ['view-ledger'], ['from', 'to'], false],
        'day-book' => ['Day Book', 'Finance', 'Every posted voucher in date order.', ['view-ledger'], ['from', 'to'], true],
        'fee-collection' => ['Fee Collection', 'Fees', 'Receipts by day, payment method or class.', ['view-fees', 'manage-fees'], ['from', 'to', 'group_by'], false],
        'fee-billing' => ['Fee Billing by Class', 'Fees', 'Challans issued, concessions, collected and still due, per class.', ['view-fees', 'manage-fees'], ['from', 'to'], false],
        'fee-defaulters' => ['Fee Defaulters & Ageing', 'Fees', 'Students with overdue challans, split by how long they are overdue.', ['view-fees', 'manage-fees'], ['as_of', 'class_id'], true],
        'class-strength' => ['Class Strength', 'Students', 'Enrolled students per class and section this session, by gender.', ['manage-students'], [], false],
        'admissions' => ['Admissions Funnel', 'Students', 'Inquiries and applications by stage for a period.', ['manage-students', 'manage-admissions'], ['from', 'to'], false],
        'student-attendance' => ['Student Attendance', 'Students', 'Attendance per section for a period, with the percentage present.', ['manage-students', 'record-attendance'], ['from', 'to', 'class_id'], false],
        'staff-strength' => ['Staff Strength', 'HR', 'Current staff per department, teaching and non-teaching.', ['manage-hr'], [], false],
        'staff-attendance' => ['Staff Attendance', 'HR', 'Present, late, absent and leave days per employee.', ['manage-hr', 'manage-staff-attendance'], ['from', 'to'], true],
        'leave-summary' => ['Leave Taken', 'HR', 'Approved leave per leave type for a period.', ['manage-leave', 'manage-hr'], ['from', 'to'], false],
        'payroll-summary' => ['Payroll Summary', 'HR', 'Each payroll month: staff, gross, deductions and net pay.', ['manage-payroll', 'view-payroll'], ['from', 'to'], false],
        'expenses-by-category' => ['Expenses by Category', 'Operations', 'Approved and paid expenses per category for a period.', ['manage-expenses'], ['from', 'to'], false],
        'stock-valuation' => ['Stock Valuation', 'Operations', 'Quantity in hand and value of every stock item at average cost.', ['manage-inventory'], [], true],
        'asset-register' => ['Asset Register Summary', 'Operations', 'Assets per category: in store, handed out, in repair, cost and book value.', ['manage-assets'], [], false],
        'transport-occupancy' => ['Transport Occupancy', 'Operations', 'Riders per route against the seats of its vehicle.', ['manage-transport'], [], false],
    ];

    public const PER_PAGE = 50;

    public function __construct(private ActivityLogger $activityLogger) {}

    /**
     * The reports this user may open, grouped.
     *
     * @return array<int, array{group: string, reports: array<int, array<string, mixed>>}>
     */
    public function catalogue(User $user): array
    {
        return collect(self::REPORTS)
            ->filter(fn (array $report): bool => $user->hasPermission($report[3]))
            ->map(fn (array $report, string $key): array => ['key' => $key, 'title' => $report[0], 'group' => $report[1], 'description' => $report[2], 'filters' => $report[4]])
            ->groupBy('group')
            ->map(fn ($reports, string $group): array => ['group' => $group, 'reports' => $reports->values()->all()])
            ->values()
            ->all();
    }

    public function allowed(string $key, User $user): bool
    {
        return isset(self::REPORTS[$key]) && $user->hasPermission(self::REPORTS[$key][3]);
    }

    /**
     * Run a report for the screen (one page of a long list).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function run(string $key, array $filters, int $page = 1): array
    {
        // The same report with the same filters is reused for two minutes; CSV and PDF always run fresh.
        return Cache::remember('report:'.$key.':'.md5(serialize([$filters, $page, today()->toDateString()])), 120, fn (): array => $this->page($key, $filters, $page));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function page(string $key, array $filters, int $page): array
    {
        $report = $this->build($key, $filters);

        if (isset($report['query'])) {
            $paginator = $report['query']->paginate(self::PER_PAGE, ['*'], 'page', $page);
            $report['rows'] = collect($paginator->items())->map($report['map'])->all();
            $report['meta'] = ['current_page' => $paginator->currentPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total(), 'last_page' => $paginator->lastPage()];
            unset($report['query'], $report['map']);
        }

        return $report;
    }

    /**
     * Every row as CSV, streamed in chunks so long lists never sit in memory.
     *
     * @param  array<string, mixed>  $filters
     */
    public function csv(string $key, array $filters, User $user): StreamedResponse
    {
        $report = $this->build($key, $filters);
        $this->activityLogger->record('reports', 'exported', "{$user->name} exported {$report['title']} ({$report['period']}) as CSV");

        return response()->streamDownload(function () use ($report): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_column($report['columns'], 'label'));
            $write = fn (array $row) => fputcsv($out, array_map(fn (array $column) => $this->plain($row[$column['key']] ?? null, $column['type']), $report['columns']));

            if (isset($report['query'])) {
                $report['query']->lazy(500)->each(fn ($row) => $write(($report['map'])($row)));
            } else {
                array_map($write, $report['rows']);
            }

            if ($report['totals'] !== null) {
                $write($report['totals']);
            }

            fclose($out);
        }, Str::slug($report['title']).'-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Printable report. Long lists print their first 1,000 rows; the CSV has everything.
     *
     * @param  array<string, mixed>  $filters
     */
    public function pdf(string $key, array $filters, User $user): Response
    {
        $report = $this->build($key, $filters);

        if (isset($report['query'])) {
            $report['rows'] = $report['query']->limit(1000)->get()->map($report['map'])->all();
        }

        $this->activityLogger->record('reports', 'printed', "{$user->name} printed {$report['title']} ({$report['period']})");

        return Pdf::loadView('pdf.report', ['report' => $report, 'schoolName' => SystemSetting::query()->value('school_name')])
            ->setPaper('a4', count($report['columns']) > 6 ? 'landscape' : 'portrait')
            ->stream(Str::slug($report['title']).'.pdf');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function build(string $key, array $filters): array
    {
        $from = $filters['from'] ?? now()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? today()->toDateString();
        $asOf = $filters['as_of'] ?? today()->toDateString();
        $definition = self::REPORTS[$key];
        $period = in_array('as_of', $definition[4], true) ? 'as of '.Carbon::parse($asOf)->format('d M Y')
            : (in_array('from', $definition[4], true) ? Carbon::parse($from)->format('d M Y').' – '.Carbon::parse($to)->format('d M Y') : 'today');

        $report = $this->{Str::camel($key)}($from, $to, $asOf, $filters);

        return ['key' => $key, 'title' => $definition[0], 'description' => $definition[2], 'period' => $period, 'filters' => ['from' => $from, 'to' => $to, 'as_of' => $asOf, 'class_id' => $filters['class_id'] ?? null, 'group_by' => $filters['group_by'] ?? null], 'totals' => null, 'summary' => [], 'meta' => null, ...$report];
    }

    /* ---------- Finance ---------- */

    /**
     * Posted movement (debit − credit) per account, optionally within dates.
     */
    private function movements(?string $from, string $to): Builder
    {
        return DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->whereIn('journal_entries.status', JournalEntry::LEDGER_STATUSES)
            ->whereNull('journal_lines.deleted_at')
            ->when($from, fn (Builder $query) => $query->whereDate('journal_entries.entry_date', '>=', $from))
            ->whereDate('journal_entries.entry_date', '<=', $to)
            ->groupBy('journal_lines.account_id')
            ->selectRaw('journal_lines.account_id, sum(journal_lines.debit) as debit, sum(journal_lines.credit) as credit');
    }

    /**
     * @return Collection<int, object>
     */
    private function accountsOfTypes(array $types)
    {
        return DB::table('accounts')->join('account_types', 'account_types.id', '=', 'accounts.account_type_id')
            ->whereIn('account_types.name', $types)->where('accounts.is_group', false)->whereNull('accounts.deleted_at')
            ->orderBy('accounts.code')
            ->get(['accounts.id', 'accounts.code', 'accounts.name', 'accounts.opening_balance', 'accounts.opening_balance_type', 'account_types.name as type', 'account_types.normal_balance']);
    }

    /**
     * @return array<string, mixed>
     */
    private function profitLoss(string $from, string $to): array
    {
        $movements = $this->movements($from, $to)->get()->keyBy('account_id');
        $rows = $this->accountsOfTypes(['Income', 'Expense'])->map(function (object $account) use ($movements): array {
            $movement = $movements[$account->id] ?? null;
            $net = (int) ($movement->debit ?? 0) - (int) ($movement->credit ?? 0);

            return ['type' => $account->type, 'code' => $account->code, 'name' => $account->name, 'amount' => $account->type === 'Income' ? -$net : $net];
        })->filter(fn (array $row): bool => $row['amount'] !== 0)->sortBy(['type', 'code'])->values();

        $income = (int) $rows->where('type', 'Income')->sum('amount');
        $expense = (int) $rows->where('type', 'Expense')->sum('amount');

        return [
            'columns' => [$this->column('type', 'Type'), $this->column('code', 'Code'), $this->column('name', 'Account'), $this->column('amount', 'Amount', 'money')],
            'rows' => $rows->all(),
            'summary' => [['Total income', $income, 'money'], ['Total expenditure', $expense, 'money'], [$income >= $expense ? 'Surplus' : 'Deficit', abs($income - $expense), 'money']],
            'chart' => ['label' => 'name', 'value' => 'amount'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function balanceSheet(string $from, string $to, string $asOf): array
    {
        $movements = $this->movements(null, $asOf)->get()->keyBy('account_id');
        $balance = function (object $account) use ($movements): int {
            $movement = $movements[$account->id] ?? null;
            $debitBalance = ($account->opening_balance_type === 'credit' ? -(int) $account->opening_balance : (int) $account->opening_balance) + (int) ($movement->debit ?? 0) - (int) ($movement->credit ?? 0);

            return $account->normal_balance === 'debit' ? $debitBalance : -$debitBalance;
        };

        $rows = $this->accountsOfTypes(['Asset', 'Liability', 'Equity'])
            ->map(fn (object $account): array => ['type' => $account->type, 'code' => $account->code, 'name' => $account->name, 'amount' => $balance($account)])
            ->filter(fn (array $row): bool => $row['amount'] !== 0)->values();
        $surplus = $this->accountsOfTypes(['Income', 'Expense'])->sum(fn (object $account): int => $account->type === 'Income' ? $balance($account) : -$balance($account));

        if ($surplus !== 0) {
            $rows->push(['type' => 'Equity', 'code' => '', 'name' => 'Surplus / (deficit) to date', 'amount' => $surplus]);
        }

        $assets = (int) $rows->where('type', 'Asset')->sum('amount');
        $liabilities = (int) $rows->where('type', 'Liability')->sum('amount');
        $equity = (int) $rows->where('type', 'Equity')->sum('amount');

        return [
            'columns' => [$this->column('type', 'Type'), $this->column('code', 'Code'), $this->column('name', 'Account'), $this->column('amount', 'Balance', 'money')],
            'rows' => $rows->sortBy(['type', 'code'])->values()->all(),
            'summary' => [['Total assets', $assets, 'money'], ['Liabilities', $liabilities, 'money'], ['Equity', $equity, 'money'], ['Difference', $assets - $liabilities - $equity, 'money']],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cashBankBook(string $from, string $to): array
    {
        $before = $this->movements(null, Carbon::parse($from)->subDay()->toDateString())->get()->keyBy('account_id');
        $during = $this->movements($from, $to)->get()->keyBy('account_id');

        $rows = DB::table('accounts')->where('is_group', false)->whereNull('deleted_at')
            ->where(fn (Builder $query) => $query->where('is_cash_account', true)->orWhere('is_bank_account', true))
            ->orderBy('code')->get(['id', 'code', 'name', 'opening_balance', 'opening_balance_type'])
            ->map(function (object $account) use ($before, $during): array {
                $opening = ($account->opening_balance_type === 'credit' ? -(int) $account->opening_balance : (int) $account->opening_balance) + (int) ($before[$account->id]->debit ?? 0) - (int) ($before[$account->id]->credit ?? 0);
                $in = (int) ($during[$account->id]->debit ?? 0);
                $out = (int) ($during[$account->id]->credit ?? 0);

                return ['code' => $account->code, 'name' => $account->name, 'opening' => $opening, 'in' => $in, 'out' => $out, 'closing' => $opening + $in - $out];
            });

        return [
            'columns' => [$this->column('code', 'Code'), $this->column('name', 'Account'), $this->column('opening', 'Opening', 'money'), $this->column('in', 'Money in', 'money'), $this->column('out', 'Money out', 'money'), $this->column('closing', 'Closing', 'money')],
            'rows' => $rows->all(),
            'totals' => ['code' => '', 'name' => 'Total', 'opening' => $rows->sum('opening'), 'in' => $rows->sum('in'), 'out' => $rows->sum('out'), 'closing' => $rows->sum('closing')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dayBook(string $from, string $to): array
    {
        $query = DB::table('journal_entries')->whereIn('status', JournalEntry::LEDGER_STATUSES)->whereNull('deleted_at')
            ->whereDate('entry_date', '>=', $from)->whereDate('entry_date', '<=', $to)
            ->orderBy('entry_date')->orderBy('id')
            ->select(['entry_date', 'voucher_no', 'narration', 'status', 'total_debit']);

        return [
            'columns' => [$this->column('entry_date', 'Date', 'date'), $this->column('voucher_no', 'Voucher'), $this->column('narration', 'Narration'), $this->column('status', 'Status'), $this->column('total_debit', 'Amount', 'money')],
            'query' => $query,
            'map' => fn (object $row): array => ['entry_date' => substr((string) $row->entry_date, 0, 10), 'voucher_no' => $row->voucher_no, 'narration' => $row->narration, 'status' => Str::headline($row->status), 'total_debit' => (int) $row->total_debit],
            'totals' => ['entry_date' => '', 'voucher_no' => '', 'narration' => 'Total', 'status' => '', 'total_debit' => (int) (clone $query)->reorder()->sum('total_debit')],
        ];
    }

    /* ---------- Fees ---------- */

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function feeCollection(string $from, string $to, string $asOf, array $filters): array
    {
        $by = in_array($filters['group_by'] ?? null, ['day', 'method', 'class'], true) ? $filters['group_by'] : 'day';
        $query = DB::table('fee_payments')->where('fee_payments.status', 'posted')
            ->whereDate('fee_payments.received_on', '>=', $from)->whereDate('fee_payments.received_on', '<=', $to)
            ->selectRaw('count(*) as receipts, sum(fee_payments.amount) as amount');

        match ($by) {
            'method' => $query->leftJoin('payment_methods', 'payment_methods.id', '=', 'fee_payments.payment_method_id')->addSelect('payment_methods.name as label')->groupBy('payment_methods.name')->orderBy('payment_methods.name'),
            'class' => $this->joinCurrentClass($query, 'fee_payments.student_id')->addSelect('classes.name as label')->groupBy('classes.name', 'classes.level_order')->orderBy('classes.level_order'),
            default => $query->addSelect('fee_payments.received_on as label')->groupBy('fee_payments.received_on')->orderBy('fee_payments.received_on'),
        };

        $rows = $query->get()->map(fn (object $row): array => ['label' => $by === 'day' ? substr((string) $row->label, 0, 10) : ($row->label ?? 'Not set'), 'receipts' => (int) $row->receipts, 'amount' => (int) $row->amount]);
        $labels = ['day' => 'Date', 'method' => 'Payment method', 'class' => 'Class'];

        return [
            'columns' => [$this->column('label', $labels[$by], $by === 'day' ? 'date' : 'text'), $this->column('receipts', 'Receipts', 'number'), $this->column('amount', 'Collected', 'money')],
            'rows' => $rows->all(),
            'totals' => ['label' => 'Total', 'receipts' => $rows->sum('receipts'), 'amount' => $rows->sum('amount')],
            'chart' => ['label' => 'label', 'value' => 'amount'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function feeBilling(string $from, string $to): array
    {
        $rows = DB::table('fee_invoices')
            ->leftJoin('enrollments', 'enrollments.id', '=', 'fee_invoices.enrollment_id')
            ->leftJoin('classes', 'classes.id', '=', 'enrollments.class_id')
            ->whereIn('fee_invoices.status', [...FeeInvoice::OPEN_STATUSES, 'paid'])
            ->whereDate('fee_invoices.issue_date', '>=', $from)->whereDate('fee_invoices.issue_date', '<=', $to)
            ->groupBy('classes.name', 'classes.level_order')->orderBy('classes.level_order')
            ->selectRaw('classes.name as class, count(*) as challans, sum(fee_invoices.subtotal) as billed, sum(fee_invoices.concession_total) as concessions, sum(fee_invoices.total) as total, sum(fee_invoices.paid_total) as paid, sum(fee_invoices.balance) as balance')
            ->get()
            ->map(fn (object $row): array => ['class' => $row->class ?? 'Admission / other', 'challans' => (int) $row->challans, 'billed' => (int) $row->billed, 'concessions' => (int) $row->concessions, 'total' => (int) $row->total, 'paid' => (int) $row->paid, 'balance' => (int) $row->balance, 'recovery' => $row->total > 0 ? round($row->paid * 100 / $row->total, 1) : null]);

        $total = (int) $rows->sum('total');

        return [
            'columns' => [$this->column('class', 'Class'), $this->column('challans', 'Challans', 'number'), $this->column('billed', 'Billed', 'money'), $this->column('concessions', 'Concessions', 'money'), $this->column('total', 'Payable', 'money'), $this->column('paid', 'Collected', 'money'), $this->column('balance', 'Still due', 'money'), $this->column('recovery', 'Recovery', 'percent')],
            'rows' => $rows->all(),
            'totals' => ['class' => 'Total', 'challans' => $rows->sum('challans'), 'billed' => $rows->sum('billed'), 'concessions' => $rows->sum('concessions'), 'total' => $total, 'paid' => $rows->sum('paid'), 'balance' => $rows->sum('balance'), 'recovery' => $total > 0 ? round($rows->sum('paid') * 100 / $total, 1) : null],
            'chart' => ['label' => 'class', 'value' => 'balance'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function feeDefaulters(string $from, string $to, string $asOf, array $filters): array
    {
        $day = fn (int $days): string => Carbon::parse($asOf)->subDays($days)->toDateString();
        $query = DB::table('fee_invoices')
            ->join('students', 'students.id', '=', 'fee_invoices.student_id')
            ->leftJoin('enrollments', 'enrollments.id', '=', 'fee_invoices.enrollment_id')
            ->leftJoin('classes', 'classes.id', '=', 'enrollments.class_id')
            ->whereIn('fee_invoices.status', FeeInvoice::OPEN_STATUSES)->where('fee_invoices.balance', '>', 0)
            ->whereDate('fee_invoices.due_date', '<', $asOf)
            ->when($filters['class_id'] ?? null, fn (Builder $query, int $id) => $query->where('enrollments.class_id', $id))
            ->groupBy('fee_invoices.student_id', 'students.first_name', 'students.last_name', 'students.admission_no')
            ->orderByDesc(DB::raw('sum(fee_invoices.balance)'))
            ->selectRaw('fee_invoices.student_id, students.first_name, students.last_name, students.admission_no, max(classes.name) as class, count(*) as challans, min(fee_invoices.due_date) as oldest_due, sum(fee_invoices.balance) as balance')
            ->selectRaw('sum(case when fee_invoices.due_date >= ? then fee_invoices.balance else 0 end) as d30', [$day(30)])
            ->selectRaw('sum(case when fee_invoices.due_date < ? and fee_invoices.due_date >= ? then fee_invoices.balance else 0 end) as d60', [$day(30), $day(60)])
            ->selectRaw('sum(case when fee_invoices.due_date < ? and fee_invoices.due_date >= ? then fee_invoices.balance else 0 end) as d90', [$day(60), $day(90)])
            ->selectRaw('sum(case when fee_invoices.due_date < ? then fee_invoices.balance else 0 end) as over90', [$day(90)]);

        $totals = DB::query()->fromSub((clone $query)->reorder(), 'defaulters')
            ->selectRaw('count(*) as students, sum(challans) as challans, sum(balance) as balance, sum(d30) as d30, sum(d60) as d60, sum(d90) as d90, sum(over90) as over90')->first();

        return [
            'columns' => [$this->column('student', 'Student'), $this->column('admission_no', 'Admission no'), $this->column('class', 'Class'), $this->column('challans', 'Challans', 'number'), $this->column('oldest_due', 'Oldest due', 'date'), $this->column('d30', '1–30 days', 'money'), $this->column('d60', '31–60 days', 'money'), $this->column('d90', '61–90 days', 'money'), $this->column('over90', '90+ days', 'money'), $this->column('balance', 'Total due', 'money')],
            'query' => $query,
            'map' => fn (object $row): array => ['student' => trim($row->first_name.' '.$row->last_name), 'admission_no' => $row->admission_no, 'class' => $row->class, 'challans' => (int) $row->challans, 'oldest_due' => substr((string) $row->oldest_due, 0, 10), 'd30' => (int) $row->d30, 'd60' => (int) $row->d60, 'd90' => (int) $row->d90, 'over90' => (int) $row->over90, 'balance' => (int) $row->balance],
            'totals' => ['student' => 'Total', 'admission_no' => '', 'class' => '', 'challans' => (int) ($totals->challans ?? 0), 'oldest_due' => '', 'd30' => (int) ($totals->d30 ?? 0), 'd60' => (int) ($totals->d60 ?? 0), 'd90' => (int) ($totals->d90 ?? 0), 'over90' => (int) ($totals->over90 ?? 0), 'balance' => (int) ($totals->balance ?? 0)],
            'summary' => [['Students owing', (int) ($totals->students ?? 0), 'number'], ['Overdue amount', (int) ($totals->balance ?? 0), 'money'], ['Over 90 days', (int) ($totals->over90 ?? 0), 'money']],
        ];
    }

    /* ---------- Students ---------- */

    /**
     * @return array<string, mixed>
     */
    private function classStrength(): array
    {
        $genders = Lookup::query()->ofType('gender')->orderBy('sort_order')->get(['id', 'name']);
        $query = DB::table('enrollments')
            ->join('students', 'students.id', '=', 'enrollments.student_id')
            ->join('classes', 'classes.id', '=', 'enrollments.class_id')
            ->leftJoin('sections', 'sections.id', '=', 'enrollments.section_id')
            ->where('enrollments.academic_session_id', AcademicSession::where('is_current', true)->value('id'))
            ->where('enrollments.status', 'active')->whereNull('enrollments.deleted_at')->whereNull('students.deleted_at')
            ->groupBy('classes.name', 'classes.level_order', 'sections.name')->orderBy('classes.level_order')->orderBy('sections.name')
            ->selectRaw('classes.name as class, sections.name as section, count(*) as total');

        foreach ($genders as $gender) {
            $query->selectRaw('sum(case when students.gender_id = ? then 1 else 0 end) as g'.$gender->id, [$gender->id]);
        }

        $rows = $query->get()->map(fn (object $row): array => ['class' => $row->class, 'section' => $row->section ?? '—', 'total' => (int) $row->total, ...$genders->mapWithKeys(fn ($gender): array => ['g'.$gender->id => (int) $row->{'g'.$gender->id}])->all()]);

        return [
            'columns' => [$this->column('class', 'Class'), $this->column('section', 'Section'), ...$genders->map(fn ($gender): array => $this->column('g'.$gender->id, $gender->name, 'number'))->all(), $this->column('total', 'Total', 'number')],
            'rows' => $rows->all(),
            'totals' => ['class' => 'Total', 'section' => '', 'total' => $rows->sum('total'), ...$genders->mapWithKeys(fn ($gender): array => ['g'.$gender->id => $rows->sum('g'.$gender->id)])->all()],
            'chart' => ['label' => 'class', 'value' => 'total'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function admissions(string $from, string $to): array
    {
        $count = fn (string $table, string $dateColumn) => DB::table($table)->whereNull('deleted_at')->whereDate($dateColumn, '>=', $from)->whereDate($dateColumn, '<=', $to)
            ->groupBy('status')->selectRaw('status, count(*) as total')->pluck('total', 'status');

        $rows = collect([['Inquiries', $count('admission_inquiries', 'inquiry_date')], ['Applications', $count('admission_applications', 'applied_on')]])
            ->flatMap(fn (array $stage) => $stage[1]->map(fn ($total, string $status): array => ['stage' => $stage[0], 'status' => Str::headline($status), 'total' => (int) $total])->values());

        return [
            'columns' => [$this->column('stage', 'Stage'), $this->column('status', 'Status'), $this->column('total', 'Count', 'number')],
            'rows' => $rows->all(),
            'summary' => [['Inquiries', $rows->where('stage', 'Inquiries')->sum('total'), 'number'], ['Applications', $rows->where('stage', 'Applications')->sum('total'), 'number'], ['Admitted', $rows->where('stage', 'Applications')->whereIn('status', ['Enrolled', 'Admitted'])->sum('total'), 'number']],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function studentAttendance(string $from, string $to, string $asOf, array $filters): array
    {
        $rows = DB::table('student_attendances')
            ->join('sections', 'sections.id', '=', 'student_attendances.section_id')
            ->join('classes', 'classes.id', '=', 'sections.class_id')
            ->whereDate('student_attendances.date', '>=', $from)->whereDate('student_attendances.date', '<=', $to)
            ->when($filters['class_id'] ?? null, fn (Builder $query, int $id) => $query->where('sections.class_id', $id))
            ->groupBy('classes.name', 'classes.level_order', 'sections.name')->orderBy('classes.level_order')->orderBy('sections.name')
            ->selectRaw("classes.name as class, sections.name as section, count(*) as marked, count(distinct student_attendances.date) as days,
                sum(case when student_attendances.status = 'present' then 1 else 0 end) as present, sum(case when student_attendances.status = 'late' then 1 else 0 end) as late,
                sum(case when student_attendances.status = 'absent' then 1 else 0 end) as absent, sum(case when student_attendances.status in ('leave', 'half_day') then 1 else 0 end) as on_leave")
            ->get()
            ->map(fn (object $row): array => ['class' => $row->class, 'section' => $row->section, 'days' => (int) $row->days, 'present' => (int) $row->present, 'late' => (int) $row->late, 'absent' => (int) $row->absent, 'on_leave' => (int) $row->on_leave, 'percent' => $row->marked > 0 ? round(($row->present + $row->late) * 100 / $row->marked, 1) : null]);

        $marked = $rows->sum(fn (array $row): int => $row['present'] + $row['late'] + $row['absent'] + $row['on_leave']);

        return [
            'columns' => [$this->column('class', 'Class'), $this->column('section', 'Section'), $this->column('days', 'Days marked', 'number'), $this->column('present', 'Present', 'number'), $this->column('late', 'Late', 'number'), $this->column('absent', 'Absent', 'number'), $this->column('on_leave', 'Leave', 'number'), $this->column('percent', 'Present %', 'percent')],
            'rows' => $rows->all(),
            'totals' => ['class' => 'Total', 'section' => '', 'days' => '', 'present' => $rows->sum('present'), 'late' => $rows->sum('late'), 'absent' => $rows->sum('absent'), 'on_leave' => $rows->sum('on_leave'), 'percent' => $marked > 0 ? round(($rows->sum('present') + $rows->sum('late')) * 100 / $marked, 1) : null],
            'chart' => ['label' => 'section', 'value' => 'percent'],
        ];
    }

    /* ---------- HR ---------- */

    /**
     * @return array<string, mixed>
     */
    private function staffStrength(): array
    {
        $rows = DB::table('employees')->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
            ->whereIn('employees.status', Employee::CURRENT_STATUSES)->whereNull('employees.deleted_at')
            ->groupBy('departments.name')->orderBy('departments.name')
            ->selectRaw('departments.name as department, sum(case when employees.is_teaching_staff = 1 then 1 else 0 end) as teaching, sum(case when employees.is_teaching_staff = 1 then 0 else 1 end) as non_teaching, count(*) as total')
            ->get()->map(fn (object $row): array => ['department' => $row->department ?? 'No department', 'teaching' => (int) $row->teaching, 'non_teaching' => (int) $row->non_teaching, 'total' => (int) $row->total]);

        return [
            'columns' => [$this->column('department', 'Department'), $this->column('teaching', 'Teaching', 'number'), $this->column('non_teaching', 'Non-teaching', 'number'), $this->column('total', 'Total', 'number')],
            'rows' => $rows->all(),
            'totals' => ['department' => 'Total', 'teaching' => $rows->sum('teaching'), 'non_teaching' => $rows->sum('non_teaching'), 'total' => $rows->sum('total')],
            'chart' => ['label' => 'department', 'value' => 'total'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function staffAttendance(string $from, string $to): array
    {
        $query = DB::table('staff_attendances')->join('employees', 'employees.id', '=', 'staff_attendances.employee_id')
            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
            ->whereDate('staff_attendances.date', '>=', $from)->whereDate('staff_attendances.date', '<=', $to)->whereNull('employees.deleted_at')
            ->groupBy('employees.id', 'employees.first_name', 'employees.last_name', 'employees.employee_code', 'departments.name')
            ->orderBy('employees.first_name')
            ->selectRaw("employees.first_name, employees.last_name, employees.employee_code, departments.name as department,
                sum(case when staff_attendances.status = 'present' then 1 else 0 end) as present, sum(case when staff_attendances.status = 'late' then 1 else 0 end) as late,
                sum(case when staff_attendances.status = 'absent' then 1 else 0 end) as absent, sum(case when staff_attendances.status = 'half_day' then 1 else 0 end) as half_day,
                sum(case when staff_attendances.status = 'leave' then 1 else 0 end) as on_leave, sum(staff_attendances.late_minutes) as late_minutes");

        return [
            'columns' => [$this->column('employee', 'Employee'), $this->column('employee_code', 'Code'), $this->column('department', 'Department'), $this->column('present', 'Present', 'number'), $this->column('late', 'Late', 'number'), $this->column('late_minutes', 'Late minutes', 'number'), $this->column('half_day', 'Half day', 'number'), $this->column('absent', 'Absent', 'number'), $this->column('on_leave', 'Leave', 'number')],
            'query' => $query,
            'map' => fn (object $row): array => ['employee' => trim($row->first_name.' '.$row->last_name), 'employee_code' => $row->employee_code, 'department' => $row->department, 'present' => (int) $row->present, 'late' => (int) $row->late, 'late_minutes' => (int) $row->late_minutes, 'half_day' => (int) $row->half_day, 'absent' => (int) $row->absent, 'on_leave' => (int) $row->on_leave],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function leaveSummary(string $from, string $to): array
    {
        $rows = DB::table('leave_requests')->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->where('leave_requests.status', 'approved')->whereNull('leave_requests.deleted_at')
            ->whereDate('leave_requests.from_date', '<=', $to)->whereDate('leave_requests.to_date', '>=', $from)
            ->groupBy('leave_types.name', 'leave_types.sort_order', 'leave_types.is_paid')->orderBy('leave_types.sort_order')
            ->selectRaw('leave_types.name as leave_type, leave_types.is_paid, count(*) as requests, count(distinct leave_requests.employee_id) as staff, sum(leave_requests.days) as days')
            ->get()->map(fn (object $row): array => ['leave_type' => $row->leave_type.($row->is_paid ? '' : ' (unpaid)'), 'requests' => (int) $row->requests, 'staff' => (int) $row->staff, 'days' => (float) $row->days]);

        return [
            'columns' => [$this->column('leave_type', 'Leave type'), $this->column('requests', 'Requests', 'number'), $this->column('staff', 'Staff', 'number'), $this->column('days', 'Days', 'number')],
            'rows' => $rows->all(),
            'totals' => ['leave_type' => 'Total', 'requests' => $rows->sum('requests'), 'staff' => '', 'days' => $rows->sum('days')],
            'chart' => ['label' => 'leave_type', 'value' => 'days'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payrollSummary(string $from, string $to): array
    {
        $rows = DB::table('payroll_runs')->whereNull('deleted_at')
            ->whereDate('month', '>=', Carbon::parse($from)->startOfMonth()->toDateString())->whereDate('month', '<=', $to)
            ->orderBy('month')->get(['month', 'status', 'employee_count', 'total_gross', 'total_deductions', 'total_net'])
            ->map(fn (object $row): array => ['month' => Carbon::parse($row->month)->format('F Y'), 'status' => Str::headline($row->status), 'staff' => (int) $row->employee_count, 'gross' => (int) $row->total_gross, 'deductions' => (int) $row->total_deductions, 'net' => (int) $row->total_net]);

        return [
            'columns' => [$this->column('month', 'Month'), $this->column('status', 'Status'), $this->column('staff', 'Staff', 'number'), $this->column('gross', 'Gross', 'money'), $this->column('deductions', 'Deductions', 'money'), $this->column('net', 'Net pay', 'money')],
            'rows' => $rows->all(),
            'totals' => ['month' => 'Total', 'status' => '', 'staff' => '', 'gross' => $rows->sum('gross'), 'deductions' => $rows->sum('deductions'), 'net' => $rows->sum('net')],
            'chart' => ['label' => 'month', 'value' => 'net'],
        ];
    }

    /* ---------- Operations ---------- */

    /**
     * @return array<string, mixed>
     */
    private function expensesByCategory(string $from, string $to): array
    {
        $rows = DB::table('expenses')->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->whereIn('expenses.status', ['approved', 'paid'])->whereNull('expenses.deleted_at')
            ->whereDate('expenses.expense_date', '>=', $from)->whereDate('expenses.expense_date', '<=', $to)
            ->groupBy('expense_categories.name')->orderByDesc(DB::raw('sum(expenses.amount + expenses.tax_amount)'))
            ->selectRaw("expense_categories.name as category, count(*) as entries, sum(case when expenses.status = 'paid' then expenses.amount + expenses.tax_amount else 0 end) as paid, sum(expenses.amount + expenses.tax_amount) as amount")
            ->get()->map(fn (object $row): array => ['category' => $row->category ?? 'Uncategorised', 'entries' => (int) $row->entries, 'paid' => (int) $row->paid, 'unpaid' => (int) $row->amount - (int) $row->paid, 'amount' => (int) $row->amount]);

        return [
            'columns' => [$this->column('category', 'Category'), $this->column('entries', 'Entries', 'number'), $this->column('paid', 'Paid', 'money'), $this->column('unpaid', 'Approved, unpaid', 'money'), $this->column('amount', 'Total', 'money')],
            'rows' => $rows->all(),
            'totals' => ['category' => 'Total', 'entries' => $rows->sum('entries'), 'paid' => $rows->sum('paid'), 'unpaid' => $rows->sum('unpaid'), 'amount' => $rows->sum('amount')],
            'chart' => ['label' => 'category', 'value' => 'amount'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stockValuation(): array
    {
        $query = DB::table('stock_levels')->join('inventory_items', 'inventory_items.id', '=', 'stock_levels.inventory_item_id')
            ->leftJoin('item_categories', 'item_categories.id', '=', 'inventory_items.item_category_id')
            ->whereNull('inventory_items.deleted_at')
            ->groupBy('inventory_items.id', 'inventory_items.sku', 'inventory_items.name', 'item_categories.name', 'inventory_items.reorder_level')
            ->havingRaw('sum(stock_levels.quantity) <> 0')
            ->orderBy('item_categories.name')->orderBy('inventory_items.name')
            ->selectRaw('inventory_items.sku, inventory_items.name, item_categories.name as category, inventory_items.reorder_level, sum(stock_levels.quantity) as quantity, sum(stock_levels.quantity * stock_levels.average_cost) as value');

        $value = (int) DB::table('stock_levels')->join('inventory_items', 'inventory_items.id', '=', 'stock_levels.inventory_item_id')->whereNull('inventory_items.deleted_at')->sum(DB::raw('stock_levels.quantity * stock_levels.average_cost'));

        return [
            'columns' => [$this->column('sku', 'SKU'), $this->column('name', 'Item'), $this->column('category', 'Category'), $this->column('quantity', 'In hand', 'number'), $this->column('reorder', 'Reorder at', 'number'), $this->column('average_cost', 'Average cost', 'money'), $this->column('value', 'Value', 'money')],
            'query' => $query,
            'map' => fn (object $row): array => ['sku' => $row->sku, 'name' => $row->name, 'category' => $row->category, 'quantity' => (float) $row->quantity, 'reorder' => $row->reorder_level === null ? null : (float) $row->reorder_level, 'average_cost' => $row->quantity > 0 ? (int) round($row->value / $row->quantity) : 0, 'value' => (int) round($row->value)],
            'totals' => ['sku' => '', 'name' => 'Total', 'category' => '', 'quantity' => '', 'reorder' => '', 'average_cost' => '', 'value' => $value],
            'summary' => [['Stock value', $value, 'money']],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function assetRegister(): array
    {
        $rows = DB::table('assets')->leftJoin('asset_categories', 'asset_categories.id', '=', 'assets.asset_category_id')->whereNull('assets.deleted_at')
            ->groupBy('asset_categories.name')->orderBy('asset_categories.name')
            ->selectRaw("asset_categories.name as category, count(*) as total, sum(case when assets.status = 'in_store' then 1 else 0 end) as in_store,
                sum(case when assets.status = 'assigned' then 1 else 0 end) as assigned, sum(case when assets.status = 'in_repair' then 1 else 0 end) as in_repair,
                sum(case when assets.status in ('lost', 'disposed') then 1 else 0 end) as gone, sum(assets.purchase_cost) as cost,
                sum(case when assets.status in ('lost', 'disposed') then 0 else assets.current_book_value end) as book_value")
            ->get()->map(fn (object $row): array => ['category' => $row->category ?? 'Uncategorised', 'total' => (int) $row->total, 'in_store' => (int) $row->in_store, 'assigned' => (int) $row->assigned, 'in_repair' => (int) $row->in_repair, 'gone' => (int) $row->gone, 'cost' => (int) $row->cost, 'book_value' => (int) $row->book_value]);

        return [
            'columns' => [$this->column('category', 'Category'), $this->column('total', 'Assets', 'number'), $this->column('in_store', 'In store', 'number'), $this->column('assigned', 'Handed out', 'number'), $this->column('in_repair', 'In repair', 'number'), $this->column('gone', 'Lost / disposed', 'number'), $this->column('cost', 'Cost', 'money'), $this->column('book_value', 'Book value', 'money')],
            'rows' => $rows->all(),
            'totals' => ['category' => 'Total', 'total' => $rows->sum('total'), 'in_store' => $rows->sum('in_store'), 'assigned' => $rows->sum('assigned'), 'in_repair' => $rows->sum('in_repair'), 'gone' => $rows->sum('gone'), 'cost' => $rows->sum('cost'), 'book_value' => $rows->sum('book_value')],
            'chart' => ['label' => 'category', 'value' => 'book_value'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transportOccupancy(): array
    {
        $rows = DB::table('transport_routes')->leftJoin('vehicles', 'vehicles.id', '=', 'transport_routes.vehicle_id')
            ->leftJoin('student_transport', fn ($join) => $join->on('student_transport.transport_route_id', '=', 'transport_routes.id')->where('student_transport.status', 'active')->whereNull('student_transport.deleted_at'))
            ->where('transport_routes.is_active', true)->whereNull('transport_routes.deleted_at')
            ->groupBy('transport_routes.id', 'transport_routes.name', 'vehicles.registration_no', 'vehicles.capacity', 'transport_routes.monthly_fee')->orderBy('transport_routes.name')
            ->selectRaw('transport_routes.name as route, vehicles.registration_no as vehicle, vehicles.capacity as seats, transport_routes.monthly_fee as fee, count(student_transport.id) as riders')
            ->get()->map(fn (object $row): array => ['route' => $row->route, 'vehicle' => $row->vehicle, 'seats' => (int) $row->seats, 'riders' => (int) $row->riders, 'occupancy' => $row->seats > 0 ? round($row->riders * 100 / $row->seats, 1) : null, 'fee' => (int) $row->fee]);

        return [
            'columns' => [$this->column('route', 'Route'), $this->column('vehicle', 'Vehicle'), $this->column('seats', 'Seats', 'number'), $this->column('riders', 'Riders', 'number'), $this->column('occupancy', 'Occupancy', 'percent'), $this->column('fee', 'Route fee', 'money')],
            'rows' => $rows->all(),
            'totals' => ['route' => 'Total', 'vehicle' => '', 'seats' => $rows->sum('seats'), 'riders' => $rows->sum('riders'), 'occupancy' => $rows->sum('seats') > 0 ? round($rows->sum('riders') * 100 / $rows->sum('seats'), 1) : null, 'fee' => ''],
            'chart' => ['label' => 'route', 'value' => 'riders'],
        ];
    }

    /* ---------- Helpers ---------- */

    /**
     * @return array{key: string, label: string, type: string}
     */
    private function column(string $key, string $label, string $type = 'text'): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type];
    }

    /**
     * Join the class of each student's current enrollment.
     */
    private function joinCurrentClass(Builder $query, string $studentColumn): Builder
    {
        $session = AcademicSession::where('is_current', true)->value('id') ?? 0;

        return $query->leftJoin('enrollments', fn ($join) => $join->on('enrollments.student_id', '=', $studentColumn)->where('enrollments.academic_session_id', $session)->whereNull('enrollments.deleted_at'))
            ->leftJoin('classes', 'classes.id', '=', 'enrollments.class_id');
    }

    /**
     * A CSV cell: money in rupees, percentages as numbers.
     */
    private function plain(mixed $value, string $type): mixed
    {
        return match (true) {
            $value === null || $value === '' => '',
            $type === 'money' => number_format($value / 100, 2, '.', ''),
            default => $value,
        };
    }
}
