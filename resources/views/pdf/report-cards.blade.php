{{-- A4 report cards, one page per student. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 14mm 14mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; color: #1f2937; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .head { background: #1d2b45; color: #ffffff; padding: 5mm 6mm; text-align: center; }
        .head h1 { margin: 0; font-size: 15pt; }
        .head p { margin: 1.5mm 0 0; color: #fde68a; font-size: 9pt; letter-spacing: 0.6pt; text-transform: uppercase; }
        .accent { height: 1.2mm; background: #c9a227; margin-bottom: 5mm; }
        table { width: 100%; border-collapse: collapse; }
        .info td { padding: 1.5mm 2mm; }
        .info .label { color: #6b7280; width: 22%; }
        .marks { margin-top: 5mm; }
        .marks th { background: #f3f4f6; border: 0.3mm solid #d1d5db; padding: 2mm; text-align: left; }
        .marks td { border: 0.3mm solid #e5e7eb; padding: 2mm; }
        .num { text-align: right; }
        .summary { margin-top: 5mm; }
        .summary td { width: 25%; padding: 3mm; border: 0.3mm solid #e5e7eb; text-align: center; }
        .summary strong { display: block; font-size: 13pt; color: #1d2b45; }
        .pass { color: #15803d; }
        .fail { color: #b91c1c; }
        .remarks { margin-top: 6mm; min-height: 18mm; border: 0.3mm dashed #d1d5db; padding: 3mm; }
        .signs { margin-top: 18mm; }
        .signs td { width: 33%; text-align: center; padding-top: 2mm; border-top: 0.3mm solid #374151; }
        .signs td.gap { border: none; width: 3%; }
    </style>
</head>
<body>
@foreach ($cards as $card)
    @php($studentMarks = ($marks[$card->enrollment_id] ?? collect())->keyBy('exam_subject_id'))
    <div class="page">
        <div class="head">
            <h1>{{ $schoolName }}</h1>
            <p>Progress Report — {{ $exam->name }}</p>
        </div>
        <div class="accent"></div>

        <table class="info">
            <tr><td class="label">Student</td><td><strong>{{ $card->student?->fullName() }}</strong></td><td class="label">Admission no</td><td>{{ $card->student?->admission_no }}</td></tr>
            <tr><td class="label">Class</td><td>{{ $exam->schoolClass?->name }} {{ $card->enrollment?->section?->name }}</td><td class="label">Roll no</td><td>{{ $card->enrollment?->roll_no ?? '—' }}</td></tr>
            <tr><td class="label">Term</td><td>{{ $exam->term?->name }}</td><td class="label">Attendance</td><td>{{ $card->attendance_percent !== null ? $card->attendance_percent.'%' : '—' }}</td></tr>
        </table>

        <table class="marks">
            <tr><th>Subject</th><th class="num">Max</th><th class="num">Obtained</th><th>Grade</th></tr>
            @foreach ($exam->subjects as $paper)
                @php($mark = $studentMarks->get($paper->id))
                <tr>
                    <td>{{ $paper->subject?->name }}</td>
                    <td class="num">{{ $paper->maxMarks() }}</td>
                    <td class="num">{{ $mark?->is_absent ? 'Absent' : rtrim(rtrim(number_format($mark?->obtained() ?? 0, 2), '0'), '.') }}</td>
                    <td>{{ $mark?->grade ?? '—' }}</td>
                </tr>
            @endforeach
            <tr><th>Total</th><th class="num">{{ rtrim(rtrim(number_format($card->total_max, 2), '0'), '.') }}</th><th class="num">{{ rtrim(rtrim(number_format($card->total_obtained, 2), '0'), '.') }}</th><th>{{ $card->grade }}</th></tr>
        </table>

        <table class="summary">
            <tr>
                <td>Percentage<strong>{{ $card->percentage }}%</strong></td>
                <td>Grade<strong>{{ $card->grade ?? '—' }}</strong></td>
                <td>Position in section<strong>{{ $card->position_in_section ?? '—' }}</strong></td>
                <td>Result<strong class="{{ $card->result }}">{{ strtoupper($card->result) }}</strong></td>
            </tr>
        </table>

        <div class="remarks"><strong>Class teacher's remarks:</strong> {{ $card->class_teacher_remarks }}</div>

        <table class="signs">
            <tr><td>Class Teacher</td><td class="gap"></td><td>Controller of Examinations</td><td class="gap"></td><td>Principal</td></tr>
        </table>
    </div>
@endforeach
</body>
</html>
