{{-- Fee receipt: one A5 portrait page. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 9mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.6pt; color: #1f2937; }
        .head { text-align: center; border-bottom: 1.2pt solid #1d2b45; padding-bottom: 6pt; }
        .logo { height: 14mm; margin-bottom: 3pt; }
        h1 { margin: 0; font-size: 13pt; color: #1d2b45; }
        .muted { color: #6b7280; }
        .small { font-size: 7.4pt; }
        .title { margin: 8pt 0 2pt; text-align: center; font-size: 10.5pt; letter-spacing: 1.5pt; text-transform: uppercase; color: #1d2b45; }
        table { width: 100%; border-collapse: collapse; }
        table.info td { padding: 2.2pt 0; vertical-align: top; }
        table.info td.label { width: 32%; color: #6b7280; }
        table.lines { margin-top: 8pt; }
        table.lines th { background: #1d2b45; color: #fff; text-align: left; padding: 3pt 4pt; font-size: 7.8pt; }
        table.lines td { padding: 3pt 4pt; border-bottom: 0.4pt solid #e5e7eb; }
        .num { text-align: right; }
        tr.total td { font-weight: bold; border-top: 1pt solid #1d2b45; border-bottom: 0; background: #f8f5ec; font-size: 9.5pt; }
        .reversed { margin-top: 8pt; padding: 4pt 6pt; border: 1pt solid #b91c1c; color: #b91c1c; font-weight: bold; text-align: center; }
        .note { margin-top: 8pt; }
        table.signs { margin-top: 26pt; }
        table.signs td { width: 50%; padding-top: 5pt; border-top: 0.5pt solid #9ca3af; text-align: center; color: #6b7280; font-size: 7.4pt; }
        table.signs td.gap { border-top: 0; width: 20%; }
    </style>
</head>
<body>
@php
    $money = fn (int $paisa): string => 'Rs '.number_format($paisa / 100);
    $enrollment = $payment->student?->currentEnrollment;
    $class = trim(($enrollment?->schoolClass?->name ?? '').' '.($enrollment?->section?->name ?? ''));
@endphp
<div class="head">
    @if ($logo)<img class="logo" src="{{ $logo }}" alt="">@endif
    <h1>{{ $settings?->school_name }}</h1>
    <div class="muted small">{{ $settings?->school_address }}{{ $settings?->school_phone ? ' · '.$settings->school_phone : '' }}</div>
</div>

<div class="title">Fee Receipt</div>

<table class="info">
    <tr><td class="label">Receipt no</td><td><strong>{{ $payment->receipt_no }}</strong></td></tr>
    <tr><td class="label">Date</td><td>{{ $payment->received_on?->format('d M Y') }}</td></tr>
    <tr><td class="label">Received from</td><td>{{ $payment->student?->fullName() ?? $payment->application?->fullName() }}</td></tr>
    <tr><td class="label">ID</td><td>{{ $payment->student?->admission_no ?? $payment->application?->application_no }}</td></tr>
    @if ($class !== '')<tr><td class="label">Class</td><td>{{ $class }}</td></tr>@endif
    @if ($payment->paidBy)<tr><td class="label">Paid by</td><td>{{ $payment->paidBy->name }}</td></tr>@endif
    <tr><td class="label">Paid through</td><td>{{ $payment->paymentMethod?->name }}{{ $payment->reference_no ? ' · '.$payment->reference_no : '' }}</td></tr>
</table>

<table class="lines">
    <tr><th>For</th><th>Fee month</th><th class="num">Amount</th></tr>
    @foreach ($payment->allocations->where('from_advance', false) as $allocation)
        <tr>
            <td>Challan {{ $allocation->invoice?->invoice_no }}</td>
            <td>{{ $allocation->invoice?->billing_month?->format('M Y') ?? ucfirst((string) $allocation->invoice?->invoice_type) }}</td>
            <td class="num">{{ $money($allocation->amount) }}</td>
        </tr>
    @endforeach
    @if ($payment->advance_amount > 0)
        <tr><td colspan="2">Advance (kept for the next challans)</td><td class="num">{{ $money($payment->advance_amount) }}</td></tr>
    @endif
    <tr class="total"><td colspan="2">Total received</td><td class="num">{{ $money($payment->amount) }}</td></tr>
</table>

@if ($payment->status === 'reversed')
    <div class="reversed">REVERSED{{ $payment->reversal_reason ? ' — '.$payment->reversal_reason : '' }}. This receipt is no longer valid.</div>
@endif

@if ($payment->remarks)
    <p class="note muted">{{ $payment->remarks }}</p>
@endif

<table class="signs"><tr>
    <td>Received by{{ $payment->receivedBy ? ': '.$payment->receivedBy->name : '' }}</td>
    <td class="gap"></td>
    <td>Stamp</td>
</tr></table>
<p class="muted small" style="margin-top: 10pt; text-align: center;">This is a computer-generated receipt and is valid without a signature.</p>
</body>
</html>
