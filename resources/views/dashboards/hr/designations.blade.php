@extends('layouts.dashboard')

@section('title', 'Designations')
@section('page_heading', 'HR')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Designations</h1>
        <p>Job titles. Teaching designations make new employees teaching staff by default.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        Add Designation
    </button>
</section>

@include('dashboards.hr.tabs', ['current' => 'admin.hr.designations'])

<section class="dash-panel" data-hr="designations" data-datatable-url="{{ route('erp.designations.datatable') }}" data-resource-url="{{ route('erp.designations.index') }}">
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Designation</th>
                    <th class="dash-hide-md">Department</th>
                    <th>Staff</th>
                    <th>Type</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="hrModal" aria-labelledby="hrModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'key'])</span>
            <div>
                <h3 id="hrModalTitle" data-modal-title>Add designation</h3>
                <p>Pay grades come from the Lookups screen.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            @include('dashboards.partials.field', ['name' => 'name', 'label' => 'Name', 'required' => true, 'wide' => true])
            @include('dashboards.partials.select', ['name' => 'department_id', 'label' => 'Department', 'options' => $data['departments']])
            @include('dashboards.partials.select', ['name' => 'grade_id', 'label' => 'Pay grade', 'options' => $data['lookups']['pay_grade']])
            <label class="dash-switch">
                <input type="checkbox" name="is_teaching">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Teaching post</span>
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
<script src="{{ asset('js/admin-hr.js') }}?v={{ filemtime(public_path('js/admin-hr.js')) }}" defer></script>
@endsection
