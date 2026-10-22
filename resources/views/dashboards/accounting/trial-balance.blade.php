@extends('layouts.dashboard')

@section('title', 'Trial Balance')
@section('page_heading', 'Accounts')

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Trial Balance</h1>
        <p>Every account's balance on a date. Total debits always equal total credits when the books are right.</p>
    </div>
</section>

@include('dashboards.accounting.tabs', ['current' => 'accounts.trial-balance'])

<section class="dash-panel" data-accounting="trial-balance" data-url="{{ route('erp.ledger.trial-balance') }}" data-ledger-url="{{ url('/accounts/chart') }}">
    <div class="dash-panel-head">
        <div>
            <h2>As of <span data-as-of-label>{{ now()->format('d M Y') }}</span></h2>
            <p data-balance-status>Loading…</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-date-field">
                <span class="dash-visually-hidden">As of</span>
                <input type="date" data-filter="as_of" value="{{ now()->toDateString() }}">
            </label>
        </div>
    </div>
    <div class="dash-table-wrap">
        <table class="dash-table dash-trial-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Account</th>
                    <th class="dash-hide-sm">Type</th>
                    <th class="dash-col-num">Debit</th>
                    <th class="dash-col-num">Credit</th>
                </tr>
            </thead>
            <tbody data-rows>
                <tr><td colspan="5" class="dash-muted">Loading…</td></tr>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="2">Total</th>
                    <th class="dash-hide-sm"></th>
                    <th class="dash-col-num" data-total="debit">—</th>
                    <th class="dash-col-num" data-total="credit">—</th>
                </tr>
            </tfoot>
        </table>
    </div>
</section>
@endsection

@section('extra_js')
<script src="{{ asset('js/data-grid.js') }}?v={{ filemtime(public_path('js/data-grid.js')) }}" defer></script>
<script src="{{ asset('js/admin-accounting.js') }}?v={{ filemtime(public_path('js/admin-accounting.js')) }}" defer></script>
@endsection
