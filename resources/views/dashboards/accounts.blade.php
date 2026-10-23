@extends('layouts.dashboard')

@section('title', 'Accounts Dashboard')
@section('page_heading', 'Finance Overview')

@section('content')
@php
    $summary = $data['summary'];
    $payments = $data['recent_payments'];
    $payroll = $summary['payroll'];
@endphp

<section class="dash-page-intro">
    <h1>Welcome, {{ $user->name }}</h1>
    <p>Fee collections, vouchers and payroll</p>
</section>

@include('partials.sticky-notes', ['limit' => 8])

<section class="dash-stats">
    <article class="dash-stat">
        <span class="dash-stat-label">Collected Today</span>
        <strong class="dash-stat-value dash-stat-value-sm">Rs {{ number_format($summary['collected_today'] / 100) }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">This Month</span>
        <strong class="dash-stat-value dash-stat-value-sm">Rs {{ number_format($summary['collected_this_month'] / 100) }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Open / Overdue Vouchers</span>
        <strong class="dash-stat-value">{{ $summary['open_invoices'] }} / {{ $summary['overdue_invoices'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Payroll</span>
        <strong class="dash-stat-value dash-stat-value-sm">{{ $payroll ? $payroll['month'].' · '.ucfirst($payroll['status']) : '—' }}</strong>
    </article>
</section>

<section class="dash-panel">
    <div class="dash-panel-head">
        <h2>Latest Receipts</h2>
        <a class="dash-btn dash-btn-ghost" href="{{ route('accounts.fees.receipts') }}">All receipts</a>
    </div>
    @if ($payments === [])
        <p class="dash-muted">No fee received yet.</p>
    @else
        <div class="dash-table-wrap">
            <table class="dash-table">
                <thead>
                    <tr><th>Receipt</th><th>Student</th><th>Method</th><th>Amount</th><th>Date</th></tr>
                </thead>
                <tbody>
                    @foreach ($payments as $payment)
                        <tr>
                            <td>{{ $payment['receipt_no'] }}</td>
                            <td>{{ $payment['student'] ?? '—' }}</td>
                            <td>{{ $payment['method'] ?? '—' }}</td>
                            <td>Rs {{ number_format($payment['amount'] / 100) }}</td>
                            <td>{{ $payment['received_on'] ? \Illuminate\Support\Carbon::parse($payment['received_on'])->format('d M Y') : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
