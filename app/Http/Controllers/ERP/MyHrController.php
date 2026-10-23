<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\ApplyLeaveRequest;
use App\Http\Requests\Hr\RequestLoanRequest;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\LeaveRequest;
use App\Models\Payslip;
use App\Services\LeaveService;
use App\Services\LoanService;
use App\Services\PayrollService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Employee self-service (web "My HR" and the mobile app): own leave, payslips and loans. Anyone with an
 * employee record may use it; nobody sees another person's data here.
 */
class MyHrController extends Controller
{
    use ApiResponse;

    public function leave(Request $request, LeaveService $leaveService): JsonResponse
    {
        $employee = $this->employee($request);

        return $this->successResponse([
            'balances' => $leaveService->balances($employee),
            'requests' => LeaveRequest::where('employee_id', $employee->id)->with('leaveType:id,name')->latest('from_date')->limit(50)->get()
                ->map(fn (LeaveRequest $leave): array => [
                    ...$leave->only(['id', 'days', 'is_half_day', 'reason', 'status', 'review_remarks']),
                    'leave_type' => $leave->leaveType?->name, 'from_date' => $leave->from_date->toDateString(), 'to_date' => $leave->to_date->toDateString(),
                ]),
        ], 'Your leave.');
    }

    public function applyLeave(ApplyLeaveRequest $request, LeaveService $leaveService): JsonResponse
    {
        return $this->successResponse($leaveService->apply($this->employee($request), $request->validated(), $request->user()), 'Leave request sent for approval.', 201);
    }

    public function cancelLeave(Request $request, LeaveRequest $leaveRequest, LeaveService $leaveService): JsonResponse
    {
        abort_unless($leaveRequest->employee_id === $this->employee($request)->id, 404);

        return $this->successResponse($leaveService->cancel($request->user(), $leaveRequest->load('employee', 'leaveType')), 'Leave cancelled.');
    }

    public function payslips(Request $request): JsonResponse
    {
        $payslips = Payslip::where('employee_id', $this->employee($request)->id)->whereIn('status', ['final', 'paid'])->with('run:id,month')->latest('id')->limit(36)->get()
            ->map(fn (Payslip $payslip): array => [
                ...$payslip->only(['id', 'gross', 'deductions', 'net', 'status', 'working_days', 'absent_days', 'leave_days']),
                'month' => $payslip->run?->month?->toDateString(), 'paid_on' => $payslip->paid_on?->toDateString(),
            ]);

        return $this->successResponse($payslips, 'Your payslips.');
    }

    public function payslipPdf(Request $request, Payslip $payslip, PayrollService $payrollService): Response
    {
        abort_unless($payslip->employee_id === $this->employee($request)->id && in_array($payslip->status, ['final', 'paid'], true), 404);

        return $payrollService->pdf(Payslip::whereKey($payslip->id)->get(), 'payslip.pdf');
    }

    public function loans(Request $request): JsonResponse
    {
        $loans = EmployeeLoan::where('employee_id', $this->employee($request)->id)->with(['loanType:id,name', 'instalmentRows:id,employee_loan_id,due_month,amount,status'])->latest('id')->get();

        return $this->successResponse($loans, 'Your loans.');
    }

    public function requestLoan(RequestLoanRequest $request, LoanService $loanService): JsonResponse
    {
        return $this->successResponse($loanService->request($this->employee($request), $request->validated(), $request->user()), 'Loan request sent to the administrator.', 201);
    }

    private function employee(Request $request): Employee
    {
        return Employee::where('user_id', $request->user()->id)->first() ?? abort(404, 'Your login is not linked to an employee record.');
    }
}
