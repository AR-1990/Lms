@extends('layouts.dashboard')

@section('title', 'Attendance')
@section('page_heading', 'Attendance Record')

@section('content')
<section class="dash-page-intro">
    <h1>Attendance</h1>
    <p>Course-wise presence summary and recent attendance logs.</p>
</section>

<section class="dash-stats">
    <article class="dash-stat">
        <span class="dash-stat-label">Overall</span>
        <strong class="dash-stat-value dash-stat-value-sm">{{ $data['overall_percentage'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Courses Tracked</span>
        <strong class="dash-stat-value">{{ count($data['breakdown']) }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Classes Attended</span>
        <strong class="dash-stat-value">{{ collect($data['breakdown'])->sum('attended') }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Total Classes</span>
        <strong class="dash-stat-value">{{ collect($data['breakdown'])->sum('total_classes') }}</strong>
    </article>
</section>

<div class="dash-grid-2">
    <section class="dash-panel">
        <div class="dash-panel-head">
            <h2>Course Breakdown</h2>
        </div>
        <div class="dash-table-wrap">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Course</th>
                        <th>Attended</th>
                        <th>Total</th>
                        <th>%</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['breakdown'] as $row)
                        <tr>
                            <td>{{ $row['course'] }}</td>
                            <td>{{ $row['attended'] }}</td>
                            <td>{{ $row['total_classes'] }}</td>
                            <td>{{ $row['percentage'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="dash-panel">
        <div class="dash-panel-head">
            <h2>Recent Logs</h2>
        </div>
        <div class="dash-table-wrap">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Course</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['recent_logs'] as $log)
                        <tr>
                            <td>{{ $log['date'] }}</td>
                            <td>{{ $log['course'] }}</td>
                            <td>
                                <span class="dash-badge {{ $log['status'] === 'Present' ? 'dash-badge-ok' : 'dash-badge-warn' }}">
                                    {{ $log['status'] }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
