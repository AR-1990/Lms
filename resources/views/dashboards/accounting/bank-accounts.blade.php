@extends('layouts.dashboard')

@section('title', 'Bank Accounts')
@section('page_heading', 'Accounts')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Bank Accounts</h1>
        <p>The school's bank details. The default account is printed on fee challans.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        Add Bank Account
    </button>
</section>

@include('dashboards.accounting.tabs', ['current' => 'accounts.bank-accounts'])

<section class="dash-panel" data-accounting="bank-accounts" data-datatable-url="{{ route('erp.ledger.bank-accounts.datatable') }}" data-resource-url="{{ route('erp.ledger.bank-accounts.index') }}">
    <div class="dash-panel-head">
        <div>
            <h2>All bank accounts</h2>
            <p>Each is linked to one ledger account marked as a bank account in the chart.</p>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Account title</th>
                    <th>Bank</th>
                    <th>Account no</th>
                    <th class="dash-hide-md">Ledger account</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="accountingModal" aria-labelledby="accountingModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'building'])</span>
            <div>
                <h3 id="accountingModalTitle" data-modal-title>Add bank account</h3>
                <p>Banks come from the Lookups screen (type: bank).</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>

        <div class="dash-form-grid">
            <div class="dash-field">
                <label for="bankLedger">Ledger account</label>
                <select id="bankLedger" name="account_id" required>
                    @foreach ($data['bank_ledger_accounts'] as $account)
                        <option value="{{ $account->id }}">{{ $account->code }} {{ $account->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="account_id" hidden></p>
            </div>
            <div class="dash-field">
                <label for="bankName">Bank</label>
                <select id="bankName" name="bank_id" required>
                    @foreach ($data['banks'] as $bank)
                        <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="bank_id" hidden></p>
            </div>
            <div class="dash-field dash-field-wide">
                <label for="bankTitle">Account title</label>
                <input id="bankTitle" name="account_title" required maxlength="255" autocomplete="off">
                <p class="dash-field-error" data-error-for="account_title" hidden></p>
            </div>
            <div class="dash-field">
                <label for="bankNumber">Account number</label>
                <input id="bankNumber" name="account_number" required maxlength="50" autocomplete="off">
                <p class="dash-field-error" data-error-for="account_number" hidden></p>
            </div>
            <div class="dash-field">
                <label for="bankIban">IBAN <span class="dash-optional">optional</span></label>
                <input id="bankIban" name="iban" maxlength="40" autocomplete="off" class="dash-uppercase">
                <p class="dash-field-error" data-error-for="iban" hidden></p>
            </div>
            <div class="dash-field">
                <label for="bankBranch">Branch <span class="dash-optional">optional</span></label>
                <input id="bankBranch" name="branch" maxlength="255" autocomplete="off">
                <p class="dash-field-error" data-error-for="branch" hidden></p>
            </div>
            <div class="dash-field">
                <label for="bankBranchCode">Branch code <span class="dash-optional">optional</span></label>
                <input id="bankBranchCode" name="branch_code" maxlength="20" autocomplete="off">
                <p class="dash-field-error" data-error-for="branch_code" hidden></p>
            </div>
            <label class="dash-switch dash-field-wide">
                <input type="checkbox" name="is_default_for_fees">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Print on fee challans (default)</span>
            </label>
        </div>

        <p class="dash-form-error" data-form-error hidden></p>

        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save Bank Account</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-accounting.js') }}?v={{ filemtime(public_path('js/admin-accounting.js')) }}" defer></script>
@endsection
