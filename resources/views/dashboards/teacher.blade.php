@extends('layouts.dashboard')

@section('title', 'Faculty Dashboard')
@section('page_heading', 'Faculty Overview')

@section('content')
@php
    $metrics = $data['metrics'];
    $schedule = $data['today_schedule'];
    $teacher = $data['teacher'];
@endphp

<section class="dash-page-intro">
    <h1>Welcome, {{ $teacher['name'] ?? $user->name }}</h1>
    <p>{{ collect([$teacher['designation'] ?? null, $teacher['department'] ?? null, $teacher['employee_code'] ?? null])->filter()->implode(' · ') ?: 'Your classes and today\'s periods' }}</p>
</section>

@include('partials.sticky-notes', ['limit' => 8])

<section class="dash-stats">
    <article class="dash-stat">
        <span class="dash-stat-label">Sections</span>
        <strong class="dash-stat-value">{{ $metrics['sections'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Students</span>
        <strong class="dash-stat-value">{{ $metrics['students'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Periods Today</span>
        <strong class="dash-stat-value">{{ $metrics['periods_today'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Attendance Today</span>
        <strong class="dash-stat-value">{{ $metrics['today_attendance_percentage'] === null ? '—' : $metrics['today_attendance_percentage'].'%' }}</strong>
    </article>
</section>

<section class="dash-panel">
    <div class="dash-panel-head">
        <h2>Today's Periods</h2>
        <a class="dash-btn dash-btn-ghost" href="{{ route('teacher.classes') }}">My classes</a>
    </div>
    @if ($schedule === [])
        <p class="dash-muted">No periods on your timetable today.</p>
    @else
        <div class="dash-table-wrap">
            <table class="dash-table">
                <thead>
                    <tr><th>Period</th><th>Time</th><th>Class</th><th>Subject</th><th>Room</th></tr>
                </thead>
                <tbody>
                    @foreach ($schedule as $slot)
                        <tr>
                            <td>{{ $slot['period'] }}</td>
                            <td>{{ $slot['starts_at'] }}{{ $slot['ends_at'] ? ' – '.$slot['ends_at'] : '' }}</td>
                            <td>{{ $slot['section'] }}</td>
                            <td>{{ $slot['subject'] }}</td>
                            <td>{{ $slot['room'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
