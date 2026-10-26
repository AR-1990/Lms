{{-- A4 result card (marksheet), one page per student: school header with logo and photo, every paper with theory /
practical, total, grade, class highest and pass / fail, the summary (percentage, grade, positions, attendance), the
grading key, remarks and signatures. Built by ResultService::reportCardsPdf. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 9mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.6pt; color: #1f2937; margin: 0; }
        .page { page-break-after: always; border: 0.8mm solid #1d2b45; padding: 1.2mm; height: 275mm; }
        .page:last-child { page-break-after: auto; }
        .inner { border: 0.3mm solid #c9a227; padding: 5mm 6mm 4mm; height: 263mm; position: relative; }
        table { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: middle; }
        .logo { width: 22mm; height: 22mm; }
        .logo img { max-width: 22mm; max-height: 22mm; }
        .school { text-align: center; }
        .school h1 { margin: 0; font-size: 17pt; color: #1d2b45; letter-spacing: 0.3pt; }
        .school p { margin: 1mm 0 0; font-size: 7.8pt; color: #4b5563; }
        .photo { width: 22mm; text-align: right; }
        .photo div { width: 20mm; height: 24mm; border: 0.4mm solid #c9a227; background: #f3f4f6; display: inline-block; overflow: hidden; }
        .photo img { width: 20mm; height: 24mm; }
        .title { margin: 3.5mm 0 3mm; background: #1d2b45; color: #ffffff; text-align: center; padding: 2.2mm; font-size: 11pt; font-weight: bold; letter-spacing: 1.2pt; }
        .title span { color: #fde68a; font-weight: normal; letter-spacing: 0.4pt; font-size: 9pt; }
        .info td { padding: 1.3mm 1.5mm; border-bottom: 0.2mm dotted #d1d5db; }
        .info .label { color: #6b7280; width: 17%; font-size: 7.8pt; text-transform: uppercase; letter-spacing: 0.3pt; }
        .info .value { width: 33%; font-weight: bold; }
        .marks { margin-top: 3.5mm; }
        .marks th { background: #1d2b45; color: #ffffff; border: 0.2mm solid #1d2b45; padding: 1.8mm 1.2mm; font-size: 7.6pt; text-align: center; }
        .marks th.left, .marks td.left { text-align: left; }
        .marks td { border: 0.2mm solid #d1d5db; padding: 1.7mm 1.2mm; text-align: center; }
        .marks tr.alt td { background: #f9fafb; }
        .marks tr.total td { background: #fef9e7; font-weight: bold; border-top: 0.4mm solid #c9a227; }
        .fail { color: #b91c1c; font-weight: bold; }
        .pass { color: #15803d; font-weight: bold; }
        .muted { color: #6b7280; }
        .summary { margin-top: 3.5mm; }
        .summary td { width: 16.66%; border: 0.3mm solid #e5e7eb; text-align: center; padding: 2mm 1mm; font-size: 7.4pt; color: #6b7280; text-transform: uppercase; letter-spacing: 0.2pt; }
        .summary strong { display: block; margin-top: 0.8mm; font-size: 12.5pt; color: #1d2b45; text-transform: none; letter-spacing: 0; }
        .summary .result-pass strong { color: #15803d; }
        .summary .result-fail strong { color: #b91c1c; }
        .lower { margin-top: 3.5mm; }
        .lower > tbody > tr > td { vertical-align: top; }
        .box { border: 0.3mm solid #e5e7eb; padding: 2.2mm 2.5mm; min-height: 15mm; }
        .box h3 { margin: 0 0 1.2mm; font-size: 8pt; color: #1d2b45; text-transform: uppercase; letter-spacing: 0.4pt; }
        .key td { border: 0.2mm solid #e5e7eb; padding: 0.9mm 1mm; text-align: center; font-size: 7.3pt; }
        .key th { background: #f3f4f6; border: 0.2mm solid #e5e7eb; padding: 0.9mm 1mm; font-size: 7.3pt; }
        .bottom { position: absolute; left: 6mm; right: 6mm; bottom: 4mm; }
        .signs td { width: 28%; text-align: center; padding-top: 1.5mm; border-top: 0.3mm solid #374151; font-size: 8pt; }
        .signs td.gap { border: none; width: 8%; }
        .foot { margin-top: 3mm; font-size: 6.8pt; color: #9ca3af; }
        .foot td.right { text-align: right; }
    </style>
</head>
<body>
@php($fmt = fn ($value): string => $value === null ? '—' : rtrim(rtrim(number_format((float) $value, 2), '0'), '.'))
@foreach ($cards as $card)
    @php($studentMarks = ($marks[$card->enrollment_id] ?? collect())->keyBy('exam_subject_id'))
    @php($guardian = $card->student?->guardians->firstWhere('pivot.is_primary_contact', true) ?? $card->student?->guardians->first())
    <div class="page"><div class="inner">
        <table class="head">
            <tr>
                <td class="logo">@if ($logo)<img src="{{ $logo }}" alt="">@endif</td>
                <td class="school">
                    <h1>{{ $school['name'] }}</h1>
                    @if ($school['address'])<p>{{ $school['address'] }}</p>@endif
                    @if ($school['phone'] || $school['email'])<p>{{ collect([$school['phone'] ? 'Phone '.$school['phone'] : null, $school['email']])->filter()->implode(' · ') }}</p>@endif
                </td>
                <td class="photo"><div>@if ($photos[$card->id] ?? null)<img src="{{ $photos[$card->id] }}" alt="">@endif</div></td>
            </tr>
        </table>

        <div class="title">RESULT CARD<br><span>{{ $exam->name }}{{ $exam->examType ? ' · '.$exam->examType->name : '' }} · {{ $exam->term?->name }}{{ $session ? ' · Session '.$session : '' }}</span></div>

        <table class="info">
            <tr><td class="label">Student</td><td class="value">{{ $card->student?->fullName() }}</td><td class="label">Admission no</td><td class="value">{{ $card->student?->admission_no }}</td></tr>
            <tr><td class="label">Father / guardian</td><td class="value">{{ $guardian?->name ?? '—' }}</td><td class="label">Class &amp; section</td><td class="value">{{ $exam->schoolClass?->name }} {{ $card->enrollment?->section?->name }}</td></tr>
            <tr><td class="label">Roll no</td><td class="value">{{ $card->enrollment?->roll_no ?? '—' }}</td><td class="label">Exam roll no</td><td class="value">{{ $rollNumbers[$card->enrollment_id] ?? '—' }}</td></tr>
            <tr><td class="label">Date of birth</td><td class="value">{{ $card->student?->date_of_birth?->format('d M Y') ?? '—' }}</td><td class="label">Attendance</td><td class="value">{{ $card->attendance_percent !== null ? $fmt($card->attendance_percent).'%' : '—' }}</td></tr>
        </table>

        <table class="marks">
            <tr>
                <th style="width: 5%">#</th>
                <th class="left">Subject</th>
                <th style="width: 9%">Max marks</th>
                <th style="width: 9%">Pass marks</th>
                <th style="width: 9%">Theory</th>
                <th style="width: 9%">Practical</th>
                <th style="width: 9%">Obtained</th>
                <th style="width: 7%">%</th>
                <th style="width: 7%">Grade</th>
                <th style="width: 8%">Highest</th>
                <th style="width: 8%">Status</th>
            </tr>
            @foreach ($exam->subjects as $index => $paper)
                @php($mark = $studentMarks->get($paper->id))
                @php($obtained = $mark && ! $mark->is_absent ? $mark->obtained() : null)
                @php($failed = $mark === null || $mark->is_absent || ($paper->pass_marks !== null && $obtained < $paper->pass_marks))
                <tr class="{{ $index % 2 ? 'alt' : '' }}">
                    <td>{{ $index + 1 }}</td>
                    <td class="left">{{ $paper->subject?->name }}</td>
                    <td>{{ $paper->maxMarks() }}</td>
                    <td class="muted">{{ $paper->pass_marks ?? '—' }}</td>
                    <td>{{ $mark?->is_absent ? '—' : $fmt($mark?->theory_marks) }}</td>
                    <td>{{ $paper->practical_max_marks ? ($mark?->is_absent ? '—' : $fmt($mark?->practical_marks)) : '—' }}</td>
                    <td><strong>{{ $mark?->is_absent ? 'Absent' : $fmt($obtained) }}</strong></td>
                    <td>{{ $obtained !== null && $paper->maxMarks() ? $fmt(round($obtained * 100 / $paper->maxMarks(), 1)) : '—' }}</td>
                    <td><strong>{{ $mark?->grade ?? '—' }}</strong></td>
                    <td class="muted">{{ $fmt($highest[$paper->id] ?? null) }}</td>
                    <td class="{{ $failed ? 'fail' : 'pass' }}">{{ $failed ? 'Fail' : 'Pass' }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td></td>
                <td class="left">Grand total</td>
                <td>{{ $fmt($card->total_max) }}</td>
                <td></td><td></td><td></td>
                <td>{{ $fmt($card->total_obtained) }}</td>
                <td>{{ $fmt($card->percentage) }}</td>
                <td>{{ $card->grade ?? '—' }}</td>
                <td></td>
                <td class="{{ $card->result === 'pass' ? 'pass' : 'fail' }}">{{ ucfirst((string) $card->result) }}</td>
            </tr>
        </table>

        <table class="summary">
            <tr>
                <td>Marks<strong>{{ $fmt($card->total_obtained) }} / {{ $fmt($card->total_max) }}</strong></td>
                <td>Percentage<strong>{{ $fmt($card->percentage) }}%</strong></td>
                <td>Grade<strong>{{ $card->grade ?? '—' }}</strong></td>
                <td>Position in section<strong>{{ $card->position_in_section ? $card->position_in_section.' / '.($sectionSizes[$card->section_id] ?? '—') : '—' }}</strong></td>
                <td>Position in class<strong>{{ $card->position_in_class ? $card->position_in_class.' / '.$classSize : '—' }}</strong></td>
                <td class="result-{{ $card->result === 'pass' ? 'pass' : 'fail' }}">Result<strong>{{ $card->result === 'pass' ? 'PASS' : 'FAIL' }}</strong></td>
            </tr>
        </table>

        <table class="lower">
            <tr>
                <td style="width: 62%; padding-right: 2mm;">
                    <div class="box"><h3>Class teacher's remarks</h3>{{ $card->class_teacher_remarks ?: '' }}</div>
                    <div class="box" style="margin-top: 2mm;"><h3>Principal's remarks</h3>{{ $card->principal_remarks ?: '' }}</div>
                </td>
                <td style="width: 38%;">
                    <div class="box">
                        <h3>Grading key</h3>
                        @if ($exam->gradingScale && $exam->gradingScale->items->isNotEmpty())
                            <table class="key">
                                <tr><th>Grade</th><th>Marks %</th><th>Remark</th></tr>
                                @foreach ($exam->gradingScale->items->sortByDesc('min_percent') as $item)
                                    <tr><td><strong>{{ $item->grade }}</strong></td><td>{{ $fmt($item->min_percent) }}–{{ $fmt($item->max_percent) }}</td><td>{{ $item->remark }}</td></tr>
                                @endforeach
                            </table>
                        @endif
                    </div>
                </td>
            </tr>
        </table>

        <div class="bottom">
        <table class="signs">
            <tr><td>Class Teacher</td><td class="gap"></td><td>Controller of Examinations</td><td class="gap"></td><td>Principal</td></tr>
        </table>

        <table class="foot">
            <tr><td>Issued on {{ $issuedOn }} · This result card is computer generated.</td><td class="right">{{ $school['name'] }}</td></tr>
        </table>
        </div>
    </div></div>
@endforeach
</body>
</html>
