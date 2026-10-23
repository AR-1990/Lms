@extends('layouts.dashboard')

@section('title', 'Purchase Orders')
@section('page_heading', 'Inventory')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Purchase Orders</h1>
        <p>Orders to vendors. Drafts can change; an approver approves; goods are received against approved orders.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>@include('partials.dashboard-icon', ['name' => 'plus']) New Order</button>
</section>

@include('dashboards.inventory.tabs', ['current' => 'admin.inventory.orders'])

<section class="dash-panel" data-inventory="orders" data-datatable-url="{{ url('/api/erp/purchase-orders/datatable') }}" data-resource-url="{{ url('/api/erp/purchase-orders') }}" data-search-url="{{ url('/api/erp/inventory/items/search') }}" data-recipients-url="{{ url('/api/erp/inventory/recipients') }}" data-can-approve="{{ auth()->user()->hasPermission('approve-requests') ? 1 : 0 }}">
    <div class="dash-panel-head">
        <div>
            <h2>Orders</h2>
            <p>Open an order to approve, receive or cancel it.</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-select"><span class="dash-visually-hidden">Status</span>
                <select data-filter="status">
                    <option value="">All orders</option>
@foreach (\App\Models\PurchaseOrder::STATUSES as $status)                    <option value="{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
@endforeach
                </select>
            </label>
            <label class="dash-select"><span class="dash-visually-hidden">Vendor</span>
                <select data-filter="vendor_id">
                    <option value="">All vendors</option>
                    @foreach ($data['vendors'] as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Vendor</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal dash-modal-wide" id="orderModal" aria-labelledby="orderModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'send'])</span>
            <div>
                <h3 id="orderModalTitle" data-modal-title>New purchase order</h3>
                <p data-modal-sub>Saved as a draft. Lines are stock items or assets (pick an asset category).</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-scroll">
            <div class="dash-form-grid">
                @include('dashboards.partials.select', ['name' => 'vendor_id', 'label' => 'Vendor', 'options' => $data['vendors'], 'required' => true, 'id' => 'poVendor'])
                @include('dashboards.partials.select', ['name' => 'location_id', 'label' => 'Deliver to', 'options' => $data['stores'], 'id' => 'poStore'])
                @include('dashboards.partials.field', ['name' => 'order_date', 'label' => 'Order date', 'type' => 'date', 'required' => true, 'id' => 'poDate'])
                @include('dashboards.partials.field', ['name' => 'expected_date', 'label' => 'Expected by', 'type' => 'date', 'id' => 'poExpected'])
                @include('dashboards.partials.select', ['name' => 'purchase_request_id', 'label' => 'For request', 'options' => $data['requests'], 'id' => 'poRequest'])
                @include('dashboards.partials.field', ['name' => 'tax', 'label' => 'Tax (Rs)', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'id' => 'poTax'])
                @include('dashboards.partials.field', ['name' => 'discount', 'label' => 'Discount (Rs)', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'id' => 'poDiscount'])
                @include('dashboards.partials.field', ['name' => 'terms', 'label' => 'Terms', 'type' => 'textarea', 'wide' => true, 'id' => 'poTerms'])
            </div>
            <div class="dash-form-grid">
                <div class="dash-field">
                    <label for="poAssetLine">Add an asset line</label>
                    <select id="poAssetLine" data-asset-line>
                        <option value="">Choose an asset category…</option>
                        @foreach ($data['asset_categories'] as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="dash-lines" data-lines="order">
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
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save Draft</button>
        </div>
    </form>
</dialog>

<dialog class="dash-modal dash-modal-wide" id="orderView" aria-labelledby="orderViewTitle">
    <div class="dash-modal-head">
        <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'send'])</span>
        <div><h3 id="orderViewTitle" data-view-title>Order</h3><p data-view-sub></p></div>
        <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
    </div>
    <div class="dash-form-scroll" data-view-body></div>
    <div class="dash-modal-actions" data-view-actions></div>
</dialog>

<dialog class="dash-modal dash-form-modal dash-modal-wide" id="receiveModal" aria-labelledby="receiveModalTitle">
    <form data-receive-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'check'])</span>
            <div>
                <h3 id="receiveModalTitle" data-modal-title>Receive goods</h3>
                <p data-modal-sub></p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-scroll">
            <div class="dash-form-grid">
                @include('dashboards.partials.select', ['name' => 'location_id', 'label' => 'Into store', 'options' => $data['stores'], 'required' => true, 'id' => 'grnStore'])
                @include('dashboards.partials.field', ['name' => 'received_on', 'label' => 'Received on', 'type' => 'date', 'required' => true, 'id' => 'grnDate'])
                @include('dashboards.partials.field', ['name' => 'vendor_invoice_no', 'label' => 'Vendor invoice no', 'id' => 'grnInvoice'])
                @include('dashboards.partials.field', ['name' => 'vendor_invoice_date', 'label' => 'Invoice date', 'type' => 'date', 'id' => 'grnInvoiceDate'])
            </div>
            <table class="dash-table dash-lines-table"><thead><tr><th>Item</th><th>Ordered</th><th>Received before</th><th>Receive now</th></tr></thead><tbody data-receive-rows></tbody></table>
            <p class="dash-field-error" data-error-for="items" hidden></p>
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
