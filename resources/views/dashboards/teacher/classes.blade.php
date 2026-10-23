@extends('layouts.dashboard')

@section('title', 'My Classes')
@section('page_heading', 'My Classes')

@section('content')
<section class="dash-page-intro">
    <h1>My Classes</h1>
    <p>Your subjects and sections this session, with their timetable periods.</p>
</section>

<section class="dash-panel">
    @if ($data['classes'] === [])
        <p class="dash-muted">No classes are assigned to you this session.</p>
    @else
        <div class="dash-table-wrap">
            <table class="dash-table">
                <thead>
                    <tr><th>Subject</th><th>Class</th><th>Students</th><th>Periods / week</th><th>Timetable</th><th>Room</th></tr>
                </thead>
                <tbody>
                    @foreach ($data['classes'] as $assigned)
                        <tr>
                            <td><strong>{{ $assigned['title'] }}</strong> @if ($assigned['course_code'])<span class="dash-muted">{{ $assigned['course_code'] }}</span>@endif @if ($assigned['is_class_teacher'])<span class="dash-badge dash-badge-brand">Class teacher</span>@endif</td>
                            <td>{{ $assigned['section'] }}</td>
                            <td>{{ $assigned['students'] }}</td>
                            <td>{{ $assigned['periods_per_week'] ?? '—' }}</td>
                            <td>{{ $assigned['schedule'] ?: '—' }}</td>
                            <td>{{ $assigned['room'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
