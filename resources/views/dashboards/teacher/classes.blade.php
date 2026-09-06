@extends('layouts.dashboard')

@section('title', 'My Classes')
@section('page_heading', 'Assigned Classes')

@section('content')
<section class="dash-page-intro">
    <h1>My Classes</h1>
    <p>Sections assigned to you for the current semester.</p>
</section>

<section class="dash-stats">
    <article class="dash-stat">
        <span class="dash-stat-label">Classes</span>
        <strong class="dash-stat-value">{{ count($data['classes']) }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Students</span>
        <strong class="dash-stat-value">{{ collect($data['classes'])->sum('enrolled_students') }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Semester</span>
        <strong class="dash-stat-value dash-stat-value-sm">Fall 2026</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Status</span>
        <strong class="dash-stat-value dash-stat-value-sm">Active</strong>
    </article>
</section>

<section class="dash-panel">
    <div class="dash-panel-head">
        <h2>Class Roster</h2>
    </div>
    <div class="dash-table-wrap">
        <table class="dash-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Title</th>
                    <th>Section</th>
                    <th>Schedule</th>
                    <th>Room</th>
                    <th>Students</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['classes'] as $class)
                    <tr>
                        <td>{{ $class['course_code'] }}</td>
                        <td>{{ $class['title'] }}</td>
                        <td>{{ $class['section'] }}</td>
                        <td>{{ $class['schedule'] }}</td>
                        <td>{{ $class['room'] }}</td>
                        <td>{{ $class['enrolled_students'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
@endsection
