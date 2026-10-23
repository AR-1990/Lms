@extends('layouts.dashboard')

@section('title', 'Goods Received')
@section('page_heading', 'Inventory')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Goods Received</h1>
        <p>Goods received notes (GRN). Receive against an order from Purchase Orders, or record a direct purchase here.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>@include('partials.dashboard-icon', ['name' => 'plus']) Direct Purchase</button>
</section>

@include('dashboards.inventory.tabs', ['current' => 'admin.inventory.receipts'])

<section class="dash-panel" data-inventory="receipts" data-datatable-url="{{ url('/api/erp/goods-receipts/datatable') }}" data-resource-url="{{ url('/api/erp/goods-receipts') }}" data-search-url="{{ url('/api/erp/inventory/items/search') }}" data-recipients-url="{{ url('/api/erp/inventory/recipients') }}" data-can-approve="{{ auth()->user()->hasPermission('approve-requests') ? 1 : 0 }}">
    <div class="dash-panel-head">
        <div>
            <h2>Receipts</h2>
            <p>Each receipt raised stock and was posted to the vendor.</p>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>GRN</th>
                    <th>Vendor</th>
                    <th class="dash-hide-md">Order</th>
                    <th>Store</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th class="dash-hide-md">Voucher</th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal dash-modal-wide" id="receiptModal" aria-labelledby="receiptModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'check'])</span>
            <div>
                <h3 id="receiptModalTitle" data-modal-title>Direct purchase</h3>
                <p data-modal-sub>Stock bought without an order.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-scroll">
            <div class="dash-form-grid">
                @include('dashboards.partials.select', ['name' => 'vendor_id', 'label' => 'Vendor', 'options' => $data['vendors'], 'required' => true, 'id' => 'drVendor'])
                @include('dashboards.partials.select', ['name' => 'location_id', 'label' => 'Into store', 'options' => $data['stores'], 'required' => true, 'id' => 'drStore'])
                @include('dashboards.partials.field', ['name' => 'received_on', 'label' => 'Received on', 'type' => 'date', 'required' => true, 'id' => 'drDate'])
                @include('dashboards.partials.field', ['name' => 'vendor_invoice_no', 'label' => 'Vendor invoice no', 'id' => 'drInvoice'])
            </div>
            <div class="dash-lines" data-lines="receipt">
                <div class="dash-lines-head">
                    <strong>Items</strong>
                    <input type="search" class="dash-input" placeholder="Search an item by name or code…" data-line-search aria-label="Search item">
                </div>
                <ul class="dash-holder-results" data-line-results></ul>
                <table class="dash-table dash-lines-table">
                    <thead><tr><th>Item</th><th>Qty</th><th>Unit cost (Rs)</th><th>Total</th><th></th></tr></thead>
                    <tbody data-line-rows><tr class="dash-muted" data-line-empty><td colspan="5">No items yet. Search above to add.</td></tr></tbody>
                </table>
                <p class="dash-field-error" data-error-for="items" hidden></p>
            </div>
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions" data-modal-actions>
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Receive</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-inventory.js') }}?v={{ filemtime(public_path('js/admin-inventory.js')) }}" defer></script>
@endsection
