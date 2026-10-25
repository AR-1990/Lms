{{-- Fee challans: one landscape A4 page per challan with the bank, school and student copies side by side. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 8mm 8mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 7.6pt; color: #1f2937; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        table.copies { width: 100%; border-collapse: separate; border-spacing: 4mm 0; table-layout: fixed; }
        td.copy { vertical-align: top; border: 0.8pt dashed #94a3b8; padding: 5pt 6pt; }
        .copy-name { float: right; padding: 1pt 5pt; border: 0.8pt solid #1d2b45; color: #1d2b45; font-size: 6.6pt; font-weight: bold; text-transform: uppercase; }
        h1 { margin: 0; font-size: 10.5pt; color: #1d2b45; }
        .logo { height: 9mm; vertical-align: middle; margin-right: 3pt; }
        .muted { color: #6b7280; }
        .small { font-size: 6.6pt; }
        .bank { margin: 4pt 0; padding: 3pt 4pt; background: #f1f5f9; }
        table.info, table.lines { width: 100%; border-collapse: collapse; }
        table.info td { padding: 1.2pt 0; vertical-align: top; }
        table.info td.label { width: 38%; color: #6b7280; }
        table.lines { margin-top: 4pt; }
        table.lines th { background: #1d2b45; color: #fff; text-align: left; padding: 2pt 3pt; font-size: 6.8pt; }
        table.lines td { padding: 2pt 3pt; border-bottom: 0.4pt solid #e5e7eb; }
        .num { text-align: right; }
        tr.total td { font-weight: bold; border-top: 0.9pt solid #1d2b45; border-bottom: 0; background: #f8f5ec; font-size: 8pt; }
        .late { margin-top: 4pt; padding: 3pt 4pt; border: 0.6pt solid #e9a83a; background: #fff8eb; font-size: 6.8pt; }
        .stamp { margin-top: 10pt; }
        .stamp td { width: 50%; padding-top: 14pt; border-top: 0.5pt solid #9ca3af; text-align: center; color: #6b7280; font-size: 6.6pt; }
        .status-paid { color: #2f7a4a; font-weight: bold; }
    </style>
</head>
<body>
@foreach ($invoices as $invoice)
    @php
        $class = trim(($invoice->enrollment?->schoolClass?->name ?? '').' '.($invoice->enrollment?->section?->name ?? ''));
        $ref = $invoice->student?->admission_no ?? $invoice->application?->application_no;
        $lateRules = $rules->filter(fn ($rule) => $rule->invoice_type === null || $rule->invoice_type === $invoice->invoice_type);
        $fixedLate = (int) $lateRules->where('calculation', 'fixed')->sum('amount');
        $money = fn (int $paisa): string => number_format($paisa / 100);
    @endphp
    <div class="page">
        <table class="copies"><tr>
            @foreach (['Bank copy', 'School copy', 'Student copy'] as $copy)
                <td class="copy">
                    <span class="copy-name">{{ $copy }}</span>
                    <h1>@if ($logo)<img class="logo" src="{{ $logo }}" alt=""> @endif{{ $settings?->school_name }}</h1>
                    <div class="muted small">{{ $settings?->school_address }}{{ $settings?->school_phone ? ' · '.$settings->school_phone : '' }}</div>

                    @if ($bank)
                        <div class="bank small">
                            <strong>Pay at {{ $bank->bank?->name ?? 'the bank' }}</strong>{{ $bank->branch ? ', '.$bank->branch : '' }}<br>
                            {{ $bank->account_title }} · A/C {{ $bank->account_number }}{{ $bank->iban ? ' · IBAN '.$bank->iban : '' }}
                        </div>
                    @endif

                    <table class="info">
                        <tr><td class="label">Challan no</td><td><strong>{{ $invoice->invoice_no }}</strong></td></tr>
                        <tr><td class="label">Student</td><td>{{ $invoice->payerName() }}</td></tr>
                        <tr><td class="label">ID</td><td>{{ $ref ?? '—' }}</td></tr>
                        @if ($class !== '')<tr><td class="label">Class</td><td>{{ $class }}</td></tr>@endif
                        @if ($invoice->billing_month)<tr><td class="label">Fee month</td><td>{{ $invoice->billing_month->format('F Y') }}</td></tr>@endif
                        <tr><td class="label">Issued</td><td>{{ $invoice->issue_date?->format('d M Y') }}</td></tr>
                        <tr><td class="label">Due date</td><td><strong>{{ $invoice->due_date?->format('d M Y') }}</strong></td></tr>
                    </table>

                    <table class="lines">
                        <tr><th>Fee</th><th class="num">Rs</th></tr>
                        @foreach ($invoice->items as $item)
                            <tr><td>{{ $item->description }}@if ($item->concession_amount > 0)<br><span class="muted small">less concession {{ $money($item->concession_amount) }}</span>@endif</td><td class="num">{{ $money($item->net_amount) }}</td></tr>
                        @endforeach
                        @foreach ($invoice->fines as $fine)
                            <tr><td>{{ $fine->rule?->name ?? 'Late fine' }} <span class="muted small">({{ $fine->applied_on?->format('d M') }})</span></td><td class="num">{{ $money($fine->amount) }}</td></tr>
                        @endforeach
                        @if ($invoice->paid_total > 0 || $invoice->waived_total > 0)
                            <tr><td class="muted">Already paid / waived</td><td class="num muted">−{{ $money($invoice->paid_total + $invoice->waived_total) }}</td></tr>
                        @endif
                        <tr class="total"><td>{{ $invoice->due_date?->isPast() && ! $invoice->due_date->isToday() ? 'Payable now' : 'Payable by '.$invoice->due_date?->format('d M') }}</td><td class="num">{{ $money($invoice->balance) }}</td></tr>
                    </table>

                    @if ($invoice->status === 'paid')
                        <p class="status-paid">PAID — thank you.</p>
                    @elseif ($invoice->fines->isNotEmpty())
                        <div class="late">Includes a late fine of Rs {{ $money($invoice->fine_total) }}{{ $lateRules->contains('calculation', 'per_day') ? '; it grows each day until paid' : '' }}.</div>
                    @elseif ($lateRules->isNotEmpty())
                        <div class="late">
                            @if ($fixedLate > 0 && $lateRules->count() === 1)
                                After {{ $invoice->due_date?->copy()->addDays($lateRules->first()->grace_days)->format('d M Y') }}: <strong>Rs {{ $money($invoice->balance + $fixedLate) }}</strong> (late fine Rs {{ $money($fixedLate) }}).
                            @else
                                A late fine is added after the due date:
                                @foreach ($lateRules as $rule)
                                    {{ $rule->name }} — {{ $rule->calculation === 'percent' ? ($rule->amount / 100).'%' : 'Rs '.$money($rule->amount).($rule->calculation === 'per_day' ? ' a day' : '') }}{{ $rule->grace_days ? ' after '.$rule->grace_days.' day(s)' : '' }}{{ $loop->last ? '.' : ';' }}
                                @endforeach
                            @endif
                        </div>
                    @endif

                    <table class="stamp"><tr><td>Depositor</td><td>Bank stamp &amp; signature</td></tr></table>
                </td>
            @endforeach
        </tr></table>
    </div>
@endforeach
</body>
</html>
