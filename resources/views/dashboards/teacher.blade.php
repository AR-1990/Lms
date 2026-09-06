@extends('layouts.dashboard')

@section('title', 'Faculty Dashboard')
@section('page_heading', 'Faculty Overview')

@section('content')
@php
    $metrics = $data['metrics'];
    $schedule = $data['today_schedule'];
@endphp

<section class="dash-page-intro">
    <h1>Welcome, {{ $user->name }}</h1>
    <p>Today’s teaching schedule and class metrics</p>
</section>

<section class="dash-stats">
    <article class="dash-stat">
        <span class="dash-stat-label">Assigned Classes</span>
        <strong class="dash-stat-value">{{ $metrics['assigned_classes_count'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Total Students</span>
        <strong class="dash-stat-value">{{ $metrics['total_students_count'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Pending Assignments</span>
        <strong class="dash-stat-value">{{ $metrics['pending_assignments_count'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Today’s Attendance</span>
        <strong class="dash-stat-value">{{ $metrics['today_attendance_percentage'] }}</strong>
    </article>
</section>

<section class="dash-panel">
    <div class="dash-panel-head">
        <h2>Today’s Schedule</h2>
    </div>
    <div class="dash-table-wrap">
        <table class="dash-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Subject</th>
                    <th>Room</th>
                    <th>Students</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($schedule as $slot)
                    <tr>
                        <td>{{ $slot['time'] }}</td>
                        <td>{{ $slot['subject'] }}</td>
                        <td>{{ $slot['room'] }}</td>
                        <td>{{ $slot['students_count'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
@endsection
