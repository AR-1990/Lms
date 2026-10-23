@extends('layouts.dashboard')

@section('title', 'Stock Counts')
@section('page_heading', 'Inventory')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Stock Counts</h1>
        <p>Count a store and correct the system. Shortages are expensed; "Opening Stock" sets starting balances. Approvers only.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>@include('partials.dashboard-icon', ['name' => 'plus']) New Count</button>
</section>

@include('dashboards.inventory.tabs', ['current' => 'admin.inventory.counts'])

<section class="dash-panel" data-inventory="counts" data-datatable-url="{{ url('/api/erp/stock-adjustments/datatable') }}" data-resource-url="{{ url('/api/erp/stock-adjustments') }}" data-search-url="{{ url('/api/erp/inventory/items/search') }}" data-recipients-url="{{ url('/api/erp/inventory/recipients') }}" data-can-approve="{{ auth()->user()->hasPermission('approve-requests') ? 1 : 0 }}">
    <div class="dash-panel-head">
        <div>
            <h2>Counts</h2>
            <p>Each count is posted to the ledger.</p>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Count</th>
                    <th>Store</th>
                    <th>Date</th>
                    <th>Reason</th>
                    <th>Items</th>
                    <th class="dash-hide-md">By</th>
                    <th class="dash-hide-md">Voucher</th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal dash-modal-wide" id="countModal" aria-labelledby="countModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'hash'])</span>
            <div>
                <h3 id="countModalTitle" data-modal-title>Stock count</h3>
                <p data-modal-sub>Enter what you counted; the difference is posted.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-scroll">
            <div class="dash-form-grid">
                @include('dashboards.partials.select', ['name' => 'location_id', 'label' => 'Store', 'options' => $data['stores'], 'required' => true, 'id' => 'ctStore'])
                @include('dashboards.partials.field', ['name' => 'adjusted_on', 'label' => 'Date', 'type' => 'date', 'required' => true, 'id' => 'ctDate'])
                @include('dashboards.partials.select', ['name' => 'reason_id', 'label' => 'Reason', 'options' => $data['reasons'], 'id' => 'ctReason'])
                @include('dashboards.partials.field', ['name' => 'remarks', 'label' => 'Remarks', 'id' => 'ctRemarks'])
            </div>
            <div class="dash-lines" data-lines="count">
                <div class="dash-lines-head">
                    <strong>Items</strong>
                    <input type="search" class="dash-input" placeholder="Search an item by name or code…" data-line-search aria-label="Search item">
                </div>
                <ul class="dash-holder-results" data-line-results></ul>
                <table class="dash-table dash-lines-table">
                    <thead><tr><th>Item</th><th>System</th><th>Counted</th><th>Unit cost (Rs) for extra</th><th></th></tr></thead>
                    <tbody data-line-rows><tr class="dash-muted" data-line-empty><td colspan="5">No items yet. Search above to add.</td></tr></tbody>
                </table>
                <p class="dash-field-error" data-error-for="items" hidden></p>
            </div>
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions" data-modal-actions>
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Post Count</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-inventory.js') }}?v={{ filemtime(public_path('js/admin-inventory.js')) }}" defer></script>
@endsection
