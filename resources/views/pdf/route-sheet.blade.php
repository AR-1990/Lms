{{-- Route sheet for the driver and attendant: stops in order with pickup / drop times and the students at each. --}}
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
        .stop { margin-top: 12pt; page-break-inside: avoid; }
        .stop h2 { margin: 0 0 4pt; padding: 4pt 7pt; background: #1d2b45; color: #fff; font-size: 10pt; }
        .stop h2 span { float: right; font-weight: normal; }
        .stop td, .stop th { padding: 4pt 7pt; border-bottom: 0.5pt solid #e5e7eb; text-align: left; }
        .stop th { color: #6b7280; font-size: 8.5pt; }
        .stop td.tick { width: 14pt; }
        .box { display: inline-block; width: 9pt; height: 9pt; border: 0.8pt solid #1d2b45; }
        .empty { color: #9ca3af; padding: 4pt 7pt; }
        .foot { margin-top: 24pt; color: #9ca3af; font-size: 7.5pt; text-align: center; }
    </style>
</head>
<body>
    <div class="head">
        <h1>{{ $route['name'] }}{{ $route['code'] ? ' ('.$route['code'].')' : '' }}</h1>
        <p>{{ $schoolName }} · Route sheet printed {{ now()->format('d M Y') }}</p>
    </div>

    <table class="info">
        <tr><td class="label">Vehicle</td><td>{{ $route['vehicle_label'] ?? '—' }}{{ $route['capacity'] ? ' · '.$route['capacity'].' seats' : '' }}</td><td class="label">Riders</td><td>{{ $route['riders'] }}</td></tr>
        <tr><td class="label">Driver</td><td>{{ $route['driver_name'] ?? '—' }}</td><td class="label">Attendant</td><td>{{ $route['attendant_name'] ?? '—' }}</td></tr>
    </table>

    @foreach ($route['stops'] as $stop)
        <div class="stop">
            <h2>{{ $loop->iteration }}. {{ $stop['name'] }}{{ $stop['area_name'] ? ' · '.$stop['area_name'] : '' }}<span>Pickup {{ $stop['pickup_time'] ?? '—' }} · Drop {{ $stop['drop_time'] ?? '—' }}</span></h2>
            @if ($stop['students'] === [])
                <p class="empty">No students at this stop.</p>
            @else
                <table>
                    <thead><tr><th></th><th>Student</th><th>Admission no</th><th>Class</th><th>Parent contact</th></tr></thead>
                    <tbody>
                        @foreach ($stop['students'] as $student)
                            <tr><td class="tick"><span class="box"></span></td><td>{{ $student['name'] }}</td><td>{{ $student['admission_no'] }}</td><td>{{ $student['class'] ?: '—' }}</td><td>{{ $student['contact'] ?? '—' }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach

    <p class="foot">Tick each student on boarding. Report absent or late students to the transport office.</p>
</body>
</html>
