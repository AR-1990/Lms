@extends('layouts.dashboard')

@section('title', 'Courses')
@section('page_heading', 'Enrolled Courses')

@section('content')
<section class="dash-page-intro">
    <h1>Courses</h1>
    <p>Your enrolled subjects for the current semester.</p>
</section>

<section class="dash-stats">
    <article class="dash-stat">
        <span class="dash-stat-label">Courses</span>
        <strong class="dash-stat-value">{{ count($data['courses']) }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Credit Hours</span>
        <strong class="dash-stat-value">{{ collect($data['courses'])->sum('credit_hours') }}</strong>
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
        <h2>Course List</h2>
    </div>
    <div class="dash-table-wrap">
        <table class="dash-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Title</th>
                    <th>Instructor</th>
                    <th>Schedule</th>
                    <th>Credits</th>
                    <th>Progress</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['courses'] as $course)
                    <tr>
                        <td>{{ $course['code'] }}</td>
                        <td>{{ $course['title'] }}</td>
                        <td>{{ $course['instructor'] }}</td>
                        <td>{{ $course['schedule'] }}</td>
                        <td>{{ $course['credit_hours'] }}</td>
                        <td>{{ $course['progress'] }}</td>
                        <td><span class="dash-badge dash-badge-ok">{{ $course['status'] }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
@endsection
