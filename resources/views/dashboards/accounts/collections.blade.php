@extends('layouts.dashboard')

@section('title', 'Collections')
@section('page_heading', 'Fee Collections')

@section('content')
@php $summary = $data['summary']; @endphp

<section class="dash-page-intro">
    <h1>Collections</h1>
    <p>Daily receipts and payment channel performance.</p>
</section>

<section class="dash-stats">
    <article class="dash-stat">
        <span class="dash-stat-label">Today</span>
        <strong class="dash-stat-value dash-stat-value-sm">{{ $summary['today'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">This Week</span>
        <strong class="dash-stat-value dash-stat-value-sm">{{ $summary['week'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">This Month</span>
        <strong class="dash-stat-value dash-stat-value-sm">{{ $summary['month'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Cash Share</span>
        <strong class="dash-stat-value dash-stat-value-sm">{{ $summary['cash_share'] }}</strong>
    </article>
</section>

<section class="dash-panel">
    <div class="dash-panel-head">
        <h2>Receipt Register</h2>
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
                    <th>Cashier</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['payments'] as $payment)
                    <tr>
                        <td>{{ $payment['receipt'] }}</td>
                        <td>{{ $payment['student'] }}</td>
                        <td>{{ $payment['amount'] }}</td>
                        <td>{{ $payment['method'] }}</td>
                        <td>{{ $payment['time'] }}</td>
                        <td>{{ $payment['cashier'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
@endsection
