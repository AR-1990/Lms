@extends('layouts.dashboard')

@section('title', 'Bank Reconciliation')
@section('page_heading', 'Accounts')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Bank Reconciliation</h1>
        <p>Enter the statement date and closing balance, tick the lines the bank shows, and complete when the difference is zero.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>@include('partials.dashboard-icon', ['name' => 'plus']) New Reconciliation</button>
</section>

@include('dashboards.accounting.tabs', ['current' => 'accounts.bank-reconciliation'])

<section class="dash-panel" data-expenses="bank-reconciliation" data-datatable-url="{{ url('/api/erp/bank-reconciliations/datatable') }}" data-resource-url="{{ url('/api/erp/bank-reconciliations') }}" data-attachments-url="{{ url('/api/erp/attachments') }}" data-can-approve="{{ auth()->user()->hasPermission('approve-requests') ? 1 : 0 }}">
    <div class="dash-panel-head">
        <div>
            <h2>Reconciliations</h2>
            <p>Open a draft to continue ticking.</p>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Bank</th>
                    <th>Statement date</th>
                    <th>Statement balance</th>
                    <th>Book balance</th>
                    <th>Status</th>
                    <th class="dash-hide-md">By</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="reconModal" aria-labelledby="reconModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'check'])</span>
            <div>
                <h3 id="reconModalTitle" data-modal-title>New reconciliation</h3>
                <p data-modal-sub>Every posted line up to this date that is not yet cleared will be listed.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            @include('dashboards.partials.select', ['name' => 'bank_account_id', 'label' => 'Bank account', 'options' => $data['bank_accounts'], 'required' => true, 'id' => 'brBank'])
            @include('dashboards.partials.field', ['name' => 'statement_date', 'label' => 'Statement date', 'type' => 'date', 'required' => true, 'id' => 'brDate'])
            @include('dashboards.partials.field', ['name' => 'statement_balance', 'label' => 'Statement closing balance (Rs)', 'type' => 'number', 'step' => '0.01', 'required' => true, 'id' => 'brBalance'])
            @include('dashboards.partials.field', ['name' => 'remarks', 'label' => 'Remarks', 'id' => 'brRemarks'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Start</button>
        </div>
    </form>
</dialog>

<dialog class="dash-modal dash-modal-wide" id="reconSheet" aria-labelledby="reconSheetTitle">
    <div class="dash-modal-head">
        <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'check'])</span>
        <div><h3 id="reconSheetTitle" data-sheet-title>Reconciliation</h3><p data-sheet-sub></p></div>
        <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
    </div>
    <div class="dash-form-scroll">
        <dl class="dash-asset-facts" data-sheet-totals></dl>
        <div class="dash-table-wrap">
            <table class="dash-table">
                <thead><tr><th><span class="dash-visually-hidden">Cleared</span></th><th>Date</th><th>Voucher</th><th>Details</th><th>In (deposit)</th><th>Out (payment)</th></tr></thead>
                <tbody data-sheet-rows></tbody>
            </table>
        </div>
    </div>
    <div class="dash-modal-actions" data-sheet-actions>
        <button type="button" class="dash-btn dash-btn-ghost" data-sheet-save>Save ticks</button>
        <button type="button" class="dash-btn dash-btn-primary" data-sheet-complete>@include('partials.dashboard-icon', ['name' => 'check']) Complete</button>
    </div>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-expenses.js') }}?v={{ filemtime(public_path('js/admin-expenses.js')) }}" defer></script>
@endsection
