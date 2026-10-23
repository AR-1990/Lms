@extends('layouts.dashboard')

@section('title', 'Repairs')
@section('page_heading', 'Assets')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Repairs</h1>
        <p>Repair and service jobs. Report a repair from the asset register; complete it here to bring the asset back and post the cost.</p>
    </div>
</section>

@include('dashboards.assets.tabs', ['current' => 'admin.assets.repairs'])

<section class="dash-panel" data-assets="repairs" data-datatable-url="{{ url('/api/erp/asset-maintenances/datatable') }}" data-resource-url="{{ url('/api/erp/asset-maintenances') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Repair jobs</h2>
            <p>An asset stays "in repair" until its job is completed.</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-select"><span class="dash-visually-hidden">Status</span>
                <select data-filter="status">
                    <option value="">All jobs</option>
                    <option value="reported">Reported</option>
                    <option value="in_progress">In progress</option>
                    <option value="completed">Completed</option>
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Asset</th>
                    <th>Job</th>
                    <th class="dash-hide-md">Repairer</th>
                    <th>Reported</th>
                    <th>Cost</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="repairModal" aria-labelledby="repairModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'settings'])</span>
            <div>
                <h3 id="repairModalTitle" data-modal-title>Edit repair</h3>
                <p data-action-asset></p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            @include('dashboards.partials.select', ['name' => 'maintenance_type_id', 'label' => 'Type', 'options' => $data['maintenance_types'], 'id' => 'jobType'])
            @include('dashboards.partials.select', ['name' => 'vendor_id', 'label' => 'Repairer', 'options' => $data['vendors'], 'id' => 'jobVendor'])
            @include('dashboards.partials.field', ['name' => 'reported_on', 'label' => 'Reported on', 'type' => 'date', 'required' => true, 'id' => 'jobReported'])
            @include('dashboards.partials.field', ['name' => 'sent_on', 'label' => 'Sent on', 'type' => 'date', 'id' => 'jobSent'])
            @include('dashboards.partials.field', ['name' => 'description', 'label' => 'Problem', 'type' => 'textarea', 'wide' => true, 'id' => 'jobProblem'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save</button>
        </div>
    </form>
</dialog>

<dialog class="dash-modal dash-form-modal" id="completeModal" aria-labelledby="completeModalTitle">
    <form data-action-form="complete" novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'check'])</span>
            <div>
                <h3 id="completeModalTitle" data-modal-title>Complete repair</h3>
                <p data-action-asset></p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            @include('dashboards.partials.field', ['name' => 'completed_on', 'label' => 'Completed on', 'type' => 'date', 'required' => true, 'id' => 'doneOn'])
            @include('dashboards.partials.field', ['name' => 'cost', 'label' => 'Cost (Rs)', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'id' => 'doneCost'])
            @include('dashboards.partials.select', ['name' => 'vendor_id', 'label' => 'Repairer', 'options' => $data['vendors'], 'id' => 'doneVendor'])
            @include('dashboards.partials.select', ['name' => 'payment_method_id', 'label' => 'Paid by', 'options' => $data['payment_methods'], 'empty' => 'On credit (vendor payable)', 'id' => 'donePaidBy'])
            @include('dashboards.partials.select', ['name' => 'condition_id', 'label' => 'Condition now', 'options' => $data['conditions'], 'id' => 'doneCondition'])
            @include('dashboards.partials.field', ['name' => 'description', 'label' => 'Work done', 'type' => 'textarea', 'wide' => true, 'id' => 'doneWork'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Complete</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-assets.js') }}?v={{ filemtime(public_path('js/admin-assets.js')) }}" defer></script>
@endsection
