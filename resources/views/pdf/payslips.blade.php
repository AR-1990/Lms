{{-- One payslip per A4 page: earnings and deductions side by side, attendance summary, net pay. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 14mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; color: #1f2937; }
        .slip { page-break-after: always; }
        .slip:last-child { page-break-after: auto; }
        .head { border-bottom: 2.5pt solid #1d2b45; padding-bottom: 6pt; margin-bottom: 10pt; }
        .head h1 { margin: 0; font-size: 16pt; color: #1d2b45; }
        .head p { margin: 2pt 0 0; color: #6b7280; }
        .tag { float: right; margin-top: 2pt; padding: 3pt 9pt; border: 1pt solid #c9a227; color: #a16207; font-weight: bold; font-size: 10pt; }
        table { width: 100%; border-collapse: collapse; }
        .info td { padding: 2.5pt 0; }
        .info td.label { width: 22%; color: #6b7280; }
        .lines { margin-top: 12pt; }
        .lines th { background: #1d2b45; color: #fff; text-align: left; padding: 5pt 7pt; font-size: 9pt; }
        .lines td { padding: 4.5pt 7pt; border-bottom: 0.5pt solid #e5e7eb; vertical-align: top; }
        .lines td.amount, .lines th.amount { text-align: right; }
        .lines tr.total td { font-weight: bold; border-top: 1pt solid #1d2b45; border-bottom: 0; background: #f8f5ec; }
        .note { color: #6b7280; font-size: 8pt; }
        .net { margin-top: 14pt; padding: 10pt 12pt; border: 1.5pt solid #1d2b45; background: #fffdf5; }
        .net strong { float: right; font-size: 15pt; color: #1d2b45; }
        .days { margin-top: 12pt; }
        .days td { border: 0.5pt solid #e5e7eb; padding: 5pt; text-align: center; width: 16.6%; }
        .leave-line { margin: 5pt 0 0; color: #4b5563; font-size: 8.5pt; }
        .days span { display: block; color: #6b7280; font-size: 7.5pt; }
        .foot { margin-top: 24pt; color: #9ca3af; font-size: 7.5pt; text-align: center; }
    </style>
</head>
<body>
@foreach ($payslips as $payslip)
    @php($employee = $payslip->employee)
    @php($earnings = $payslip->items->where('type', 'earning')->values())
    @php($deductions = $payslip->items->where('type', 'deduction')->values())
    @php($rows = max($earnings->count(), $deductions->count()))
    <div class="slip">
        <div class="head">
            <span class="tag">{{ $payslip->status === 'paid' ? 'PAID' : strtoupper($payslip->status) }}</span>
            <h1>{{ $schoolName }}</h1>
            <p>Salary slip for {{ $payslip->run->month->format('F Y') }}</p>
        </div>

        <table class="info">
            <tr><td class="label">Employee</td><td><strong>{{ $employee?->fullName() }}</strong></td><td class="label">Employee code</td><td>{{ $employee?->employee_code }}</td></tr>
            <tr><td class="label">Designation</td><td>{{ $employee?->designation?->name ?? '—' }}</td><td class="label">Department</td><td>{{ $employee?->department?->name ?? '—' }}</td></tr>
            <tr><td class="label">Bank account</td><td>{{ $employee?->bank_account_no ?? '—' }}</td><td class="label">Paid</td><td>{{ $payslip->paid_on ? $payslip->paid_on->format('d M Y').' · '.($payslip->paymentMethod?->name ?? '') : 'Not yet' }}</td></tr>
        </table>

        <table class="days">
            <tr>
                <td>{{ rtrim(rtrim(number_format($payslip->working_days, 1), '0'), '.') }}<span>working days</span></td>
                <td>{{ rtrim(rtrim(number_format($payslip->present_days, 1), '0'), '.') }}<span>present</span></td>
                <td>{{ rtrim(rtrim(number_format($payslip->leave_days, 1), '0'), '.') }}<span>paid leave</span></td>
                <td>{{ rtrim(rtrim(number_format($payslip->unpaid_leave_days, 1), '0'), '.') }}<span>unpaid leave</span></td>
                <td>{{ rtrim(rtrim(number_format($payslip->absent_days, 1), '0'), '.') }}<span>absent</span></td>
                <td>{{ $payslip->late_count }}<span>late{{ $payslip->late_minutes ? ' · '.$payslip->late_minutes.' min' : '' }}</span></td>
            </tr>
        </table>

        @if ($payslip->leaves->isNotEmpty())
            <p class="leave-line">Leave taken: {{ $payslip->leaves->map(fn ($leave) => rtrim(rtrim(number_format($leave->days, 1), '0'), '.').' '.($leave->leaveType?->name ?? '').($leave->leaveType?->is_paid === false ? ' (unpaid)' : ''))->implode(' · ') }}</p>
        @endif

        <table class="lines">
            <tr><th>Earnings</th><th class="amount">Rs</th><th>Deductions</th><th class="amount">Rs</th></tr>
            @for ($i = 0; $i < $rows; $i++)
                <tr>
                    <td>{{ $earnings[$i]->component->name ?? '' }}@if (isset($earnings[$i]) && $earnings[$i]->note) <span class="note">({{ $earnings[$i]->note }})</span>@endif</td>
                    <td class="amount">{{ isset($earnings[$i]) ? number_format($earnings[$i]->amount / 100) : '' }}</td>
                    <td>{{ $deductions[$i]->component->name ?? '' }}@if (isset($deductions[$i]) && $deductions[$i]->note) <span class="note">({{ $deductions[$i]->note }})</span>@endif</td>
                    <td class="amount">{{ isset($deductions[$i]) ? number_format($deductions[$i]->amount / 100) : '' }}</td>
                </tr>
            @endfor
            <tr class="total">
                <td>Gross pay</td><td class="amount">{{ number_format($payslip->gross / 100) }}</td>
                <td>Total deductions</td><td class="amount">{{ number_format($payslip->deductions / 100) }}</td>
            </tr>
        </table>

        <div class="net">Net pay <strong>Rs {{ number_format($payslip->net / 100) }}</strong></div>

        <p class="foot">This is a computer-generated payslip and needs no signature.</p>
    </div>
@endforeach
</body>
</html>
