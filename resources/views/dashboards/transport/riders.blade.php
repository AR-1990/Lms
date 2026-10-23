@extends('layouts.dashboard')

@section('title', 'Riders')
@section('page_heading', 'Transport')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Riders</h1>
        <p>Which student rides from which stop. Active riders get the transport fee on their monthly challan; leaving school ends the ride.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>@include('partials.dashboard-icon', ['name' => 'plus']) Add Rider</button>
</section>

@include('dashboards.transport.tabs', ['current' => 'admin.transport.riders'])

<section class="dash-panel" data-transport="riders" data-datatable-url="{{ url('/api/erp/student-transport/datatable') }}" data-resource-url="{{ url('/api/erp/student-transport') }}" data-stops-url="{{ url('/api/erp/transport-stops') }}" data-students-url="{{ url('/api/erp/transport-students') }}" data-sheet-url="{{ url('/api/erp/transport-routes') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Riders</h2>
            <p>Adding a student who already rides moves them to the new stop.</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-select"><span class="dash-visually-hidden">Route</span>
                <select data-filter="transport_route_id">
                    <option value="">All routes</option>
                    @foreach ($data['routes'] as $route)<option value="{{ $route->id }}">{{ $route->name }}</option>@endforeach
                </select>
            </label>
            <label class="dash-select"><span class="dash-visually-hidden">Status</span>
                <select data-filter="status">
                    <option value="active">Riding now</option>
                    <option value="ended">Ended</option>
                    <option value="">All</option>
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Class</th>
                    <th>Route</th>
                    <th>Stop</th>
                    <th>From</th>
                    <th>Fee</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="riderModal" aria-labelledby="riderModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'student'])</span>
            <div>
                <h3 id="riderModalTitle" data-modal-title>Add rider</h3>
                <p>The fee starts from this month.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-scroll">
            <div class="dash-form-grid">
            <div class="dash-field dash-field-wide">
                <label for="riderSearch">Student</label>
                <input id="riderSearch" type="search" placeholder="Type a name or admission no" autocomplete="off" data-student-search>
                <input type="hidden" name="student_id">
                <ul class="dash-holder-results" data-student-results></ul>
                <p class="dash-field-error" data-error-for="student_id" hidden></p>
            </div>
            <div class="dash-field dash-field-wide">
                <label for="riderStop">Stop</label>
                <select id="riderStop" name="transport_stop_id" required data-stop-select><option value="">Loading…</option></select>
                <p class="dash-field-error" data-error-for="transport_stop_id" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'from_date', 'label' => 'From', 'type' => 'date', 'required' => true, 'id' => 'riderFrom'])
            @include('dashboards.partials.field', ['name' => 'remarks', 'label' => 'Remarks', 'id' => 'riderRemarks'])
            </div>
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save Rider</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-transport.js') }}?v={{ filemtime(public_path('js/admin-transport.js')) }}" defer></script>
@endsection
