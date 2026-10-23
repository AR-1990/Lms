@extends('layouts.dashboard')

@section('title', 'Locations')
@section('page_heading', 'Assets')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Locations</h1>
        <p>Rooms, labs, offices and stores. Stores also hold inventory stock.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>@include('partials.dashboard-icon', ['name' => 'plus']) Add Location</button>
</section>

@include('dashboards.assets.tabs', ['current' => 'admin.assets.locations'])

<section class="dash-panel" data-assets="locations" data-datatable-url="{{ url('/api/erp/locations/datatable') }}" data-resource-url="{{ url('/api/erp/locations') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Locations</h2>
            <p>The person in charge answers for what is in the room.</p>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Location</th>
                    <th class="dash-hide-md">Building</th>
                    <th>In charge</th>
                    <th>Assets</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="locationModal" aria-labelledby="locationModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'building'])</span>
            <div>
                <h3 id="locationModalTitle" data-modal-title>Add location</h3>
                <p data-action-asset></p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            @include('dashboards.partials.field', ['name' => 'name', 'label' => 'Name', 'required' => true, 'id' => 'locName'])
            @include('dashboards.partials.field', ['name' => 'code', 'label' => 'Code', 'id' => 'locCode'])
            @include('dashboards.partials.field', ['name' => 'building', 'label' => 'Building', 'id' => 'locBuilding'])
            @include('dashboards.partials.field', ['name' => 'floor', 'label' => 'Floor', 'id' => 'locFloor'])
            @include('dashboards.partials.select', ['name' => 'parent_id', 'label' => 'Inside', 'options' => $data['locations'], 'id' => 'locParent'])
            @include('dashboards.partials.select', ['name' => 'in_charge_employee_id', 'label' => 'In charge', 'options' => $data['staff'], 'id' => 'locInCharge'])
            <label class="dash-switch">
                <input type="checkbox" name="is_store">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>This is a store (holds stock)</span>
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
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save Location</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-assets.js') }}?v={{ filemtime(public_path('js/admin-assets.js')) }}" defer></script>
@endsection
