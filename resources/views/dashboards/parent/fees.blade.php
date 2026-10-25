@extends('layouts.dashboard')

@section('title', 'Fees')
@section('page_heading', 'Fee Vouchers')

@section('content')
@php
    $summary = $data['summary'];
    $meta = $data['meta'];
@endphp

<section class="dash-page-intro">
    <h1>Fees</h1>
    <p>Fee vouchers and payments for your children.</p>
</section>

<section class="dash-stats">
    <article class="dash-stat">
        <span class="dash-stat-label">Outstanding</span>
        <strong class="dash-stat-value dash-stat-value-sm">Rs {{ number_format($summary['outstanding'] / 100) }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Paid So Far</span>
        <strong class="dash-stat-value dash-stat-value-sm">Rs {{ number_format($summary['paid_total'] / 100) }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Open Vouchers</span>
        <strong class="dash-stat-value">{{ $summary['open_invoices'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Next Due</span>
        <strong class="dash-stat-value dash-stat-value-sm">{{ $summary['next_due_date'] ? \Illuminate\Support\Carbon::parse($summary['next_due_date'])->format('d M Y').' · Rs '.number_format($summary['next_due_amount'] / 100) : '—' }}</strong>
    </article>
</section>

<section class="dash-panel">
    @if ($data['ledger'] === [])
        <p class="dash-muted">No fee vouchers yet.</p>
    @else
        <div class="dash-table-wrap">
            <table class="dash-table">
                <thead>
                    <tr><th>Voucher</th><th>Child</th><th>For</th><th>Due</th><th>Total</th><th>Balance</th><th>Status</th><th><span class="dash-visually-hidden">Challan</span></th></tr>
                </thead>
                <tbody>
                    @foreach ($data['ledger'] as $row)
                        <tr>
                            <td>{{ $row['invoice_no'] }}</td>
                            <td>{{ $row['student'] }}</td>
                            <td>{{ $row['description'] }}</td>
                            <td>{{ $row['due_date'] ? \Illuminate\Support\Carbon::parse($row['due_date'])->format('d M Y') : '—' }}</td>
                            <td>Rs {{ number_format($row['total'] / 100) }}</td>
                            <td>Rs {{ number_format($row['balance'] / 100) }}</td>
                            <td><span class="dash-badge {{ $row['status'] === 'paid' ? 'dash-badge-ok' : 'dash-badge-warn' }}">{{ ucfirst(str_replace('_', ' ', $row['status'])) }}</span></td>
                            <td><a class="dash-btn dash-btn-ghost dash-btn-sm" href="{{ route('erp.fees.invoices.pdf', $row['id']) }}" target="_blank" rel="noopener">Download challan</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($meta['last_page'] > 1)
            <nav class="dash-pager" aria-label="Pages">
                @if ($meta['current_page'] > 1)<a class="dash-btn dash-btn-ghost" href="{{ route('parent.fees', ['page' => $meta['current_page'] - 1]) }}">&larr; Newer</a>@endif
                <span class="dash-muted">Page {{ $meta['current_page'] }} of {{ $meta['last_page'] }}</span>
                @if ($meta['current_page'] < $meta['last_page'])<a class="dash-btn dash-btn-ghost" href="{{ route('parent.fees', ['page' => $meta['current_page'] + 1]) }}">Older &rarr;</a>@endif
            </nav>
        @endif
    @endif
</section>
@endsection
