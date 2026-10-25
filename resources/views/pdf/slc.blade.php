{{-- School Leaving Certificate: one A4 portrait page. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 12mm; }
        body { font-family: DejaVu Serif, serif; font-size: 10.5pt; color: #1f2937; }
        .frame { border: 2.5pt double #1d2b45; padding: 14mm 14mm 10mm; min-height: 245mm; }
        .head { text-align: center; }
        .school { margin: 0; font-size: 19pt; letter-spacing: 0.5pt; color: #1d2b45; }
        .tagline { margin: 2pt 0 0; font-size: 9pt; color: #6b7280; font-style: italic; }
        .address { margin: 3pt 0 0; font-size: 8.5pt; color: #6b7280; }
        .title { margin: 14pt auto 0; padding: 5pt 16pt; display: inline-block; border-top: 1pt solid #c9a24a; border-bottom: 1pt solid #c9a24a; font-size: 14pt; letter-spacing: 2pt; text-transform: uppercase; color: #1d2b45; }
        table.meta { width: 100%; margin-top: 14pt; font-size: 9.5pt; }
        table.meta td { padding: 0; }
        table.meta td.right { text-align: right; }
        table.details { width: 100%; margin-top: 12pt; border-collapse: collapse; }
        table.details td { padding: 6.5pt 4pt; border-bottom: 0.5pt dotted #9ca3af; vertical-align: top; }
        table.details td.label { width: 40%; color: #4b5563; }
        table.details td.value { font-weight: bold; }
        .statement { margin-top: 16pt; line-height: 1.6; text-align: justify; }
        table.signs { width: 100%; margin-top: 46pt; }
        table.signs td { width: 33%; padding-top: 6pt; border-top: 0.6pt solid #6b7280; text-align: center; font-size: 9pt; color: #4b5563; }
        table.signs td.gap { border-top: 0; }
        .foot { margin-top: 18pt; font-size: 7.5pt; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
@php
    $guardian = $student->guardians->first();
    $date = fn ($value): string => $value ? $value->format('d F Y') : '—';
    $statusText = ['graduated' => 'completed their studies at the school', 'withdrawn' => 'was withdrawn from the school', 'left' => 'left the school'][$student->current_status] ?? 'left the school';
@endphp
<div class="frame">
    <div class="head">
        <h1 class="school">{{ $settings?->school_name }}</h1>
        @if ($settings?->school_tagline)<p class="tagline">{{ $settings->school_tagline }}</p>@endif
        <p class="address">{{ $settings?->school_address }}{{ $settings?->school_phone ? ' · '.$settings->school_phone : '' }}</p>
        <div class="title">School Leaving Certificate</div>
    </div>

    <table class="meta"><tr>
        <td>Certificate no: <strong>{{ $student->slc_no }}</strong></td>
        <td class="right">Date of issue: <strong>{{ $date($student->slc_issued_on) }}</strong></td>
    </tr></table>

    <table class="details">
        <tr><td class="label">Name of student</td><td class="value">{{ $student->fullName() }}</td></tr>
        @if ($guardian)
            <tr><td class="label">Parent / guardian</td><td class="value">{{ $guardian->name }}</td></tr>
        @endif
        <tr><td class="label">Admission number</td><td class="value">{{ $student->admission_no }}</td></tr>
        <tr><td class="label">Date of birth</td><td class="value">{{ $date($student->date_of_birth) }}</td></tr>
        @if ($student->b_form_no)
            <tr><td class="label">B-Form number</td><td class="value">{{ $student->b_form_no }}</td></tr>
        @endif
        <tr><td class="label">Date of admission</td><td class="value">{{ $date($student->admission_date) }}{{ $firstEnrollment?->schoolClass ? ' — '.$firstEnrollment->schoolClass->name : '' }}</td></tr>
        <tr><td class="label">Class at leaving</td><td class="value">{{ $lastEnrollment?->schoolClass?->name ?? '—' }}{{ $lastEnrollment?->academicSession ? ' (session '.$lastEnrollment->academicSession->name.')' : '' }}</td></tr>
        <tr><td class="label">Date of leaving</td><td class="value">{{ $date($student->left_on) }}</td></tr>
        <tr><td class="label">Reason for leaving</td><td class="value">{{ $student->leavingReason?->name ?? ucfirst($student->current_status) }}</td></tr>
        <tr><td class="label">Conduct</td><td class="value">{{ $student->slcConduct?->name ?? '—' }}</td></tr>
        <tr><td class="label">School dues</td><td class="value">All dues cleared</td></tr>
        @if ($student->slc_remarks)
            <tr><td class="label">Remarks</td><td class="value">{{ $student->slc_remarks }}</td></tr>
        @endif
    </table>

    <p class="statement">
        Certified that {{ $student->fullName() }}, admission number {{ $student->admission_no }}, {{ $statusText }} on {{ $date($student->left_on) }}.
        The particulars above are correct according to the school's admission register.
    </p>

    <table class="signs"><tr>
        <td>Class Teacher</td>
        <td class="gap"></td>
        <td>Principal (with school seal)</td>
    </tr></table>

    <p class="foot">This certificate is valid only with the principal's signature and the school seal.</p>
</div>
</body>
</html>
