@extends('layouts.dashboard')

@section('title', 'Fees')
@section('page_heading', 'Fee Ledger')

@section('content')
@php $summary = $data['summary']; @endphp

<section class="dash-page-intro">
    <h1>Fees</h1>
    <p>Challans, dues, and payment history for your children.</p>
</section>

<section class="dash-stats">
    <article class="dash-stat">
        <span class="dash-stat-label">Outstanding</span>
        <strong class="dash-stat-value dash-stat-value-sm">{{ $summary['outstanding'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Paid YTD</span>
        <strong class="dash-stat-value dash-stat-value-sm">{{ $summary['paid_ytd'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Next Due</span>
        <strong class="dash-stat-value dash-stat-value-sm">{{ $summary['next_due'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Open Challans</span>
        <strong class="dash-stat-value">{{ $summary['open_challans'] }}</strong>
    </article>
</section>

<section class="dash-panel">
    <div class="dash-panel-head">
        <h2>Fee Ledger</h2>
    </div>
    <div class="dash-table-wrap">
        <table class="dash-table">
            <thead>
                <tr>
                    <th>Challan</th>
                    <th>Student</th>
                    <th>Description</th>
                    <th>Amount</th>
                    <th>Due</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['ledger'] as $row)
                    <tr>
                        <td>{{ $row['challan'] }}</td>
                        <td>{{ $row['student'] }}</td>
                        <td>{{ $row['description'] }}</td>
                        <td>{{ $row['amount'] }}</td>
                        <td>{{ $row['due'] }}</td>
                        <td>
                            <span class="dash-badge {{ $row['status'] === 'Paid' ? 'dash-badge-ok' : 'dash-badge-warn' }}">
                                {{ $row['status'] }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
@endsection
