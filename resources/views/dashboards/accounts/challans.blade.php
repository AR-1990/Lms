@extends('layouts.dashboard')

@section('title', 'Challans')
@section('page_heading', 'Fee Challans')

@section('content')
@php $summary = $data['summary']; @endphp

<section class="dash-page-intro">
    <h1>Challans</h1>
    <p>Issued, pending, and overdue fee challans.</p>
</section>

<section class="dash-stats">
    <article class="dash-stat">
        <span class="dash-stat-label">Pending</span>
        <strong class="dash-stat-value">{{ $summary['pending'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Overdue</span>
        <strong class="dash-stat-value">{{ $summary['overdue'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Issued Today</span>
        <strong class="dash-stat-value">{{ $summary['issued_today'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Cleared Today</span>
        <strong class="dash-stat-value">{{ $summary['cleared_today'] }}</strong>
    </article>
</section>

<section class="dash-panel">
    <div class="dash-panel-head">
        <h2>Challan Register</h2>
    </div>
    <div class="dash-table-wrap">
        <table class="dash-table">
            <thead>
                <tr>
                    <th>Number</th>
                    <th>Student</th>
                    <th>Amount</th>
                    <th>Issued</th>
                    <th>Due</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['challans'] as $challan)
                    <tr>
                        <td>{{ $challan['number'] }}</td>
                        <td>{{ $challan['student'] }}</td>
                        <td>{{ $challan['amount'] }}</td>
                        <td>{{ $challan['issued'] }}</td>
                        <td>{{ $challan['due'] }}</td>
                        <td>
                            @php
                                $badge = match ($challan['status']) {
                                    'Paid' => 'dash-badge-ok',
                                    'Overdue', 'Due' => 'dash-badge-warn',
                                    default => '',
                                };
                            @endphp
                            <span class="dash-badge {{ $badge }}">{{ $challan['status'] }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
@endsection
