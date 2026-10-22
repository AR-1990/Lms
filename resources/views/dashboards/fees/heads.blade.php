@extends('layouts.dashboard')

@section('title', 'Fee Heads')
@section('page_heading', 'Fees')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Fee Heads</h1>
        <p>Tuition, admission, lab… Each head posts to its own income account.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        Add Fee Head
    </button>
</section>

@include('dashboards.fees.tabs', ['current' => 'accounts.fees.heads'])

<section class="dash-panel" data-fees="heads" data-datatable-url="{{ route('erp.fees.heads.datatable') }}" data-resource-url="{{ route('erp.fees.heads.index') }}">
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Fee head</th>
                    <th class="dash-hide-md">Income account</th>
                    <th>Frequency</th>
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
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'book'])</span>
            <div>
                <h3 id="feesModalTitle" data-modal-title>Add fee head</h3>
                <p>A head used on a challan can be made inactive but not deleted.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            @include('dashboards.partials.field', ['name' => 'name', 'label' => 'Name', 'required' => true])
            @include('dashboards.partials.field', ['name' => 'code', 'label' => 'Code', 'placeholder' => 'TUI'])
            @include('dashboards.partials.select', ['name' => 'income_account_id', 'label' => 'Income account', 'options' => $data['income_accounts'], 'required' => true, 'withCode' => true, 'wide' => true])
            @include('dashboards.partials.select', ['name' => 'frequency_id', 'label' => 'Frequency', 'options' => $data['frequencies'], 'id' => 'headFrequency'])
            @include('dashboards.partials.field', ['name' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'min' => 0])
            <label class="dash-switch">
                <input type="checkbox" name="is_concession_allowed" checked>
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Concession allowed</span>
            </label>
            <label class="dash-switch">
                <input type="checkbox" name="is_refundable">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Refundable</span>
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
