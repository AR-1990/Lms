@extends('layouts.dashboard')

@section('title', 'Subjects')
@section('page_heading', 'My Subjects')

@section('content')
<section class="dash-page-intro">
    <h1>My Subjects</h1>
    <p>Subjects of your class this session and who teaches them.</p>
</section>

<section class="dash-panel">
    @if ($data['courses'] === [])
        <p class="dash-muted">No subjects found for your class.</p>
    @else
        <div class="dash-table-wrap">
            <table class="dash-table">
                <thead>
                    <tr><th>Subject</th><th>Code</th><th>Teacher</th><th>Periods / week</th></tr>
                </thead>
                <tbody>
                    @foreach ($data['courses'] as $course)
                        <tr>
                            <td>{{ $course['title'] }} @if ($course['is_optional'])<span class="dash-badge">Optional</span>@endif</td>
                            <td>{{ $course['code'] ?? '—' }}</td>
                            <td>{{ $course['teacher'] ?? '—' }}</td>
                            <td>{{ $course['periods_per_week'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
