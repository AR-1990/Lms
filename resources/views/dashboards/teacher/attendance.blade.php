@extends('layouts.dashboard')

@section('title', 'Attendance')
@section('page_heading', 'Attendance Register')

@section('content')
@php $summary = $data['summary']; @endphp

<section class="dash-page-intro">
    <h1>Attendance</h1>
    <p>Mark and review class attendance sessions.</p>
</section>

<section class="dash-stats">
    <article class="dash-stat">
        <span class="dash-stat-label">Sessions Today</span>
        <strong class="dash-stat-value">{{ $summary['sessions_today'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Marked</span>
        <strong class="dash-stat-value">{{ $summary['marked'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Pending</span>
        <strong class="dash-stat-value">{{ $summary['pending'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Avg Presence</span>
        <strong class="dash-stat-value dash-stat-value-sm">{{ $summary['avg_presence'] }}</strong>
    </article>
</section>

<section class="dash-panel">
    <div class="dash-panel-head">
        <h2>Recent Sessions</h2>
    </div>
    <div class="dash-table-wrap">
        <table class="dash-table">
            <thead>
                <tr>
                    <th>Class</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Present</th>
                    <th>Absent</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['sessions'] as $session)
                    <tr>
                        <td>{{ $session['class'] }}</td>
                        <td>{{ $session['date'] }}</td>
                        <td>{{ $session['time'] }}</td>
                        <td>{{ $session['present'] }}</td>
                        <td>{{ $session['absent'] }}</td>
                        <td>
                            <span class="dash-badge {{ $session['status'] === 'Submitted' ? 'dash-badge-ok' : 'dash-badge-warn' }}">
                                {{ $session['status'] }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
@endsection
