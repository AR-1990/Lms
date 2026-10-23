@extends('layouts.dashboard')

@section('title', 'Who Holds What')
@section('page_heading', 'Assets')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Who Holds What</h1>
        <p>Every hand-over: which person, department or room has which asset, since when, and what is overdue.</p>
    </div>
</section>

@include('dashboards.assets.tabs', ['current' => 'admin.assets.handovers'])

<section class="dash-panel" data-assets="handovers" data-datatable-url="{{ url('/api/erp/asset-assignments/datatable') }}" data-resource-url="{{ url('/api/erp/assets') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Hand-overs</h2>
            <p>Red means the return date has passed.</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-select"><span class="dash-visually-hidden">Open or returned</span>
                <select data-filter="open">
                    <option value="1">Still held</option>
                    <option value="0">Returned</option>
                    <option value="">All</option>
                </select>
            </label>
            <label class="dash-select"><span class="dash-visually-hidden">Holder type</span>
                <select data-filter="assignee_type">
                    <option value="">All holders</option>
                    <option value="employee">Employees</option>
                    <option value="student">Students</option>
                    <option value="department">Departments</option>
                    <option value="location">Rooms</option>
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Asset</th>
                    <th>Held by</th>
                    <th>Since</th>
                    <th>Return by</th>
                    <th>Returned</th>
                    <th class="dash-hide-md">Remarks</th>
                    <th><span class="dash-visually-hidden">Next step</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

{{-- Take back straight from the list --}}
<dialog class="dash-modal dash-form-modal" id="handoverReturn" aria-labelledby="handoverReturnTitle">
    <form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'arrow-left'])</span>
            <div><h3 id="handoverReturnTitle">Take back</h3><p data-action-asset></p></div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            @include('dashboards.partials.field', ['name' => 'returned_on', 'label' => 'Returned on', 'type' => 'date', 'required' => true, 'id' => 'hrOn'])
            @include('dashboards.partials.select', ['name' => 'condition_in_id', 'label' => 'Condition', 'options' => $data['conditions'], 'id' => 'hrCondition'])
            @include('dashboards.partials.select', ['name' => 'location_id', 'label' => 'Put in', 'options' => $data['locations'], 'empty' => 'Same place', 'id' => 'hrLocation'])
            @include('dashboards.partials.field', ['name' => 'remarks', 'label' => 'Remarks', 'type' => 'textarea', 'wide' => true, 'id' => 'hrRemarks'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>Take Back</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-assets.js') }}?v={{ filemtime(public_path('js/admin-assets.js')) }}" defer></script>
@endsection
