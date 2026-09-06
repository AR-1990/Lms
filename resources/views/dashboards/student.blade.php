@extends('layouts.dashboard')

@section('title', 'Student Dashboard')
@section('page_heading', 'Academic Overview')

@section('content')
@php
    $summary = $data['academic_summary'];
    $exams = $data['upcoming_exams'];
@endphp

<section class="dash-page-intro">
    <h1>Welcome, {{ $user->name }}</h1>
    <p>{{ $summary['current_semester'] }}</p>
</section>

<section class="dash-stats">
    <article class="dash-stat">
        <span class="dash-stat-label">CGPA</span>
        <strong class="dash-stat-value">{{ $summary['cgpa'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Enrolled Courses</span>
        <strong class="dash-stat-value">{{ $summary['enrolled_courses_count'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Attendance</span>
        <strong class="dash-stat-value">{{ $summary['overall_attendance'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Upcoming Exams</span>
        <strong class="dash-stat-value">{{ count($exams) }}</strong>
    </article>
</section>

<section class="dash-panel">
    <div class="dash-panel-head">
        <h2>Upcoming Exams</h2>
    </div>
    <div class="dash-table-wrap">
        <table class="dash-table">
            <thead>
                <tr>
                    <th>Course</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Venue</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($exams as $exam)
                    <tr>
                        <td>{{ $exam['course'] }}</td>
                        <td>{{ $exam['date'] }}</td>
                        <td>{{ $exam['time'] }}</td>
                        <td>{{ $exam['venue'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
@endsection
