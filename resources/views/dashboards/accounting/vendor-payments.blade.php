@extends('layouts.dashboard')

@section('title', 'Vendor Payments')
@section('page_heading', 'Accounts')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Vendor Payments</h1>
        <p>Pay vendors for goods received on credit. The amount is checked against what they are owed.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>@include('partials.dashboard-icon', ['name' => 'plus']) Pay Vendor</button>
</section>

@include('dashboards.accounting.tabs', ['current' => 'accounts.vendor-payments'])

<section class="dash-panel" data-expenses="vendor-payments" data-datatable-url="{{ url('/api/erp/vendor-payments/datatable') }}" data-resource-url="{{ url('/api/erp/vendor-payments') }}" data-attachments-url="{{ url('/api/erp/attachments') }}" data-can-approve="{{ auth()->user()->hasPermission('approve-requests') ? 1 : 0 }}">
    <div class="dash-panel-head">
        <div>
            <h2>Payments</h2>
            <p>Reversing a payment needs an approver and a reason.</p>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Payment</th>
                    <th>Vendor</th>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Paid by</th>
                    <th>Status</th>
                    <th class="dash-hide-md">Voucher</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<section class="dash-panel" data-vendor-balances data-url="{{ url('/api/erp/vendor-balances/datatable') }}">
    <div class="dash-panel-head">
        <div>
            <h2>What we owe</h2>
            <p>Balance per vendor from goods received, bills and payments.</p>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-balance-grid>
            <thead><tr><th>Vendor</th><th>Phone</th><th>Owed</th></tr></thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="vendorPayModal" aria-labelledby="vendorPayModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'send'])</span>
            <div>
                <h3 id="vendorPayModalTitle" data-modal-title>Pay vendor</h3>
                <p data-modal-sub></p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            @include('dashboards.partials.select', ['name' => 'vendor_id', 'label' => 'Vendor', 'options' => $data['vendors'], 'required' => true, 'id' => 'vpVendor'])
            <p class="dash-muted dash-field-wide" data-owed></p>
            @include('dashboards.partials.field', ['name' => 'paid_on', 'label' => 'Paid on', 'type' => 'date', 'required' => true, 'id' => 'vpOn'])
            @include('dashboards.partials.field', ['name' => 'amount', 'label' => 'Amount (Rs)', 'type' => 'number', 'step' => '0.01', 'min' => '0.01', 'required' => true, 'id' => 'vpAmount'])
            @include('dashboards.partials.select', ['name' => 'payment_method_id', 'label' => 'Paid by', 'options' => $data['payment_methods'], 'required' => true, 'id' => 'vpMethod'])
            @include('dashboards.partials.field', ['name' => 'reference_no', 'label' => 'Cheque / transfer ref', 'id' => 'vpRef'])
            @include('dashboards.partials.field', ['name' => 'remarks', 'label' => 'Remarks', 'type' => 'textarea', 'wide' => true, 'id' => 'vpRemarks'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Pay</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-expenses.js') }}?v={{ filemtime(public_path('js/admin-expenses.js')) }}" defer></script>
@endsection
