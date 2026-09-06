@extends('layouts.dashboard')

@section('title', 'Accounts Dashboard')
@section('page_heading', 'Finance Overview')

@section('content')
@php
    $summary = $data['summary'];
    $payments = $data['recent_payments'];
    $breakdown = $data['fee_breakdown'];
@endphp

<section class="dash-page-intro">
    <h1>Welcome, {{ $user->name }}</h1>
    <p>Fee collections, challans, and payroll status</p>
</section>

<section class="dash-stats">
    <article class="dash-stat">
        <span class="dash-stat-label">Collected Today</span>
        <strong class="dash-stat-value dash-stat-value-sm">{{ $summary['collections_today'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Pending Challans</span>
        <strong class="dash-stat-value">{{ $summary['pending_challans'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Overdue Accounts</span>
        <strong class="dash-stat-value">{{ $summary['overdue_accounts'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Payroll</span>
        <strong class="dash-stat-value dash-stat-value-sm">{{ $summary['payroll_status'] }}</strong>
    </article>
</section>

<div class="dash-grid-2">
    <section class="dash-panel">
        <div class="dash-panel-head">
            <h2>Recent Payments</h2>
        </div>
        <div class="dash-table-wrap">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Receipt</th>
                        <th>Student</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payments as $payment)
                        <tr>
                            <td>{{ $payment['receipt'] }}</td>
                            <td>{{ $payment['student'] }}</td>
                            <td>{{ $payment['amount'] }}</td>
                            <td>{{ $payment['method'] }}</td>
                            <td>{{ $payment['time'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="dash-panel">
        <div class="dash-panel-head">
            <h2>Fee Breakdown</h2>
        </div>
        <ul class="dash-list">
            @foreach ($breakdown as $row)
                <li>
                    <div>
                        <strong>{{ $row['category'] }}</strong>
                        <span class="dash-muted">Collected {{ $row['collected'] }} · Pending {{ $row['pending'] }}</span>
                    </div>
                </li>
            @endforeach
        </ul>
    </section>
</div>
@endsection
