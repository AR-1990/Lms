<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Employee;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminService
{
    public function __construct(
        private SystemSettingsService $systemSettingsService,
        private LookupService $lookupService,
    ) {}

    /**
     * Get system-wide dashboard overview and statistics directly via Eloquent SQL.
     */
    public function getDashboardSummary(): array
    {
        $rolesBreakdown = Role::withCount('users')->orderBy('name')->get();
        $roleCounts = $rolesBreakdown->pluck('users_count', 'slug');
        $recentUsers = User::with('roles:id,name,slug')->latest()->latest('id')->take(5)->get();
        $settings = $this->systemSettingsService->getSettingsPayload();

        return [
            'overview' => [
                'total_users' => User::count(),
                'total_admins' => $roleCounts->get('admin', 0),
                'total_teachers' => $roleCounts->get('teacher', 0),
                'total_students' => $roleCounts->get('student', 0),
                'total_roles' => $rolesBreakdown->count(),
                'new_this_month' => User::where('created_at', '>=', now()->startOfMonth())->count(),
            ],
            'daily_quote' => $this->lookupService->dailyQuote(),
            'monthly_signups' => $this->getMonthlySignups(6),
            'recent_users' => $recentUsers,
            'roles_breakdown' => $rolesBreakdown,
            'system_status' => [
                'api_version' => '1.0.0',
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'status' => 'operational',
                'timezone' => $settings['timezone'],
                'school_name' => $settings['school_name'] ?? config('app.name'),
                'currency' => $settings['currency']['code'] ?? null,
            ],
        ];
    }

    /**
     * Money picture for the admin home: fee collection, pending fees and spending, live from the ledger tables.
     * Amounts are paisa. Each part is one grouped query so the page stays fast as data grows.
     *
     * @return array<string, mixed>
     */
    public function getFinanceDashboard(int $months = 12): array
    {
        $today = today();
        $monthStart = $today->copy()->startOfMonth();
        $previousStart = $monthStart->copy()->subMonth();
        $previousSameDay = $previousStart->copy()->addDays(min($today->day, $previousStart->daysInMonth) - 1);
        $firstMonth = $monthStart->copy()->subMonths($months - 1);
        $receipts = fn () => DB::table('fee_payments')->where('fee_payments.status', 'posted');
        $spending = fn () => DB::table('expenses')->whereIn('expenses.status', ['approved', 'paid'])->whereNull('expenses.deleted_at');
        $open = fn () => DB::table('fee_invoices')->whereIn('fee_invoices.status', FeeInvoice::OPEN_STATUSES)->where('fee_invoices.balance', '>', 0);
        $between = fn ($query, string $column, Carbon $from, Carbon $to) => $query->whereDate($column, '>=', $from->toDateString())->whereDate($column, '<=', $to->toDateString());

        $collected = $receipts()->whereDate('received_on', '>=', $firstMonth->toDateString())
            ->selectRaw('substr(received_on, 1, 7) as month, sum(amount) as total')->groupByRaw('substr(received_on, 1, 7)')->pluck('total', 'month');
        $spent = $spending()->whereDate('expense_date', '>=', $firstMonth->toDateString())
            ->selectRaw('substr(expense_date, 1, 7) as month, sum(amount + tax_amount) as total')->groupByRaw('substr(expense_date, 1, 7)')->pluck('total', 'month');
        $salaries = DB::table('payroll_runs')->whereIn('status', ['approved', 'posted', 'paid'])->whereNull('deleted_at')->whereDate('month', '>=', $firstMonth->toDateString())
            ->selectRaw('substr(month, 1, 7) as month, sum(total_gross) as total')->groupByRaw('substr(month, 1, 7)')->pluck('total', 'month');

        $trend = collect(range(0, $months - 1))->map(function (int $offset) use ($firstMonth, $collected, $spent, $salaries): array {
            $key = $firstMonth->copy()->addMonths($offset)->format('Y-m');

            return [
                'month' => $key, 'label' => Carbon::parse($key.'-01')->format('M y'),
                'collected' => (int) ($collected[$key] ?? 0), 'expenses' => (int) ($spent[$key] ?? 0), 'salaries' => (int) ($salaries[$key] ?? 0),
            ];
        })->all();

        $daily = $between($receipts(), 'fee_payments.received_on', $monthStart, $today)
            ->selectRaw('substr(received_on, 1, 10) as day, sum(amount) as total, count(*) as receipts')->groupByRaw('substr(received_on, 1, 10)')->get()->keyBy('day');
        $day = fn (int $offset) => $monthStart->copy()->addDays($offset)->toDateString();

        $asOf = $today->toDateString();
        $ageing = $open()->whereDate('due_date', '<', $asOf)->selectRaw(
            'sum(case when due_date >= ? then balance else 0 end) as d30, sum(case when due_date < ? and due_date >= ? then balance else 0 end) as d60, '
            .'sum(case when due_date < ? and due_date >= ? then balance else 0 end) as d90, sum(case when due_date < ? then balance else 0 end) as over90, count(distinct student_id) as students',
            [$today->copy()->subDays(30)->toDateString(), $today->copy()->subDays(30)->toDateString(), $today->copy()->subDays(60)->toDateString(), $today->copy()->subDays(60)->toDateString(), $today->copy()->subDays(90)->toDateString(), $today->copy()->subDays(90)->toDateString()]
        )->first();

        $collectedThisMonth = (int) $between($receipts(), 'fee_payments.received_on', $monthStart, $today)->sum('amount');
        $collectedLastMonth = (int) $between($receipts(), 'fee_payments.received_on', $previousStart, $previousSameDay)->sum('amount');
        $spentThisMonth = (int) $between($spending(), 'expenses.expense_date', $monthStart, $today)->sum(DB::raw('amount + tax_amount'));
        $spentLastMonth = (int) $between($spending(), 'expenses.expense_date', $previousStart, $previousSameDay)->sum(DB::raw('amount + tax_amount'));
        $billedThisMonth = (int) DB::table('fee_invoices')->whereIn('status', [...FeeInvoice::OPEN_STATUSES, 'paid'])->whereDate('issue_date', '>=', $monthStart->toDateString())->sum('total');

        return [
            'as_of' => $asOf,
            'kpis' => [
                'collected_today' => (int) $receipts()->whereDate('received_on', $asOf)->sum('amount'),
                'receipts_today' => $receipts()->whereDate('received_on', $asOf)->count(),
                'collected_month' => $collectedThisMonth,
                'collected_change' => $this->change($collectedThisMonth, $collectedLastMonth),
                'billed_month' => $billedThisMonth,
                'recovery_month' => $billedThisMonth > 0 ? round(min(100, $collectedThisMonth * 100 / $billedThisMonth), 1) : null,
                'outstanding' => (int) $open()->sum('balance'),
                'open_challans' => $open()->count(),
                'overdue' => (int) (($ageing->d30 ?? 0) + ($ageing->d60 ?? 0) + ($ageing->d90 ?? 0) + ($ageing->over90 ?? 0)),
                'overdue_students' => (int) ($ageing->students ?? 0),
                'expenses_month' => $spentThisMonth,
                'expenses_change' => $this->change($spentThisMonth, $spentLastMonth),
                'awaiting_payment' => (int) DB::table('expenses')->where('status', 'approved')->whereNull('deleted_at')->sum(DB::raw('amount + tax_amount')),
                'salaries_month' => (int) ($salaries[$monthStart->format('Y-m')] ?? 0),
                'students' => DB::table('enrollments')->where('status', 'active')->whereNull('deleted_at')->where('academic_session_id', AcademicSession::where('is_current', true)->value('id'))->count(),
                'staff' => Employee::whereIn('status', Employee::CURRENT_STATUSES)->count(),
            ],
            'trend' => $trend,
            'daily' => collect(range(0, $today->day - 1))->map(fn (int $offset): array => ['day' => $day($offset), 'collected' => (int) ($daily[$day($offset)]->total ?? 0), 'receipts' => (int) ($daily[$day($offset)]->receipts ?? 0)])->all(),
            'ageing' => [
                ['label' => '1–30 days', 'amount' => (int) ($ageing->d30 ?? 0)], ['label' => '31–60 days', 'amount' => (int) ($ageing->d60 ?? 0)],
                ['label' => '61–90 days', 'amount' => (int) ($ageing->d90 ?? 0)], ['label' => '90+ days', 'amount' => (int) ($ageing->over90 ?? 0)],
            ],
            'methods' => $between($receipts(), 'fee_payments.received_on', $monthStart, $today)->leftJoin('payment_methods', 'payment_methods.id', '=', 'fee_payments.payment_method_id')
                ->groupBy('payment_methods.name')->orderByDesc(DB::raw('sum(fee_payments.amount)'))
                ->selectRaw('payment_methods.name as label, sum(fee_payments.amount) as amount, count(*) as receipts')->get()
                ->map(fn (object $row): array => ['label' => $row->label ?? 'Other', 'amount' => (int) $row->amount, 'receipts' => (int) $row->receipts])->all(),
            'expense_categories' => $between($spending(), 'expenses.expense_date', $monthStart, $today)->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
                ->groupBy('expense_categories.name')->orderByDesc(DB::raw('sum(expenses.amount + expenses.tax_amount)'))->limit(6)
                ->selectRaw('expense_categories.name as label, sum(expenses.amount + expenses.tax_amount) as amount')->get()
                ->map(fn (object $row): array => ['label' => $row->label ?? 'Uncategorised', 'amount' => (int) $row->amount])->all(),
            'pending_by_class' => $open()->leftJoin('enrollments', 'enrollments.id', '=', 'fee_invoices.enrollment_id')->leftJoin('classes', 'classes.id', '=', 'enrollments.class_id')
                ->groupBy('classes.id', 'classes.name')->orderByDesc(DB::raw('sum(fee_invoices.balance)'))->limit(6)
                ->selectRaw('classes.id as class_id, classes.name as label, sum(fee_invoices.balance) as amount, count(distinct fee_invoices.student_id) as students')->get()
                ->map(fn (object $row): array => ['class_id' => $row->class_id, 'label' => $row->label ?? 'Admission / other', 'amount' => (int) $row->amount, 'students' => (int) $row->students])->all(),
            'recent_receipts' => FeePayment::where('status', 'posted')->with(['student:id,first_name,middle_name,last_name', 'paymentMethod:id,name'])->latest('received_on')->latest('id')->limit(6)
                ->get(['id', 'receipt_no', 'student_id', 'payment_method_id', 'amount', 'received_on'])
                ->map(fn (FeePayment $payment): array => ['receipt_no' => $payment->receipt_no, 'student' => $payment->student?->fullName(), 'method' => $payment->paymentMethod?->name, 'amount' => (int) $payment->amount, 'received_on' => $payment->received_on?->toDateString()])->all(),
            'top_defaulters' => $open()->whereDate('due_date', '<', $asOf)->join('students', 'students.id', '=', 'fee_invoices.student_id')
                ->groupBy('fee_invoices.student_id', 'students.first_name', 'students.last_name', 'students.admission_no')->orderByDesc(DB::raw('sum(fee_invoices.balance)'))->limit(5)
                ->selectRaw('students.first_name, students.last_name, students.admission_no, sum(fee_invoices.balance) as amount, min(fee_invoices.due_date) as oldest_due')->get()
                ->map(fn (object $row): array => ['student' => trim($row->first_name.' '.$row->last_name), 'admission_no' => $row->admission_no, 'amount' => (int) $row->amount, 'oldest_due' => substr((string) $row->oldest_due, 0, 10)])->all(),
        ];
    }

    /**
     * Percent change against the same days of last month (null when last month had nothing).
     */
    private function change(int $current, int $previous): ?float
    {
        return $previous > 0 ? round(($current - $previous) * 100 / $previous, 1) : null;
    }

    /**
     * New accounts per month for the last N months, oldest first (months with none included).
     *
     * @return array<int, array{label: string, count: int}>
     */
    private function getMonthlySignups(int $months): array
    {
        $firstMonth = now()->startOfMonth()->subMonths($months - 1);

        $countsByMonth = User::where('created_at', '>=', $firstMonth)
            ->pluck('created_at')
            ->countBy(fn ($createdAt): string => $createdAt->format('Y-m'));

        return collect(range(0, $months - 1))
            ->map(function (int $offset) use ($firstMonth, $countsByMonth): array {
                $month = $firstMonth->copy()->addMonths($offset);

                return [
                    'label' => $month->format('M'),
                    'count' => $countsByMonth->get($month->format('Y-m'), 0),
                ];
            })
            ->all();
    }

    /**
     * Operational reports snapshot for administrators.
     */
    public function getReportsSnapshot(): array
    {
        $summary = $this->getDashboardSummary();

        return [
            'overview' => $summary['overview'],
            'roles_breakdown' => $summary['roles_breakdown'],
            'reports' => [],
            'kpis' => [],
        ];
    }
}
