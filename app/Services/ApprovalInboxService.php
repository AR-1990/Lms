<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\Expense;
use App\Models\LeaveRequest;
use App\Models\PurchaseOrder;
use App\Models\Section;
use App\Models\StudentConcession;
use App\Models\StudentLeaveRequest;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Everything waiting for the signed-in user's decision, from every module, in one list. Each item carries the
 * exact approve / reject call (the module's own endpoint), so the web inbox and the mobile apps act the same way.
 */
class ApprovalInboxService
{
    private const LIMIT = 50;

    public function __construct(private LeaveService $leaveService) {}

    /**
     * @return array{total: int, sections: array<int, array{key: string, label: string, count: int, items: array<int, array<string, mixed>>}>}
     */
    public function inbox(User $user): array
    {
        $sections = array_values(array_filter([
            $user->hasPermission('approve-requests') ? $this->section('requests', 'Deletions & purchase requests', ApprovalRequest::with('requester:id,name')->where('status', 'pending')->latest('requested_at'), fn (ApprovalRequest $request): array => $this->item(
                $request->id, Str::headline($request->request_type).': '.$request->subject_label, $request->reason, $request->amount, $request->requester?->name, $request->requested_at,
                ['method' => 'POST', 'url' => "/api/erp/approvals/{$request->id}/approve", 'body' => []],
                ['method' => 'POST', 'url' => "/api/erp/approvals/{$request->id}/reject", 'reason_field' => 'decision_note'],
            )) : null,

            $user->hasPermission('approve-requests') ? $this->section('expenses', 'Expenses', Expense::with(['category:id,name', 'creator:id,name'])->where('status', 'draft')->latest('id'), fn (Expense $expense): array => $this->item(
                $expense->id, ($expense->category?->name ?? 'Expense').' — '.$expense->expense_no, $expense->description, $expense->total(), $expense->creator?->name, $expense->created_at,
                ['method' => 'POST', 'url' => "/api/erp/expenses/{$expense->id}/approve", 'body' => []],
                ['method' => 'POST', 'url' => "/api/erp/expenses/{$expense->id}/cancel", 'reason_field' => 'reason'],
            )) : null,

            $user->hasPermission('approve-requests') ? $this->section('purchase_orders', 'Purchase orders', PurchaseOrder::with(['vendor:id,name', 'creator:id,name'])->where('status', 'draft')->latest('id'), fn (PurchaseOrder $order): array => $this->item(
                $order->id, 'PO '.$order->po_no.($order->vendor ? ' — '.$order->vendor->name : ''), null, $order->total, $order->creator?->name, $order->created_at,
                ['method' => 'POST', 'url' => "/api/erp/purchase-orders/{$order->id}/approve", 'body' => []],
                ['method' => 'POST', 'url' => "/api/erp/purchase-orders/{$order->id}/cancel", 'reason_field' => 'reason'],
            )) : null,

            $user->hasPermission('approve-requests') ? $this->section('loans', 'Staff loans', EmployeeLoan::with('employee:id,first_name,middle_name,last_name,employee_code')->where('status', 'pending')->latest('id'), fn (EmployeeLoan $loan): array => $this->item(
                $loan->id, 'Loan — '.$loan->employee?->fullName(), $loan->reason, $loan->amount, $loan->employee?->fullName(), $loan->created_at,
                ['method' => 'POST', 'url' => "/api/erp/employee-loans/{$loan->id}/decide", 'body' => ['approve' => true]],
                ['method' => 'POST', 'url' => "/api/erp/employee-loans/{$loan->id}/decide", 'body' => ['approve' => false], 'reason_field' => 'remarks'],
            )) : null,

            $user->hasPermission('manage-fee-setup') ? $this->section('concessions', 'Fee concessions', StudentConcession::with(['student:id,first_name,middle_name,last_name,admission_no', 'concessionType:id,name', 'creator:id,name'])->where('status', 'pending')->latest('id'), fn (StudentConcession $concession): array => $this->item(
                $concession->id, ($concession->concessionType?->name ?? 'Concession').' — '.$concession->student?->fullName(), $concession->calculation === 'percent' ? ($concession->value / 100).'% off'.($concession->reason ? ' · '.$concession->reason : '') : $concession->reason, $concession->calculation === 'percent' ? null : $concession->value, $concession->creator?->name, $concession->created_at,
                ['method' => 'POST', 'url' => "/api/erp/fees/concessions/{$concession->id}/decision", 'body' => ['decision' => 'approved']],
                ['method' => 'POST', 'url' => "/api/erp/fees/concessions/{$concession->id}/decision", 'body' => ['decision' => 'rejected']],
            )) : null,

            $this->section('staff_leave', 'Staff leave', $this->leaveService->requestsQuery($user, ['status' => 'pending'])->whereHas('employee', fn ($query) => $query->where('user_id', '!=', $user->id)->orWhereNull('user_id'))->latest('id'), fn (LeaveRequest $leave): array => $this->item(
                $leave->id, ($leave->leaveType?->name ?? 'Leave').' — '.$leave->employee?->fullName(), $leave->from_date?->format('d M').' to '.$leave->to_date?->format('d M Y').' ('.$leave->days.' day(s))'.($leave->reason ? ' · '.$leave->reason : ''), null, $leave->employee?->fullName(), $leave->created_at,
                ['method' => 'POST', 'url' => "/api/erp/leave-requests/{$leave->id}/decide", 'body' => ['approve' => true]],
                ['method' => 'POST', 'url' => "/api/erp/leave-requests/{$leave->id}/decide", 'body' => ['approve' => false], 'reason_field' => 'remarks'],
            )),

            $user->hasPermission('record-attendance') ? $this->section('student_leave', 'Student leave', StudentLeaveRequest::with(['student:id,first_name,middle_name,last_name,admission_no', 'requester:id,name'])->where('status', 'pending')
                ->when(! $user->hasPermission('manage-students'), fn ($query) => $query->whereIn('student_id', $this->classTeacherStudentIds($user)))->latest('id'), fn (StudentLeaveRequest $leave): array => $this->item(
                    $leave->id, 'Leave — '.$leave->student?->fullName(), $leave->from_date?->format('d M').' to '.$leave->to_date?->format('d M Y').' · '.$leave->reason, null, $leave->requester?->name, $leave->created_at,
                    ['method' => 'POST', 'url' => "/api/erp/leave-requests/{$leave->id}/decision", 'body' => ['decision' => 'approved']],
                    ['method' => 'POST', 'url' => "/api/erp/leave-requests/{$leave->id}/decision", 'body' => ['decision' => 'rejected'], 'reason_field' => 'remarks', 'reason_optional' => true],
                )) : null,
        ]));

        return ['total' => array_sum(array_column($sections, 'count')), 'sections' => $sections];
    }

    /**
     * @return array{key: string, label: string, count: int, items: array<int, array<string, mixed>>}
     */
    private function section(string $key, string $label, $query, callable $map): array
    {
        return ['key' => $key, 'label' => $label, 'count' => (clone $query)->count(), 'items' => $query->limit(self::LIMIT)->get()->map($map)->all()];
    }

    /**
     * @param  array<string, mixed>  $approve
     * @param  array<string, mixed>  $reject
     * @return array<string, mixed>
     */
    private function item(int $id, string $title, ?string $detail, ?int $amount, ?string $by, $at, array $approve, array $reject): array
    {
        return ['id' => $id, 'title' => $title, 'detail' => $detail, 'amount' => $amount, 'requested_by' => $by, 'requested_at' => $at?->toIso8601String(), 'approve' => $approve, 'reject' => $reject + ['body' => []]];
    }

    /**
     * Students of the sections the user is class teacher of (current session).
     *
     * @return array<int, int>
     */
    private function classTeacherStudentIds(User $user): array
    {
        $employeeId = Employee::where('user_id', $user->id)->value('id');

        return Section::where('class_teacher_id', $employeeId ?? 0)
            ->join('enrollments', 'enrollments.section_id', '=', 'sections.id')
            ->where('enrollments.status', 'active')
            ->pluck('enrollments.student_id')
            ->all();
    }
}
