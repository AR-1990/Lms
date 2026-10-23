@extends('layouts.dashboard')

@section('title', 'Transfers')
@section('page_heading', 'Inventory')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Transfers</h1>
        <p>Stock moved between stores at its average cost.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>@include('partials.dashboard-icon', ['name' => 'plus']) New Transfer</button>
</section>

@include('dashboards.inventory.tabs', ['current' => 'admin.inventory.transfers'])

<section class="dash-panel" data-inventory="transfers" data-datatable-url="{{ url('/api/erp/stock-transfers/datatable') }}" data-resource-url="{{ url('/api/erp/stock-transfers') }}" data-search-url="{{ url('/api/erp/inventory/items/search') }}" data-recipients-url="{{ url('/api/erp/inventory/recipients') }}" data-can-approve="{{ auth()->user()->hasPermission('approve-requests') ? 1 : 0 }}">
    <div class="dash-panel-head">
        <div>
            <h2>Transfers</h2>
            <p>Newest first.</p>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Transfer</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Date</th>
                    <th>Items</th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal dash-modal-wide" id="transferModal" aria-labelledby="transferModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'arrow-right'])</span>
            <div>
                <h3 id="transferModalTitle" data-modal-title>Move stock</h3>
                <p data-modal-sub></p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-scroll">
            <div class="dash-form-grid">
                @include('dashboards.partials.select', ['name' => 'from_location_id', 'label' => 'From store', 'options' => $data['stores'], 'required' => true, 'id' => 'trFrom'])
                @include('dashboards.partials.select', ['name' => 'to_location_id', 'label' => 'To store', 'options' => $data['stores'], 'required' => true, 'id' => 'trTo'])
                @include('dashboards.partials.field', ['name' => 'transferred_on', 'label' => 'Date', 'type' => 'date', 'required' => true, 'id' => 'trDate'])
                @include('dashboards.partials.field', ['name' => 'remarks', 'label' => 'Remarks', 'id' => 'trRemarks'])
            </div>
            <div class="dash-lines" data-lines="transfer">
                <div class="dash-lines-head">
                    <strong>Items</strong>
                    <input type="search" class="dash-input" placeholder="Search an item by name or code…" data-line-search aria-label="Search item">
                </div>
                <ul class="dash-holder-results" data-line-results></ul>
                <table class="dash-table dash-lines-table">
                    <thead><tr><th>Item</th><th>Qty</th><th></th></tr></thead>
                    <tbody data-line-rows><tr class="dash-muted" data-line-empty><td colspan="3">No items yet. Search above to add.</td></tr></tbody>
                </table>
                <p class="dash-field-error" data-error-for="items" hidden></p>
            </div>
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions" data-modal-actions>
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Move</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-inventory.js') }}?v={{ filemtime(public_path('js/admin-inventory.js')) }}" defer></script>
@endsection
