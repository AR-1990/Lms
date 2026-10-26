@extends('layouts.dashboard')

@section('title', 'Payment Methods')
@section('page_heading', 'Accounts')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Payment Methods</h1>
        <p>How fees and bills are paid — Cash, bank deposit, JazzCash… Each one posts to its own cash or bank account.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        Add Payment Method
    </button>
</section>

@include('dashboards.accounting.tabs', ['current' => 'accounts.payment-methods'])

<section class="dash-panel" data-accounting="payment-methods" data-datatable-url="{{ route('erp.ledger.payment-methods.datatable') }}" data-resource-url="{{ url('/api/erp/ledger/payment-methods') }}">
    <div class="dash-panel-head">
        <div>
            <h2>All payment methods</h2>
            <p>Order decides the sequence on receipt forms.</p>
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
                    <th>Order</th>
                    <th>Method</th>
                    <th class="dash-hide-md">Posts to</th>
                    <th>Needs</th>
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
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'key'])</span>
            <div>
                <h3 id="accountingModalTitle" data-modal-title>Add payment method</h3>
                <p>Money received this way is posted to the selected account.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>

        <div class="dash-form-grid">
            <div class="dash-field">
                <label for="methodName">Name</label>
                <input id="methodName" name="name" required maxlength="100" autocomplete="off" placeholder="Raast">
                <p class="dash-field-error" data-error-for="name" hidden></p>
            </div>
            <div class="dash-field">
                <label for="methodCode">Code</label>
                <input id="methodCode" name="code" required maxlength="20" autocomplete="off" class="dash-uppercase" placeholder="RAAST">
                <p class="dash-field-error" data-error-for="code" hidden></p>
            </div>
            <div class="dash-field dash-field-wide">
                <label for="methodAccount">Posts to account</label>
                <select id="methodAccount" name="account_id" required>
                    @foreach ($data['postable_accounts']->filter(fn ($account) => $account->is_cash_account || $account->is_bank_account) as $account)
                        <option value="{{ $account->id }}">{{ $account->code }} {{ $account->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="account_id" hidden></p>
            </div>
            <div class="dash-field">
                <label for="methodCharges">Charges % <span class="dash-optional">optional</span></label>
                <input id="methodCharges" name="charges_percent" type="number" min="0" max="100" step="0.01">
                <p class="dash-field-error" data-error-for="charges_percent" hidden></p>
            </div>
            <div class="dash-field">
                <label for="methodOrder">Order <span class="dash-optional">optional</span></label>
                <input id="methodOrder" name="sort_order" type="number" min="0" max="32000">
                <p class="dash-field-error" data-error-for="sort_order" hidden></p>
            </div>
            <label class="dash-switch">
                <input type="checkbox" name="requires_reference">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Needs transaction / cheque no</span>
            </label>
            <label class="dash-switch">
                <input type="checkbox" name="requires_bank_details">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Needs bank &amp; cheque date</span>
            </label>
            <label class="dash-switch">
                <input type="checkbox" name="is_online">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Online / mobile wallet</span>
            </label>
            <div class="dash-field dash-field-wide">
                <label for="f_payment_instructions">How families pay to this <span class="dash-optional">optional</span></label>
                <textarea id="f_payment_instructions" name="payment_instructions" rows="2" maxlength="1000" placeholder="JazzCash 0300 1234567 — Pinnacle International Academy"></textarea>
                <p class="dash-field-hint">Shown to parents with the screenshot upload. Fill it to let families pay online with this method.</p>
                <p class="dash-field-error" data-error-for="payment_instructions" hidden></p>
            </div>
            <label class="dash-switch">
                <input type="checkbox" name="is_active" checked>
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Active</span>
            </label>
        </div>

        <p class="dash-form-error" data-form-error hidden></p>

        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save Method</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-accounting.js') }}?v={{ filemtime(public_path('js/admin-accounting.js')) }}" defer></script>
@endsection
