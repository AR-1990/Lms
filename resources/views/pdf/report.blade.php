{{-- Any reports-centre report: summary figures, then the table with its totals row. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 12mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #1f2937; }
        .head { border-bottom: 2.5pt solid #1d2b45; padding-bottom: 6pt; margin-bottom: 8pt; }
        .head h1 { margin: 0; font-size: 15pt; color: #1d2b45; }
        .head p { margin: 2pt 0 0; color: #6b7280; }
        .summary { margin-bottom: 8pt; }
        .summary td { padding: 5pt 8pt; border: 0.5pt solid #e5e7eb; }
        .summary span { display: block; color: #6b7280; font-size: 7.5pt; }
        .summary strong { font-size: 11pt; color: #1d2b45; }
        table { width: 100%; border-collapse: collapse; }
        .rows th { background: #1d2b45; color: #fff; text-align: left; padding: 4pt 5pt; font-size: 7.5pt; }
        .rows td { padding: 3.5pt 5pt; border-bottom: 0.5pt solid #e5e7eb; }
        .rows .num { text-align: right; }
        .rows tr.total td { font-weight: bold; border-top: 1pt solid #1d2b45; background: #f8f5ec; }
        .empty { color: #9ca3af; padding: 10pt 0; }
        .foot { margin-top: 12pt; color: #9ca3af; font-size: 7pt; }
    </style>
</head>
<body>
@php
    $cell = function ($value, string $type) {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($type) {
            'money' => number_format($value / 100),
            'percent' => $value.'%',
            'number' => is_numeric($value) ? rtrim(rtrim(number_format((float) $value, 1), '0'), '.') : $value,
            'date' => \Illuminate\Support\Carbon::parse($value)->format('d M Y'),
            default => $value,
        };
    };
@endphp
    <div class="head">
        <h1>{{ $report['title'] }}</h1>
        <p>{{ $schoolName }} · {{ $report['period'] }} · printed {{ now()->format('d M Y H:i') }} · amounts in Rs</p>
    </div>

    @if ($report['summary'] !== [])
        <table class="summary"><tr>
            @foreach ($report['summary'] as [$label, $value, $type])
                <td><span>{{ $label }}</span><strong>{{ $cell($value, $type) }}</strong></td>
            @endforeach
        </tr></table>
    @endif

    @if ($report['rows'] === [])
        <p class="empty">Nothing to show for this period.</p>
    @else
        <table class="rows">
            <thead><tr>@foreach ($report['columns'] as $column)<th class="{{ in_array($column['type'], ['money', 'number', 'percent'], true) ? 'num' : '' }}">{{ $column['label'] }}</th>@endforeach</tr></thead>
            <tbody>
                @foreach ($report['rows'] as $row)
                    <tr>@foreach ($report['columns'] as $column)<td class="{{ in_array($column['type'], ['money', 'number', 'percent'], true) ? 'num' : '' }}">{{ $cell($row[$column['key']] ?? null, $column['type']) }}</td>@endforeach</tr>
                @endforeach
                @if ($report['totals'])
                    <tr class="total">@foreach ($report['columns'] as $column)<td class="{{ in_array($column['type'], ['money', 'number', 'percent'], true) ? 'num' : '' }}">{{ $cell($report['totals'][$column['key']] ?? null, $column['type']) }}</td>@endforeach</tr>
                @endif
            </tbody>
        </table>
        @if (isset($report['meta']) || count($report['rows']) >= 1000)
            <p class="foot">Long lists print their first 1,000 rows; download the CSV for every row.</p>
        @endif
    @endif
</body>
</html>
