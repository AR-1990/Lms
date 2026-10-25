{{-- Bonafide or character certificate: one A4 page. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 12mm; }
        body { font-family: DejaVu Serif, serif; font-size: 11pt; color: #1f2937; line-height: 1.7; }
        .frame { border: 2.5pt double #1d2b45; padding: 14mm; min-height: 245mm; }
        .head { text-align: center; }
        .logo { height: 20mm; margin-bottom: 4pt; }
        .school { margin: 0; font-size: 19pt; color: #1d2b45; }
        .muted { color: #6b7280; font-size: 9pt; }
        .title { margin: 16pt auto 0; padding: 5pt 16pt; display: inline-block; border-top: 1pt solid #c9a24a; border-bottom: 1pt solid #c9a24a; font-size: 14pt; letter-spacing: 2pt; text-transform: uppercase; color: #1d2b45; }
        .meta { margin-top: 16pt; width: 100%; font-size: 10pt; }
        .meta td.right { text-align: right; }
        p { margin: 18pt 0 0; text-align: justify; }
        .sign { margin-top: 60pt; width: 100%; }
        .sign td { width: 33%; padding-top: 6pt; border-top: 0.6pt solid #6b7280; text-align: center; font-size: 9.5pt; color: #4b5563; }
        .sign td.gap { border-top: 0; }
    </style>
</head>
<body>
@php
    $enrollment = $student->currentEnrollment;
    $class = trim(($enrollment?->schoolClass?->name ?? '').' '.($enrollment?->section?->name ?? ''));
    $guardian = $student->guardians->first();
    $relation = ['Male' => 'son', 'Female' => 'daughter'][$student->gender?->name ?? ''] ?? 'child';
@endphp
<div class="frame">
    <div class="head">
        @if ($logo)<img class="logo" src="{{ $logo }}" alt="">@endif
        <h1 class="school">{{ $settings?->school_name }}</h1>
        <div class="muted">{{ $settings?->school_address }}{{ $settings?->school_phone ? ' · '.$settings->school_phone : '' }}</div>
        <div class="title">{{ \App\Models\StudentCertificate::TYPES[$certificate->certificate_type] }}</div>
    </div>

    <table class="meta"><tr>
        <td>No: <strong>{{ $certificate->certificate_no }}</strong></td>
        <td class="right">Date: <strong>{{ $certificate->issued_on->format('d F Y') }}</strong></td>
    </tr></table>

    @if ($certificate->certificate_type === 'bonafide')
        <p>This is to certify that <strong>{{ $student->fullName() }}</strong>{{ $guardian ? ', '.$relation.' of '.$guardian->name.',' : '' }} admission number <strong>{{ $student->admission_no }}</strong>, is a bonafide student of this school{{ $class !== '' ? ', studying in '.$class : '' }}{{ $enrollment?->academicSession ? ' in the session '.$enrollment->academicSession->name : '' }}. {{ $student->date_of_birth ? 'According to our records, the date of birth is '.$student->date_of_birth->format('d F Y').'.' : '' }}</p>
    @else
        <p>This is to certify that <strong>{{ $student->fullName() }}</strong>{{ $guardian ? ', '.$relation.' of '.$guardian->name.',' : '' }} admission number <strong>{{ $student->admission_no }}</strong>{{ $class !== '' ? ', of '.$class : '' }}, has been a student of this school since {{ $student->admission_date?->format('d F Y') }}. During this time the conduct and character of the student have been <strong>{{ strtolower($certificate->conduct?->name ?? 'good') }}</strong>.</p>
    @endif

    @if ($certificate->purpose)
        <p>This certificate is issued on request for the purpose of {{ $certificate->purpose }}.</p>
    @endif

    @if ($certificate->remarks)
        <p>{{ $certificate->remarks }}</p>
    @endif

    <table class="sign"><tr>
        <td>Class Teacher</td>
        <td class="gap"></td>
        <td>Principal (with school seal)</td>
    </tr></table>
</div>
</body>
</html>
