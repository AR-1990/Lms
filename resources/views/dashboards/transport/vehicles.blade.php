@extends('layouts.dashboard')

@section('title', 'Vehicles')
@section('page_heading', 'Transport')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Vehicles</h1>
        <p>Buses and vans with their papers. Managers get a reminder 30 and 7 days before insurance, fitness or permit expire.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>@include('partials.dashboard-icon', ['name' => 'plus']) Add Vehicle</button>
</section>

@include('dashboards.transport.tabs', ['current' => 'admin.transport.vehicles'])

<section class="dash-panel" data-transport="vehicles" data-datatable-url="{{ url('/api/erp/vehicles/datatable') }}" data-resource-url="{{ url('/api/erp/vehicles') }}" data-stops-url="{{ url('/api/erp/transport-stops') }}" data-students-url="{{ url('/api/erp/transport-students') }}" data-sheet-url="{{ url('/api/erp/transport-routes') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Vehicles</h2>
            <p>Red badges mean papers expire within 30 days (or have expired).</p>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Vehicle</th>
                    <th>Type</th>
                    <th>Seats</th>
                    <th>Papers</th>
                    <th>Routes</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="vehicleModal" aria-labelledby="vehicleModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'server'])</span>
            <div>
                <h3 id="vehicleModalTitle" data-modal-title>Add vehicle</h3>
                <p>Upload the papers under the linked asset or in the documents section.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-scroll">
            <div class="dash-form-grid">
            @include('dashboards.partials.field', ['name' => 'registration_no', 'label' => 'Registration no', 'required' => true, 'id' => 'vhReg'])
            @include('dashboards.partials.select', ['name' => 'type_id', 'label' => 'Type', 'options' => $data['types'], 'id' => 'vhType'])
            @include('dashboards.partials.field', ['name' => 'make_model', 'label' => 'Make / model', 'id' => 'vhModel'])
            @include('dashboards.partials.field', ['name' => 'capacity', 'label' => 'Seats', 'type' => 'number', 'min' => '0', 'id' => 'vhSeats'])
            @include('dashboards.partials.field', ['name' => 'insurance_expiry', 'label' => 'Insurance expires', 'type' => 'date', 'id' => 'vhIns'])
            @include('dashboards.partials.field', ['name' => 'fitness_expiry', 'label' => 'Fitness expires', 'type' => 'date', 'id' => 'vhFit'])
            @include('dashboards.partials.field', ['name' => 'route_permit_expiry', 'label' => 'Route permit expires', 'type' => 'date', 'id' => 'vhPermit'])
            <label class="dash-switch">
                <input type="checkbox" name="is_active" checked>
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Active</span>
            </label>
            </div>
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save Vehicle</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-transport.js') }}?v={{ filemtime(public_path('js/admin-transport.js')) }}" defer></script>
@endsection
