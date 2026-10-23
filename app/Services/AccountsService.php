<?php

namespace App\Services;

use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\PayrollRun;
use App\Models\User;

/**
 * Accounts home page: today's fee collections, open and overdue vouchers, the latest payroll run and
 * the latest receipts. The full screens are Fees (vouchers, receipts) and HR → Payroll.
 */
class AccountsService
{
    /**
     * @return array{summary: array<string, mixed>, recent_payments: array<int, array<string, mixed>>}
     */
    public function getAccountsDashboard(User $user): array
    {
        $posted = FeePayment::where('status', 'posted');
        $open = FeeInvoice::whereIn('status', FeeInvoice::OPEN_STATUSES);
        $payroll = PayrollRun::latest('month')->first(['id', 'month', 'status', 'total_net', 'employee_count']);

        return [
            'summary' => [
                'collected_today' => (int) (clone $posted)->whereDate('received_on', today())->sum('amount'),
                'collected_this_month' => (int) (clone $posted)->whereBetween('received_on', [today()->startOfMonth()->toDateString(), today()->toDateString()])->sum('amount'),
                'open_invoices' => (clone $open)->count(),
                'overdue_invoices' => (clone $open)->whereDate('due_date', '<', today())->count(),
                'outstanding' => (int) (clone $open)->sum('balance'),
                'payroll' => $payroll === null ? null : [
                    'id' => $payroll->id,
                    'month' => $payroll->month?->format('M Y'),
                    'status' => $payroll->status,
                    'total_net' => (int) $payroll->total_net,
                    'employee_count' => (int) $payroll->employee_count,
                ],
            ],
            'recent_payments' => (clone $posted)->with(['student:id,first_name,middle_name,last_name', 'paymentMethod:id,name'])
                ->latest('received_on')->latest('id')->limit(8)
                ->get(['id', 'receipt_no', 'student_id', 'payment_method_id', 'amount', 'received_on'])
                ->map(fn (FeePayment $payment): array => [
                    'receipt_no' => $payment->receipt_no,
                    'student' => $payment->student?->fullName(),
                    'method' => $payment->paymentMethod?->name,
                    'amount' => (int) $payment->amount,
                    'received_on' => $payment->received_on?->toDateString(),
                ])->all(),
        ];
    }
}
