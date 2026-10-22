@extends('layouts.dashboard')

@section('title', $data['account']->name.' Ledger')
@section('page_heading', 'Accounts')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
@php
    $account = $data['account'];
@endphp

<a href="{{ route('accounts.chart') }}" class="dash-back-link">
    @include('partials.dashboard-icon', ['name' => 'arrow-left'])
    Back to chart of accounts
</a>

<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>{{ $account->code }} {{ $account->name }}</h1>
        <p>{{ $account->accountType?->name }} account{{ $account->parent ? ' under '.$account->parent->name : '' }}. Only posted vouchers appear here.</p>
    </div>
</section>

<section class="dash-stats dash-stats-3" data-ledger-summary>
    <article class="dash-stat">
        <span class="dash-stat-icon">@include('partials.dashboard-icon', ['name' => 'book'])</span>
        <span class="dash-stat-label">Opening balance</span>
        <strong class="dash-stat-value dash-stat-value-sm" data-summary="opening_balance">—</strong>
    </article>
    <article class="dash-stat dash-stat--blue">
        <span class="dash-stat-icon">@include('partials.dashboard-icon', ['name' => 'activity'])</span>
        <span class="dash-stat-label">Debits / credits in period</span>
        <strong class="dash-stat-value dash-stat-value-sm" data-summary="movement">—</strong>
    </article>
    <article class="dash-stat dash-stat--green">
        <span class="dash-stat-icon">@include('partials.dashboard-icon', ['name' => 'check'])</span>
        <span class="dash-stat-label">Closing balance</span>
        <strong class="dash-stat-value dash-stat-value-sm" data-summary="closing_balance">—</strong>
    </article>
</section>

<section class="dash-panel" data-accounting="ledger" data-datatable-url="{{ route('erp.ledger.accounts.ledger', $account) }}">
    <div class="dash-panel-head">
        <div>
            <h2>Ledger</h2>
            <p>Pick a date range; the opening balance covers everything before it.</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-date-field">
                <span class="dash-visually-hidden">From</span>
                <input type="date" data-filter="from" value="{{ now()->startOfMonth()->toDateString() }}">
            </label>
            <label class="dash-date-field">
                <span class="dash-visually-hidden">To</span>
                <input type="date" data-filter="to" value="{{ now()->toDateString() }}">
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Voucher</th>
                    <th>Narration</th>
                    <th>Debit</th>
                    <th>Credit</th>
                </tr>
            </thead>
        </table>
    </div>
</section>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-accounting.js') }}?v={{ filemtime(public_path('js/admin-accounting.js')) }}" defer></script>
@endsection
