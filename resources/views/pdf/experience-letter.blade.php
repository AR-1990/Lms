{{-- Experience letter for a member of staff who has left: one A4 page. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 18mm 20mm; }
        body { font-family: DejaVu Serif, serif; font-size: 11pt; color: #1f2937; line-height: 1.6; }
        .head { text-align: center; border-bottom: 1.4pt solid #1d2b45; padding-bottom: 8pt; }
        .logo { height: 18mm; margin-bottom: 4pt; }
        .school { margin: 0; font-size: 18pt; color: #1d2b45; }
        .muted { color: #6b7280; font-size: 9pt; }
        .meta { margin-top: 16pt; width: 100%; font-size: 10pt; }
        .meta td.right { text-align: right; }
        h2 { margin: 22pt 0 12pt; text-align: center; font-size: 13pt; letter-spacing: 2pt; text-transform: uppercase; color: #1d2b45; }
        p { margin: 0 0 10pt; text-align: justify; }
        .details { margin: 12pt 0; width: 100%; border-collapse: collapse; font-size: 10.5pt; }
        .details td { padding: 4pt 2pt; border-bottom: 0.4pt dotted #9ca3af; }
        .details td.label { width: 38%; color: #4b5563; }
        .sign { margin-top: 48pt; width: 60mm; border-top: 0.6pt solid #6b7280; padding-top: 4pt; text-align: center; color: #4b5563; font-size: 9.5pt; }
    </style>
</head>
<body>
@php
    $employee = $exit->employee;
    $date = fn ($value): string => $value ? $value->format('d F Y') : '—';
@endphp
<div class="head">
    @if ($logo)<img class="logo" src="{{ $logo }}" alt="">@endif
    <h1 class="school">{{ $settings?->school_name }}</h1>
    <div class="muted">{{ $settings?->school_address }}{{ $settings?->school_phone ? ' · '.$settings->school_phone : '' }}</div>
</div>

<table class="meta"><tr>
    <td>Ref: {{ $employee->employee_code }}/EXP</td>
    <td class="right">Date: {{ $date($exit->settlement_paid_on) }}</td>
</tr></table>

<h2>Experience Certificate</h2>

<p>To whom it may concern,</p>
<p>This is to certify that <strong>{{ $employee->fullName() }}</strong> worked with {{ $settings?->school_name }} as <strong>{{ $employee->designation?->name ?? 'a member of staff' }}</strong>{{ $employee->department ? ' in the '.$employee->department->name.' department' : '' }} from <strong>{{ $date($employee->joining_date) }}</strong> to <strong>{{ $date($exit->last_working_day) }}</strong>.</p>

<table class="details">
    <tr><td class="label">Employee code</td><td>{{ $employee->employee_code }}</td></tr>
    <tr><td class="label">Designation</td><td>{{ $employee->designation?->name ?? '—' }}</td></tr>
    @if ($employee->department)<tr><td class="label">Department</td><td>{{ $employee->department->name }}</td></tr>@endif
    <tr><td class="label">Period of service</td><td>{{ $date($employee->joining_date) }} to {{ $date($exit->last_working_day) }}</td></tr>
</table>

<p>All dues between the school and {{ $employee->fullName() }} have been settled. We wish them every success in the future.</p>

<div class="sign">Principal<br>(with school seal)</div>
</body>
</html>
