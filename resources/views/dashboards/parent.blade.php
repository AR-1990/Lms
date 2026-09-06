@extends('layouts.dashboard')

@section('title', 'Parent Dashboard')
@section('page_heading', 'Family Overview')

@section('content')
@php
    $summary = $data['summary'];
    $children = $data['children'];
    $notices = $data['recent_notices'];
@endphp

<section class="dash-page-intro">
    <h1>Welcome, {{ $user->name }}</h1>
    <p>Attendance, fees, and notices for your children</p>
</section>

<section class="dash-stats">
    <article class="dash-stat">
        <span class="dash-stat-label">Outstanding Fees</span>
        <strong class="dash-stat-value dash-stat-value-sm">{{ $summary['outstanding_fees'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Next PTM</span>
        <strong class="dash-stat-value dash-stat-value-sm">{{ $summary['next_ptm'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Unread Notices</span>
        <strong class="dash-stat-value">{{ $summary['unread_notices'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Today</span>
        <strong class="dash-stat-value dash-stat-value-sm">{{ $summary['today_attendance'] }}</strong>
    </article>
</section>

<div class="dash-grid-2">
    <section class="dash-panel">
        <div class="dash-panel-head">
            <h2>Children</h2>
        </div>
        <div class="dash-table-wrap">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Grade</th>
                        <th>Attendance</th>
                        <th>Fee</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($children as $child)
                        <tr>
                            <td>{{ $child['name'] }}</td>
                            <td>{{ $child['grade'] }}</td>
                            <td>{{ $child['attendance'] }}</td>
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

    <section class="dash-panel">
        <div class="dash-panel-head">
            <h2>Recent Notices</h2>
        </div>
        <ul class="dash-list">
            @foreach ($notices as $notice)
                <li>
                    <div>
                        <strong>{{ $notice['title'] }}</strong>
                        <span class="dash-muted">{{ $notice['date'] }} · {{ $notice['type'] }}</span>
                    </div>
                </li>
            @endforeach
        </ul>
    </section>
</div>
@endsection
