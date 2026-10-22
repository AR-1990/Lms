@extends('layouts.dashboard')

@section('title', 'Concessions')
@section('page_heading', 'Fees')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Concessions</h1>
        <p>Approved concessions are applied automatically to challans raised in their validity period.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        Grant Concession
    </button>
</section>

@include('dashboards.fees.tabs', ['current' => 'accounts.fees.concessions'])

<section class="dash-panel" data-fees="concessions" data-datatable-url="{{ route('erp.fees.concessions.datatable') }}" data-resource-url="{{ url('/api/erp/fees/concessions') }}" data-students-url="{{ route('erp.fees.students.index') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Student concessions</h2>
            <p>Open a pending concession to approve or reject it.</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">Status</span>
                <select data-filter="status">
                    <option value="">All statuses</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Concession</th>
                    <th>Value</th>
                    <th class="dash-hide-md">Valid</th>
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
                <h3 id="feesModalTitle" data-modal-title>Grant concession</h3>
                <p>Leave the value empty to use the type's default.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            @include('dashboards.fees.student-picker', ['id' => 'concessionStudent'])
            @include('dashboards.partials.select', ['name' => 'concession_type_id', 'label' => 'Type', 'options' => $data['concession_types'], 'required' => true])
            @include('dashboards.partials.select', ['name' => 'fee_head_id', 'label' => 'Fee head', 'options' => $data['fee_heads'], 'empty' => 'All eligible heads'])
            <div class="dash-field">
                <label for="concessionCalculation">Calculation <span class="dash-optional">optional</span></label>
                <select id="concessionCalculation" name="calculation">
                    <option value="">As the type</option>
                    <option value="percent">Percent</option>
                    <option value="fixed">Fixed amount</option>
                </select>
                <p class="dash-field-error" data-error-for="calculation" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'value', 'label' => 'Value (% or Rs)', 'type' => 'number', 'step' => '0.01', 'min' => 0])
            @include('dashboards.partials.field', ['name' => 'valid_from', 'label' => 'Valid from', 'type' => 'date', 'required' => true])
            @include('dashboards.partials.field', ['name' => 'valid_to', 'label' => 'Valid to', 'type' => 'date'])
            @include('dashboards.partials.field', ['name' => 'reason', 'label' => 'Reason', 'type' => 'textarea', 'wide' => true])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Grant</button>
        </div>
    </form>
</dialog>

<dialog class="dash-modal dash-form-modal" id="decisionModal" aria-labelledby="decisionTitle">
    <div class="dash-modal-head">
        <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'heart'])</span>
        <div>
            <h3 id="decisionTitle" data-detail-title>Concession</h3>
            <p data-detail-subtitle></p>
        </div>
        <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
    </div>
    <div class="dash-detail-body" data-detail-body></div>
    <div class="dash-modal-actions" data-decision-actions>
        <button type="button" class="dash-btn dash-btn-ghost" data-decision="rejected">Reject</button>
        <button type="button" class="dash-btn dash-btn-primary" data-decision="approved">@include('partials.dashboard-icon', ['name' => 'check']) Approve</button>
    </div>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-fees.js') }}?v={{ filemtime(public_path('js/admin-fees.js')) }}" defer></script>
@endsection
