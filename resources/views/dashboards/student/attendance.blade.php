@extends('layouts.dashboard')

@section('title', 'Attendance')
@section('page_heading', 'My Attendance')

@section('content')
<section class="dash-page-intro">
    <h1>My Attendance</h1>
    <p>This session so far.</p>
</section>

<section class="dash-stats">
    <article class="dash-stat">
        <span class="dash-stat-label">Attendance</span>
        <strong class="dash-stat-value">{{ $data['percentage'] === null ? '—' : $data['percentage'].'%' }}</strong>
    </article>
    @foreach ($data['counts'] as $status => $count)
        <article class="dash-stat">
            <span class="dash-stat-label">{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
            <strong class="dash-stat-value">{{ $count }}</strong>
        </article>
    @endforeach
</section>

<section class="dash-panel">
    <div class="dash-panel-head"><h2>Recent Days</h2></div>
    @if ($data['recent'] === [])
        <p class="dash-muted">No attendance marked yet.</p>
    @else
        <div class="dash-table-wrap">
            <table class="dash-table">
                <thead><tr><th>Date</th><th>Status</th></tr></thead>
                <tbody>
                    @foreach ($data['recent'] as $day)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($day['date'])->format('D, d M Y') }}</td>
                            <td><span class="dash-badge {{ in_array($day['status'], ['present', 'late', 'half_day'], true) ? 'dash-badge-ok' : 'dash-badge-warn' }}">{{ ucfirst(str_replace('_', ' ', $day['status'])) }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
