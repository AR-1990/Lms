@extends('layouts.dashboard')

@section('title', 'Voucher Types')
@section('page_heading', 'Accounts')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Voucher Types</h1>
        <p>Each type numbers its vouchers separately, e.g. JV-2026-00001, BRV-2026-00001.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        Add Voucher Type
    </button>
</section>

@include('dashboards.accounting.tabs', ['current' => 'accounts.voucher-types'])

<section class="dash-panel" data-accounting="voucher-types" data-datatable-url="{{ route('erp.ledger.voucher-types.datatable') }}" data-resource-url="{{ url('/api/erp/ledger/voucher-types') }}">
    <div class="dash-panel-head">
        <div>
            <h2>All voucher types</h2>
            <p>The number format of each type is set on the Numbering screen.</p>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th class="dash-hide-md">Vouchers</th>
                    <th>Type</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="accountingModal" aria-labelledby="accountingModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'hash'])</span>
            <div>
                <h3 id="accountingModalTitle" data-modal-title>Add voucher type</h3>
                <p>The code cannot change later; it starts every voucher number.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>

        <div class="dash-form-grid">
            <div class="dash-field">
                <label for="typeName">Name</label>
                <input id="typeName" name="name" required maxlength="100" autocomplete="off" placeholder="Bank Receipt">
                <p class="dash-field-error" data-error-for="name" hidden></p>
            </div>
            <div class="dash-field">
                <label for="typeCode">Code</label>
                <input id="typeCode" name="code" maxlength="10" autocomplete="off" class="dash-uppercase" placeholder="BRV">
                <p class="dash-field-error" data-error-for="code" hidden></p>
            </div>
        </div>

        <p class="dash-form-error" data-form-error hidden></p>

        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save Type</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-accounting.js') }}?v={{ filemtime(public_path('js/admin-accounting.js')) }}" defer></script>
@endsection
