@extends('layouts.dashboard')

@section('title', 'Children')
@section('page_heading', 'Linked Children')

@section('content')
<section class="dash-page-intro">
    <h1>Children</h1>
    <p>Academic profiles linked to your parent account.</p>
</section>

<section class="dash-stats">
    <article class="dash-stat">
        <span class="dash-stat-label">Children</span>
        <strong class="dash-stat-value">{{ count($data['children']) }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Avg Attendance</span>
        <strong class="dash-stat-value dash-stat-value-sm">95.9%</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Fee Alerts</span>
        <strong class="dash-stat-value">1</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Status</span>
        <strong class="dash-stat-value dash-stat-value-sm">Active</strong>
    </article>
</section>

<section class="dash-panel">
    <div class="dash-panel-head">
        <h2>Student Profiles</h2>
    </div>
    <div class="dash-table-wrap">
        <table class="dash-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Roll No</th>
                    <th>Grade</th>
                    <th>Homeroom</th>
                    <th>Attendance</th>
                    <th>CGPA</th>
                    <th>Fee</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['children'] as $child)
                    <tr>
                        <td>{{ $child['name'] }}</td>
                        <td>{{ $child['roll_no'] }}</td>
                        <td>{{ $child['grade'] }}</td>
                        <td>{{ $child['homeroom'] }}</td>
                        <td>{{ $child['attendance'] }}</td>
                        <td>{{ $child['cgpa'] }}</td>
                        <td>
                            <span class="dash-badge {{ $child['fee_status'] === 'Paid' ? 'dash-badge-ok' : 'dash-badge-warn' }}">
                                {{ $child['fee_status'] }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
@endsection
