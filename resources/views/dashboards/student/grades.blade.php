@extends('layouts.dashboard')

@section('title', 'Results')
@section('page_heading', 'My Results')

@section('content')
<section class="dash-page-intro">
    <h1>My Results</h1>
    <p>Published exam results, newest first.</p>
</section>

@forelse ($data['results'] as $result)
    <section class="dash-panel">
        <div class="dash-panel-head">
            <div>
                <h2>{{ $result['exam'] }}</h2>
                <p class="dash-muted">{{ $result['term'] }}{{ $result['position_in_section'] ? ' · Position '.$result['position_in_section'] : '' }}{{ $result['attendance_percent'] !== null ? ' · Attendance '.$result['attendance_percent'].'%' : '' }}</p>
            </div>
            <span class="dash-badge {{ $result['result'] === 'pass' ? 'dash-badge-ok' : 'dash-badge-warn' }}">{{ $result['percentage'] }}% · {{ $result['grade'] }} · {{ ucfirst((string) $result['result']) }}</span>
        </div>
        <div class="dash-table-wrap">
            <table class="dash-table">
                <thead><tr><th>Subject</th><th>Marks</th><th>Grade</th></tr></thead>
                <tbody>
                    @foreach ($result['subjects'] as $subject)
                        <tr>
                            <td>{{ $subject['subject'] }}</td>
                            <td>{{ $subject['absent'] ? 'Absent' : ($subject['obtained'] ?? '—').' / '.$subject['max'] }}</td>
                            <td>{{ $subject['grade'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@empty
    <section class="dash-panel"><p class="dash-muted">No results have been published yet.</p></section>
@endforelse
@endsection
