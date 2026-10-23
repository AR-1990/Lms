@extends('layouts.dashboard')

@section('title', 'Depreciation')
@section('page_heading', 'Assets')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Depreciation</h1>
        <p>Each month, charge depreciation on every asset (straight line or reducing balance, never below salvage). One journal voucher per month.</p>
    </div>
</section>

@include('dashboards.assets.tabs', ['current' => 'admin.assets.depreciation'])

<section class="dash-panel" data-depreciation>
    <div class="dash-panel-head">
        <div>
            <h2>Run a month</h2>
            <p>Preview first; posting creates the voucher. Only the latest month can be reversed.</p>
        </div>
        <div class="dash-filters-inline">
            <input type="month" class="dash-input" data-dep-month value="{{ now()->format('Y-m') }}" max="{{ now()->format('Y-m') }}" aria-label="Month">
            <button type="button" class="dash-btn dash-btn-ghost" data-dep-preview>Preview</button>
            <button type="button" class="dash-btn dash-btn-primary" data-dep-run>Post</button>
            <button type="button" class="dash-btn dash-btn-ghost" data-dep-reverse>Reverse</button>
        </div>
    </div>
    <div class="dash-table-wrap" data-dep-result><p class="dash-muted">Pick a month and press Preview.</p></div>
</section>

<section class="dash-panel" data-assets="depreciation" data-datatable-url="{{ url('/api/erp/asset-depreciation/datatable') }}" data-resource-url="{{ url('/api/erp/asset-depreciation') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Posted months</h2>
            <p>Newest first.</p>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Assets</th>
                    <th>Amount</th>
                    <th>Voucher</th>
                </tr>
            </thead>
        </table>
    </div>
</section>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-assets.js') }}?v={{ filemtime(public_path('js/admin-assets.js')) }}" defer></script>
@endsection
