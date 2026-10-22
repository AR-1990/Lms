@extends('layouts.dashboard')

@section('title', 'Vendors')
@section('page_heading', 'Accounts')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Vendors</h1>
        <p>Suppliers the school buys from. Used by expenses, purchases and assets.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        Add Vendor
    </button>
</section>

@include('dashboards.accounting.tabs', ['current' => 'accounts.vendors'])

<section class="dash-panel" data-accounting="vendors" data-datatable-url="{{ route('erp.ledger.vendors.datatable') }}" data-resource-url="{{ route('erp.ledger.vendors.index') }}">
    <div class="dash-panel-head">
        <div>
            <h2>All vendors</h2>
            <p>Search by name, phone or NTN.</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">Status</span>
                <select data-filter="is_active">
                    <option value="">All statuses</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Vendor</th>
                    <th>Phone</th>
                    <th class="dash-hide-md">NTN</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="accountingModal" aria-labelledby="accountingModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'users'])</span>
            <div>
                <h3 id="accountingModalTitle" data-modal-title>Add vendor</h3>
                <p>Only the name is required.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>

        <div class="dash-form-grid">
            <div class="dash-field dash-field-wide">
                <label for="vendorName">Name</label>
                <input id="vendorName" name="name" required maxlength="255" autocomplete="off">
                <p class="dash-field-error" data-error-for="name" hidden></p>
            </div>
            <div class="dash-field">
                <label for="vendorContact">Contact person <span class="dash-optional">optional</span></label>
                <input id="vendorContact" name="contact_person" maxlength="255" autocomplete="off">
                <p class="dash-field-error" data-error-for="contact_person" hidden></p>
            </div>
            <div class="dash-field">
                <label for="vendorPhone">Phone <span class="dash-optional">optional</span></label>
                <input id="vendorPhone" name="phone" type="tel" maxlength="30" autocomplete="off">
                <p class="dash-field-error" data-error-for="phone" hidden></p>
            </div>
            <div class="dash-field">
                <label for="vendorEmail">Email <span class="dash-optional">optional</span></label>
                <input id="vendorEmail" name="email" type="email" maxlength="255" autocomplete="off">
                <p class="dash-field-error" data-error-for="email" hidden></p>
            </div>
            <div class="dash-field">
                <label for="vendorPayable">Payable account <span class="dash-optional">optional</span></label>
                <select id="vendorPayable" name="payable_account_id">
                    <option value="">Not set</option>
                    @foreach ($data['postable_accounts'] as $account)
                        <option value="{{ $account->id }}">{{ $account->code }} {{ $account->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="payable_account_id" hidden></p>
            </div>
            <div class="dash-field">
                <label for="vendorNtn">NTN <span class="dash-optional">optional</span></label>
                <input id="vendorNtn" name="ntn" maxlength="30" autocomplete="off">
                <p class="dash-field-error" data-error-for="ntn" hidden></p>
            </div>
            <div class="dash-field">
                <label for="vendorStrn">STRN <span class="dash-optional">optional</span></label>
                <input id="vendorStrn" name="strn" maxlength="30" autocomplete="off">
                <p class="dash-field-error" data-error-for="strn" hidden></p>
            </div>
            <div class="dash-field">
                <label for="vendorBank">Bank <span class="dash-optional">optional</span></label>
                <input id="vendorBank" name="bank_name" maxlength="255" autocomplete="off">
                <p class="dash-field-error" data-error-for="bank_name" hidden></p>
            </div>
            <div class="dash-field">
                <label for="vendorBankNo">Bank account no <span class="dash-optional">optional</span></label>
                <input id="vendorBankNo" name="bank_account_no" maxlength="50" autocomplete="off">
                <p class="dash-field-error" data-error-for="bank_account_no" hidden></p>
            </div>
            <div class="dash-field dash-field-wide">
                <label for="vendorAddress">Address <span class="dash-optional">optional</span></label>
                <textarea id="vendorAddress" name="address" rows="2" maxlength="1000"></textarea>
                <p class="dash-field-error" data-error-for="address" hidden></p>
            </div>
            <label class="dash-switch dash-field-wide">
                <input type="checkbox" name="is_active" checked>
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Active</span>
            </label>
        </div>

        <p class="dash-form-error" data-form-error hidden></p>

        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save Vendor</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-accounting.js') }}?v={{ filemtime(public_path('js/admin-accounting.js')) }}" defer></script>
@endsection
