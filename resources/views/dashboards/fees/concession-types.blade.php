@extends('layouts.dashboard')

@section('title', 'Concession Types')
@section('page_heading', 'Fees')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Concession Types</h1>
        <p>Sibling, staff child, merit… The default value is offered when a concession is granted.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        Add Type
    </button>
</section>

@include('dashboards.fees.tabs', ['current' => 'accounts.fees.concession-types'])

<section class="dash-panel" data-fees="concession-types" data-datatable-url="{{ route('erp.fees.concession-types.datatable') }}" data-resource-url="{{ route('erp.fees.concession-types.index') }}">
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Default</th>
                    <th class="dash-hide-md">Expense account</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="feesModal" aria-labelledby="feesModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'heart'])</span>
            <div>
                <h3 id="feesModalTitle" data-modal-title>Add concession type</h3>
                <p>Percent off each eligible line, or a fixed rupee amount.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            @include('dashboards.partials.field', ['name' => 'name', 'label' => 'Name', 'required' => true, 'wide' => true])
            <div class="dash-field">
                <label for="typeCalculation">Calculation</label>
                <select id="typeCalculation" name="calculation">
                    <option value="percent">Percent</option>
                    <option value="fixed">Fixed amount</option>
                </select>
                <p class="dash-field-error" data-error-for="calculation" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'default_value', 'label' => 'Default (% or Rs)', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'required' => true])
            @include('dashboards.partials.select', ['name' => 'expense_account_id', 'label' => 'Expense account', 'options' => $data['expense_accounts'], 'withCode' => true, 'wide' => true, 'empty' => 'Default (5400 Fee Concessions)'])
            <label class="dash-switch">
                <input type="checkbox" name="requires_approval" checked>
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Needs approval</span>
            </label>
            <label class="dash-switch">
                <input type="checkbox" name="is_active" checked>
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Active</span>
            </label>
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-fees.js') }}?v={{ filemtime(public_path('js/admin-fees.js')) }}" defer></script>
@endsection
