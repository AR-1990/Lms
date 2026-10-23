{{-- One staff appraisal: scores per criterion (self and reviewer), the reviewer's summary and the employee's comments. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 14mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; color: #1f2937; }
        .head { border-bottom: 2.5pt solid #1d2b45; padding-bottom: 6pt; margin-bottom: 10pt; }
        .head h1 { margin: 0; font-size: 16pt; color: #1d2b45; }
        .head p { margin: 2pt 0 0; color: #6b7280; }
        table { width: 100%; border-collapse: collapse; }
        .info td { padding: 2.5pt 0; }
        .info td.label { width: 18%; color: #6b7280; }
        .scores { margin-top: 12pt; }
        .scores th, .scores td { padding: 4pt 7pt; border-bottom: 0.5pt solid #e5e7eb; text-align: left; vertical-align: top; }
        .scores th { background: #1d2b45; color: #fff; font-size: 8.5pt; }
        .scores td.num { text-align: center; width: 12%; }
        .scores small { color: #6b7280; }
        .total td { font-weight: bold; border-top: 1pt solid #1d2b45; }
        h2 { margin: 14pt 0 4pt; font-size: 10.5pt; color: #1d2b45; }
        .text { white-space: pre-line; margin: 0; }
        .sign { margin-top: 36pt; }
        .sign td { width: 33%; padding-top: 18pt; border-top: 0.6pt solid #9ca3af; text-align: center; color: #6b7280; }
        .sign td + td { border-left: 12pt solid #fff; }
    </style>
</head>
<body>
    <div class="head">
        <h1>Staff Appraisal — {{ $appraisal['cycle'] }}</h1>
        <p>{{ $schoolName }} · Printed {{ now()->format('d M Y') }}</p>
    </div>

    <table class="info">
        <tr><td class="label">Employee</td><td>{{ $appraisal['employee']['name'] }} ({{ $appraisal['employee']['code'] }})</td><td class="label">Reviewer</td><td>{{ $appraisal['reviewer'] ?? '—' }}</td></tr>
        <tr><td class="label">Department</td><td>{{ $appraisal['employee']['department'] ?? '—' }}</td><td class="label">Designation</td><td>{{ $appraisal['employee']['designation'] ?? '—' }}</td></tr>
        <tr><td class="label">Overall score</td><td>{{ $appraisal['overall_score'] !== null ? number_format($appraisal['overall_score'], 2).' / 5' : '—' }}</td><td class="label">Rating</td><td>{{ $appraisal['rating'] ?? '—' }}</td></tr>
    </table>

    <table class="scores">
        <thead><tr><th>Criterion</th><th>Weight</th><th>Self</th><th>Reviewer</th></tr></thead>
        <tbody>
            @foreach ($appraisal['scores'] as $score)
                <tr>
                    <td>{{ $score['criterion'] }}
                        @if ($score['comment'])<br><small>Reviewer: {{ $score['comment'] }}</small>@endif
                        @if ($score['self_comment'])<br><small>Self: {{ $score['self_comment'] }}</small>@endif
                    </td>
                    <td class="num">{{ $score['weight'] + 0 }}%</td>
                    <td class="num">{{ $score['self_score'] !== null ? $score['self_score'] + 0 : '—' }}</td>
                    <td class="num">{{ $score['score'] !== null ? $score['score'] + 0 : '—' }}</td>
                </tr>
            @endforeach
            <tr class="total"><td>Weighted score</td><td class="num"></td><td class="num">{{ $appraisal['self_score'] !== null ? number_format($appraisal['self_score'], 2) : '—' }}</td><td class="num">{{ $appraisal['overall_score'] !== null ? number_format($appraisal['overall_score'], 2) : '—' }}</td></tr>
        </tbody>
    </table>

    @foreach (['strengths' => 'Strengths', 'improvements' => 'Areas to improve', 'goals' => 'Goals for next period', 'employee_comments' => 'Employee comments'] as $field => $title)
        @if ($appraisal[$field])
            <h2>{{ $title }}</h2>
            <p class="text">{{ $appraisal[$field] }}</p>
        @endif
    @endforeach

    <table class="sign">
        <tr><td>Employee{{ $appraisal['acknowledged_at'] ? ' · acknowledged '.$appraisal['acknowledged_at'] : '' }}</td><td>Reviewer{{ $appraisal['submitted_at'] ? ' · submitted '.$appraisal['submitted_at'] : '' }}</td><td>Principal / HR</td></tr>
    </table>
</body>
</html>
