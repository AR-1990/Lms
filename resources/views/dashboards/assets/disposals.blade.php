@extends('layouts.dashboard')

@section('title', 'Disposals')
@section('page_heading', 'Assets')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Disposals</h1>
        <p>Assets sold, scrapped, donated or written off, with the gain or loss posted against their book value.</p>
    </div>
</section>

@include('dashboards.assets.tabs', ['current' => 'admin.assets.disposals'])

<section class="dash-panel" data-assets="disposals" data-datatable-url="{{ url('/api/erp/asset-disposals/datatable') }}" data-resource-url="{{ url('/api/erp/asset-disposals') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Disposed assets</h2>
            <p>Dispose of an asset from the register.</p>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Asset</th>
                    <th>Date</th>
                    <th>How</th>
                    <th>Book value</th>
                    <th>Sold for</th>
                    <th>Gain / loss</th>
                    <th class="dash-hide-md">Reason</th>
                    <th class="dash-hide-md">Voucher</th>
                </tr>
            </thead>
        </table>
    </div>
</section>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-assets.js') }}?v={{ filemtime(public_path('js/admin-assets.js')) }}" defer></script>
@endsection
