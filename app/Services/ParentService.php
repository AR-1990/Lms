<?php

namespace App\Services;

use App\Http\Resources\AnnouncementResource;
use App\Models\FeeInvoice;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The signed-in parent's view of their own children: class, attendance, results, fees and notices.
 */
class ParentService
{
    public function __construct(private StudentService $studentService, private AnnouncementService $announcementService) {}

    /**
     * @return array{parent: array<string, mixed>|null, children: array<int, array<string, mixed>>, summary: array<string, int>, recent_notices: array<int, array<string, mixed>>}
     */
    public function getParentDashboard(User $user): array
    {
        $guardian = $this->guardianFor($user);
        $children = $this->childRows($this->children($guardian));
        $studentIds = array_column($children, 'id');

        return [
            'parent' => $guardian === null ? null : ['id' => $guardian->id, 'name' => $guardian->name, 'phone' => $guardian->phone],
            'children' => $children,
            'summary' => [
                'children' => count($children),
                'fee_outstanding' => array_sum(array_column($children, 'fee_balance')),
                'open_invoices' => array_sum(array_column($children, 'open_invoices')),
                'present_today' => StudentAttendance::whereIn('student_id', $studentIds)->whereDate('date', today())->whereIn('status', StudentAttendance::ATTENDED)->count(),
            ],
            'recent_notices' => AnnouncementResource::collection($this->announcementService->feedFor($user)->limit(5)->get())->resolve(),
        ];
    }

    /**
     * @return array{children: array<int, array<string, mixed>>}
     */
    public function getChildrenProfiles(User $user): array
    {
        return ['children' => $this->childRows($this->children($this->guardianFor($user)))];
    }

    /**
     * Fee invoices of the parent's children, newest first, a page at a time.
     *
     * @return array{summary: array<string, mixed>, ledger: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    public function getFeeLedger(User $user, int $page = 1): array
    {
        $studentIds = $this->children($this->guardianFor($user))->pluck('id');
        $invoices = FeeInvoice::whereIn('student_id', $studentIds)->where('status', '!=', 'cancelled');
        $open = (clone $invoices)->whereIn('status', FeeInvoice::OPEN_STATUSES);
        $next = (clone $open)->orderBy('due_date')->first(['due_date', 'balance']);
        $paginator = (clone $invoices)->with('student:id,first_name,middle_name,last_name')
            ->latest('issue_date')->latest('id')
            ->paginate(20, ['id', 'invoice_no', 'student_id', 'invoice_type', 'billing_month', 'issue_date', 'due_date', 'total', 'paid_total', 'balance', 'status'], 'page', $page);

        return [
            'summary' => [
                'outstanding' => (int) (clone $open)->sum('balance'),
                'paid_total' => (int) (clone $invoices)->sum('paid_total'),
                'open_invoices' => (clone $open)->count(),
                'next_due_date' => $next?->due_date?->toDateString(),
                'next_due_amount' => $next === null ? null : (int) $next->balance,
            ],
            'ledger' => collect($paginator->items())->map(fn (FeeInvoice $invoice): array => [
                'id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'student' => $invoice->student?->fullName(),
                'description' => ucfirst($invoice->invoice_type).($invoice->billing_month ? ' · '.$invoice->billing_month->format('M Y') : ''),
                'due_date' => $invoice->due_date?->toDateString(),
                'total' => (int) $invoice->total,
                'balance' => (int) $invoice->balance,
                'status' => $invoice->status,
            ])->all(),
            'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total()],
        ];
    }

    public function guardianFor(User $user): ?Guardian
    {
        return Guardian::where('user_id', $user->id)->first(['id', 'user_id', 'name', 'phone']);
    }

    /**
     * @return Collection<int, Student>
     */
    private function children(?Guardian $guardian): Collection
    {
        if ($guardian === null) {
            return collect();
        }

        return $guardian->students()->whereNotIn('current_status', Student::LEAVING_STATUSES)
            ->with(['currentEnrollment.section:id,name,class_id', 'currentEnrollment.schoolClass:id,name', 'currentEnrollment.academicSession:id,starts_on'])
            ->get();
    }

    /**
     * @param  Collection<int, Student>  $children
     * @return array<int, array<string, mixed>>
     */
    private function childRows(Collection $children): array
    {
        return $children->map(fn (Student $student): array => [...$this->studentService->profile($student), ...$this->studentService->summary($student)])->values()->all();
    }
}
