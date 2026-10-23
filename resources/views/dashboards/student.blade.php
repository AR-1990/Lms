@extends('layouts.dashboard')

@section('title', 'Student Dashboard')
@section('page_heading', 'Academic Overview')

@section('content')
@php
    $student = $data['student'];
    $summary = $data['academic_summary'];
    $exams = $data['upcoming_exams'];
@endphp

<section class="dash-page-intro">
    <h1>Welcome, {{ $student['name'] ?? $user->name }}</h1>
    <p>{{ $student ? collect([trim(($student['class'] ?? '').' '.($student['section'] ?? '')), $student['roll_no'] ? 'Roll '.$student['roll_no'] : null, $student['admission_no']])->filter()->implode(' · ') : 'Your account is not linked to a student record yet. Please contact the school office.' }}</p>
</section>

@include('partials.sticky-notes', ['limit' => 8])

@if ($student)
<section class="dash-stats">
    <article class="dash-stat">
        <span class="dash-stat-label">Attendance</span>
        <strong class="dash-stat-value">{{ $summary['attendance_percentage'] === null ? '—' : $summary['attendance_percentage'].'%' }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Last Result</span>
        <strong class="dash-stat-value dash-stat-value-sm">{{ $summary['latest_result'] ? $summary['latest_result']['percentage'].'% · '.$summary['latest_result']['grade'] : '—' }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Fee Due</span>
        <strong class="dash-stat-value dash-stat-value-sm">Rs {{ number_format($summary['fee_balance'] / 100) }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Upcoming Papers</span>
        <strong class="dash-stat-value">{{ count($exams) }}</strong>
    </article>
</section>

@if ($bus = $summary['bus'] ?? null)
<section class="dash-panel">
    <div class="dash-panel-head"><h2>School Bus</h2></div>
    <dl class="dash-asset-facts">
        <div><dt>Route</dt><dd>{{ $bus['route'] }}</dd></div>
        <div><dt>Stop</dt><dd>{{ $bus['stop'] }}</dd></div>
        <div><dt>Pickup / drop</dt><dd>{{ $bus['pickup_time'] ?? '—' }} / {{ $bus['drop_time'] ?? '—' }}</dd></div>
        <div><dt>Vehicle</dt><dd>{{ $bus['vehicle'] ?? '—' }}</dd></div>
        <div><dt>Driver</dt><dd>{{ $bus['driver'] ?? '—' }}{{ $bus['driver_phone'] ? ' · '.$bus['driver_phone'] : '' }}</dd></div>
        <div><dt>Attendant</dt><dd>{{ $bus['attendant'] ?? '—' }}{{ $bus['attendant_phone'] ? ' · '.$bus['attendant_phone'] : '' }}</dd></div>
    </dl>
</section>
@endif

@include('dashboards.partials.counselling-notes', ['notes' => $summary['counselling'] ?? [], 'title' => 'From your counsellor'])

<section class="dash-panel">
    <div class="dash-panel-head">
        <h2>Upcoming Papers</h2>
        <a class="dash-btn dash-btn-ghost" href="{{ route('student.grades') }}">My results</a>
    </div>
    @if ($exams === [])
        <p class="dash-muted">No papers scheduled.</p>
    @else
        <div class="dash-table-wrap">
            <table class="dash-table">
                <thead>
                    <tr><th>Exam</th><th>Subject</th><th>Date</th><th>Starts</th></tr>
                </thead>
                <tbody>
                    @foreach ($exams as $exam)
                        <tr>
                            <td>{{ $exam['exam'] }}</td>
                            <td>{{ $exam['subject'] }}</td>
                            <td>{{ $exam['date'] ? \Illuminate\Support\Carbon::parse($exam['date'])->format('d M Y') : '—' }}</td>
                            <td>{{ $exam['starts_at'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endif
@endsection
