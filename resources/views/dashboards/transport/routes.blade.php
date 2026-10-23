@extends('layouts.dashboard')

@section('title', 'Routes & Stops')
@section('page_heading', 'Transport')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Routes & Stops</h1>
        <p>Each route has a vehicle, a driver and an attendant, its stops in pickup order, and a monthly fee (a stop may charge its own).</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>@include('partials.dashboard-icon', ['name' => 'plus']) Add Route</button>
</section>

@include('dashboards.transport.tabs', ['current' => 'admin.transport.routes'])

<section class="dash-panel" data-transport="routes" data-datatable-url="{{ url('/api/erp/transport-routes/datatable') }}" data-resource-url="{{ url('/api/erp/transport-routes') }}" data-stops-url="{{ url('/api/erp/transport-stops') }}" data-students-url="{{ url('/api/erp/transport-students') }}" data-sheet-url="{{ url('/api/erp/transport-routes') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Routes</h2>
            <p>Riders over the vehicle capacity are refused. Open a route to print its sheet.</p>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Route</th>
                    <th>Vehicle</th>
                    <th>Driver</th>
                    <th>Stops</th>
                    <th>Riders / seats</th>
                    <th>Fee</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal dash-modal-wide" id="routeModal" aria-labelledby="routeModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'map'])</span>
            <div>
                <h3 id="routeModalTitle" data-modal-title>Add route</h3>
                <p>Stops keep the order you list them in.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-scroll">
            <div class="dash-form-grid">
            @include('dashboards.partials.field', ['name' => 'name', 'label' => 'Name', 'required' => true, 'id' => 'rtName'])
            @include('dashboards.partials.field', ['name' => 'code', 'label' => 'Code', 'id' => 'rtCode'])
            @include('dashboards.partials.select', ['name' => 'vehicle_id', 'label' => 'Vehicle', 'options' => $data['vehicles'], 'id' => 'rtVehicle'])
            @include('dashboards.partials.field', ['name' => 'monthly_fee', 'label' => 'Monthly fee (Rs)', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'id' => 'rtFee'])
            @include('dashboards.partials.select', ['name' => 'driver_employee_id', 'label' => 'Driver', 'options' => $data['staff'], 'id' => 'rtDriver'])
            @include('dashboards.partials.select', ['name' => 'attendant_employee_id', 'label' => 'Attendant', 'options' => $data['staff'], 'id' => 'rtAttendant'])
            @include('dashboards.partials.select', ['name' => 'fee_head_id', 'label' => 'Fee head on the challan', 'options' => $data['fee_heads'], 'empty' => 'Transport Fee', 'id' => 'rtHead'])
            <label class="dash-switch">
                <input type="checkbox" name="is_active" checked>
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Active</span>
            </label>
            </div>
            <div class="dash-lines">
                <div class="dash-lines-head">
                    <strong>Stops</strong>
                    <button type="button" class="dash-btn dash-btn-ghost dash-btn-sm" data-add-stop>@include('partials.dashboard-icon', ['name' => 'plus']) Add stop</button>
                </div>
                <table class="dash-table dash-lines-table">
                    <thead><tr><th>Stop</th><th>Area</th><th>Pickup</th><th>Drop</th><th>Own fee (Rs)</th><th></th></tr></thead>
                    <tbody data-stop-rows></tbody>
                </table>
                <template data-area-options><option value="">—</option>@foreach ($data['areas'] as $area)<option value="{{ $area->id }}">{{ $area->name }}</option>@endforeach</template>
                <p class="dash-field-error" data-error-for="stops" hidden></p>
            </div>
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save Route</button>
        </div>
    </form>
</dialog>

<dialog class="dash-modal dash-modal-wide" id="routeView" aria-labelledby="routeViewTitle">
    <div class="dash-modal-head">
        <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'map'])</span>
        <div><h3 id="routeViewTitle" data-view-title>Route</h3><p data-view-sub></p></div>
        <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
    </div>
    <div class="dash-form-scroll" data-view-body></div>
    <div class="dash-modal-actions">
        <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Close</button>
        <a class="dash-btn dash-btn-primary" data-view-sheet target="_blank" rel="noopener">@include('partials.dashboard-icon', ['name' => 'book']) Print route sheet</a>
    </div>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-transport.js') }}?v={{ filemtime(public_path('js/admin-transport.js')) }}" defer></script>
@endsection
