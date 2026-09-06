@extends('layouts.dashboard')

@section('title', 'Grades')
@section('page_heading', 'Grades & Transcript')

@section('content')
<section class="dash-page-intro">
    <h1>Grades</h1>
    <p>{{ $data['semester'] }} · current published assessments.</p>
</section>

<section class="dash-stats">
    <article class="dash-stat">
        <span class="dash-stat-label">GPA</span>
        <strong class="dash-stat-value">{{ $data['gpa'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Assessments</span>
        <strong class="dash-stat-value">{{ count($data['grades']) }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Semester</span>
        <strong class="dash-stat-value dash-stat-value-sm">Fall 2026</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Standing</span>
        <strong class="dash-stat-value dash-stat-value-sm">Good</strong>
    </article>
</section>

<section class="dash-panel">
    <div class="dash-panel-head">
        <h2>Published Grades</h2>
    </div>
    <div class="dash-table-wrap">
        <table class="dash-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Course</th>
                    <th>Assessment</th>
                    <th>Grade</th>
                    <th>Points</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['grades'] as $grade)
                    <tr>
                        <td>{{ $grade['course_code'] }}</td>
                        <td>{{ $grade['course_title'] }}</td>
                        <td>{{ $grade['assessment'] }}</td>
                        <td><span class="dash-badge dash-badge-ok">{{ $grade['grade'] }}</span></td>
                        <td>{{ number_format($grade['grade_points'], 1) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
@endsection
