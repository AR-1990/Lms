@extends('layouts.dashboard')

@section('title', 'Chart of Accounts')
@section('page_heading', 'Accounts')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
@php
    $canManage = $user->hasPermission('manage-accounts');
@endphp

<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Chart of Accounts</h1>
        <p>Every rupee is posted to one of these accounts. Groups are headings; only normal accounts take entries.</p>
    </div>
    @if ($canManage)
        <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
            @include('partials.dashboard-icon', ['name' => 'plus'])
            Add Account
        </button>
    @endif
</section>

@include('dashboards.accounting.tabs', ['current' => 'accounts.chart'])

<section
    class="dash-panel"
    data-accounting="chart"
    data-can-manage="{{ $canManage ? '1' : '0' }}"
    data-datatable-url="{{ route('erp.ledger.accounts.datatable') }}"
    data-resource-url="{{ route('erp.ledger.accounts.index') }}"
    data-ledger-url="{{ url('/accounts/chart') }}"
>
    <div class="dash-panel-head">
        <div>
            <h2>Accounts</h2>
            <p>Balances include opening balances and posted vouchers. Open the book icon for the full ledger.</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">Type</span>
                <select data-filter="account_type_id">
                    <option value="">All types</option>
                    @foreach ($data['account_types'] as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </select>
            </label>
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
                    <th>Code</th>
                    <th>Account</th>
                    <th class="dash-hide-md">Type</th>
                    <th>Balance</th>
                    <th class="dash-hide-md">Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="accountingModal" aria-labelledby="accountingModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'layers'])</span>
            <div>
                <h3 id="accountingModalTitle" data-modal-title>Add account</h3>
                <p>Place it under a group of the same type.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>

        <div class="dash-form-grid">
            <div class="dash-field">
                <label for="accountType">Type</label>
                <select id="accountType" name="account_type_id" required>
                    @foreach ($data['account_types'] as $type)
                        <option value="{{ $type->id }}">{{ $type->name }} ({{ $type->normal_balance }})</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="account_type_id" hidden></p>
            </div>
            <div class="dash-field">
                <label for="accountParent">Under group <span class="dash-optional">optional</span></label>
                <select id="accountParent" name="parent_id">
                    <option value="">Top level</option>
                    @foreach ($data['group_accounts'] as $group)
                        <option value="{{ $group->id }}" data-type="{{ $group->account_type_id }}">{{ $group->code }} {{ $group->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="parent_id" hidden></p>
            </div>
            <div class="dash-field">
                <label for="accountCode">Code</label>
                <input id="accountCode" name="code" required maxlength="20" autocomplete="off" class="dash-uppercase" placeholder="5250">
                <p class="dash-field-error" data-error-for="code" hidden></p>
            </div>
            <div class="dash-field">
                <label for="accountName">Name</label>
                <input id="accountName" name="name" required maxlength="255" autocomplete="off" placeholder="Internet Charges">
                <p class="dash-field-error" data-error-for="name" hidden></p>
            </div>
            <div class="dash-field">
                <label for="accountOpening">Opening balance (Rs) <span class="dash-optional">optional</span></label>
                <input id="accountOpening" name="opening_balance" type="number" min="0" step="0.01" inputmode="decimal">
                <p class="dash-field-error" data-error-for="opening_balance" hidden></p>
            </div>
            <div class="dash-field">
                <label for="accountOpeningType">Opening side</label>
                <select id="accountOpeningType" name="opening_balance_type">
                    <option value="">—</option>
                    <option value="debit">Debit</option>
                    <option value="credit">Credit</option>
                </select>
                <p class="dash-field-error" data-error-for="opening_balance_type" hidden></p>
            </div>
            <div class="dash-field dash-field-wide">
                <label for="accountDescription">Description <span class="dash-optional">optional</span></label>
                <input id="accountDescription" name="description" maxlength="1000" autocomplete="off">
                <p class="dash-field-error" data-error-for="description" hidden></p>
            </div>
            <label class="dash-switch">
                <input type="checkbox" name="is_group">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Group heading</span>
            </label>
            <label class="dash-switch">
                <input type="checkbox" name="is_active" checked>
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Active</span>
            </label>
            <label class="dash-switch">
                <input type="checkbox" name="is_cash_account">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Cash account</span>
            </label>
            <label class="dash-switch">
                <input type="checkbox" name="is_bank_account">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Bank account</span>
            </label>
            <p class="dash-field-error dash-field-wide" data-error-for="is_group" hidden></p>
            <p class="dash-field-error dash-field-wide" data-error-for="account" hidden></p>
        </div>

        <p class="dash-form-error" data-form-error hidden></p>

        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save Account</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-accounting.js') }}?v={{ filemtime(public_path('js/admin-accounting.js')) }}" defer></script>
@endsection
